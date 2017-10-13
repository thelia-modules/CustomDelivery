<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace CustomDelivery;

use CustomDelivery\Model\CustomDeliverySlice;
use CustomDelivery\Model\CustomDeliverySliceQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Install\Database;
use Thelia\Model\Base\TaxRuleQuery;
use Thelia\Model\Cart;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
use Thelia\Model\CountryAreaQuery;
use Thelia\Model\Currency;
use Thelia\Model\LangQuery;
use Thelia\Model\Map\CountryAreaTableMap;
use Thelia\Model\Message;
use Thelia\Model\MessageQuery;
use Thelia\Model\OrderPostage;
use Thelia\Model\State;
use Thelia\Module\BaseModule;
use Thelia\Module\DeliveryModuleInterface;
use Thelia\Module\Exception\DeliveryException;
use Thelia\TaxEngine\Calculator;
use Thelia\Tools\I18n;

class CustomDelivery extends BaseModule implements DeliveryModuleInterface
{
    const MESSAGE_DOMAIN = "customdelivery";

    const CONFIG_TRACKING_URL = 'custom_delivery_tracking_url';
    const CONFIG_PICKING_METHOD = 'custom_delivery_picking_method';
    const CONFIG_TAX_RULE_ID = 'custom_delivery_taxe_rule';

    const DEFAULT_TRACKING_URL = '%ID%';
    const DEFAULT_PICKING_METHOD = 0;
    const DEFAULT_TAX_RULE_ID = 0;

    const METHOD_PRICE_WEIGHT = 0;
    const METHOD_PRICE = 1;
    const METHOD_WEIGHT = 2;

    /** @var Translator */
    protected $translator;

    public static function getConfig()
    {
        $config = [
            'url' => (
                ConfigQuery::read(self::CONFIG_TRACKING_URL, self::DEFAULT_TRACKING_URL)
            ),
            'method' => (
                intval(ConfigQuery::read(self::CONFIG_PICKING_METHOD, self::DEFAULT_PICKING_METHOD))
            ),
            'tax' => (
                intval(ConfigQuery::read(self::CONFIG_TAX_RULE_ID, self::DEFAULT_TAX_RULE_ID))
            )
        ];

        return $config;
    }

    public function preActivation(ConnectionInterface $con = null)
    {
        $injectSql = false;

        try {
            $item = CustomDeliverySliceQuery::create()->findOne();
        } catch (\Exception $ex) {
            // the table doest not exist
            $injectSql = true;
        }

        if (true === $injectSql) {
            $database = new Database($con);
            $database->insertSql(null, [__DIR__ . '/Config/thelia.sql']);
        }

        return true;
    }

    public function postActivation(ConnectionInterface $con = null)
    {
        // register config variables
        if (null === ConfigQuery::read(self::CONFIG_TRACKING_URL, null)) {
            ConfigQuery::write(self::CONFIG_TRACKING_URL, self::DEFAULT_TRACKING_URL);
        }

        if (null === ConfigQuery::read(self::CONFIG_PICKING_METHOD, null)) {
            ConfigQuery::write(self::CONFIG_PICKING_METHOD, self::DEFAULT_PICKING_METHOD);
        }

        if (null === ConfigQuery::read(self::CONFIG_TAX_RULE_ID, null)) {
            ConfigQuery::write(self::CONFIG_TAX_RULE_ID, self::DEFAULT_TAX_RULE_ID);
        }

        // create new message
        if (null === MessageQuery::create()->findOneByName('mail_custom_delivery')) {

            $message = new Message();
            $message
                ->setName('mail_custom_delivery')
                ->setHtmlTemplateFileName('custom-delivery-shipping.html')
                ->setHtmlLayoutFileName('')
                ->setTextTemplateFileName('custom-delivery-shipping.txt')
                ->setTextLayoutFileName('')
                ->setSecured(0);

            $languages = LangQuery::create()->find();

            foreach ($languages as $language) {
                $locale = $language->getLocale();

                $message->setLocale($locale);

                $message->setTitle(
                    $this->trans('Custom delivery shipping message', [], $locale)
                );
                $message->setSubject(
                    $this->trans('Your order {$order_ref} has been shipped', [], $locale)
                );
            }

            $message->save();
        }
    }

    /**
     * This method is called by the Delivery  loop, to check if the current module has to be displayed to the customer.
     * Override it to implements your delivery rules/
     *
     * If you return true, the delivery method will de displayed to the customer
     * If you return false, the delivery method will not be displayed
     *
     * @param Country $country the country to deliver to.
     * @param State $state the state to deliver to.
     *
     * @return boolean
     */
    public function isValidDelivery(Country $country, State $state = null)
    {
        // Retrieve the cart
        $cart = $this->getRequest()->getSession()->getSessionCart($this->getDispatcher());

        /** @var CustomDeliverySlice $slice */
        $slice = $this->getSlicePostage($cart, $country, $state);

        return null !== $slice;
    }

    /**
     * Calculate and return delivery price in the shop's default currency
     *
     * @param Country $country the country to deliver to.
     * @param State $state the state to deliver to.
     *
     * @return OrderPostage             the delivery price
     * @throws DeliveryException if the postage price cannot be calculated.
     */
    public function getPostage(Country $country, State $state = null)
    {
        $cart = $this->getRequest()->getSession()->getSessionCart($this->getDispatcher());

        /** @var CustomDeliverySlice $slice */
        $postage = $this->getSlicePostage($cart, $country, $state);

        if (null === $postage) {
            throw new DeliveryException();
        }

        return $postage;
    }

    /**
     *
     * This method return true if your delivery manages virtual product delivery.
     *
     * @return bool
     */
    public function handleVirtualProductDelivery()
    {
        return false;
    }

    protected function trans($id, array $parameters = [], $locale = null)
    {
        if (null === $this->translator) {
            $this->translator = Translator::getInstance();
        }

        return $this->translator->trans($id, $parameters, CustomDelivery::MESSAGE_DOMAIN, $locale);
    }

    /**
     * If a state is given and has slices, use them.
     * If state is given but has no slices, check if the country has slices.
     * If the country has slices, use them.
     * If the country has no slices, the module is not valid for delivery
     *
     * @param Cart $cart
     * @param Country $country
     * @param State $state
     * @return OrderPostage|null
     */
    protected function getSlicePostage(Cart $cart, Country $country, State $state = null)
    {
        $config = self::getConfig();
        $currency = $cart->getCurrency();
        /** @var CustomDeliverySlice $slice */
        $slice = null;

        if (null !== $state && null !== $areas = CountryAreaQuery::create()
                ->filterByStateId($state->getId())
                ->select([CountryAreaTableMap::AREA_ID])
                ->find()
        ) {
            $slice = $this->getAreaSlice($areas, $cart, $currency, $config);
        }

        if (null === $slice && null !== $areas = CountryAreaQuery::create()
                ->filterByCountryId($country->getId())
                ->filterByStateId(null)
                ->select([CountryAreaTableMap::AREA_ID])
                ->find()
        ) {
            $slice = $this->getAreaSlice($areas, $cart, $currency, $config);
        }

        if ($slice === null) {
            return null;
        }

        return $this->getAreaPostage($slice, $currency, $country, $config);
    }

    /**
     * @param $areas
     * @param Cart $cart
     * @param Currency $currency
     * @param $config
     * @return CustomDeliverySlice
     */
    protected function getAreaSlice($areas, Cart $cart, Currency $currency, $config)
    {
        $query = CustomDeliverySliceQuery::create()->filterByAreaId($areas, Criteria::IN);

        if ($config['method'] != CustomDelivery::METHOD_PRICE) {
            $query->filterByWeightMax($cart->getWeight(), Criteria::GREATER_THAN);
            $query->orderByWeightMax(Criteria::ASC);
        }

        if ($config['method'] != CustomDelivery::METHOD_WEIGHT) {
            $total = $cart->getTotalAmount();
            // convert amount to the default currency
            if (0 == $currency->getByDefault()) {
                $total = $total / $currency->getRate();
            }

            $query->filterByPriceMax($total, Criteria::GREATER_THAN);
            $query->orderByPriceMax(Criteria::ASC);
        }

        return $query->findOne();
    }

    /**
     * @param CustomDeliverySlice $slice
     * @param Currency $currency
     * @param Country $country
     * @param $config
     * @return OrderPostage
     */
    protected function getAreaPostage(CustomDeliverySlice $slice, Currency $currency, Country $country, $config)
    {
        $postage = new OrderPostage();

        if (0 == $currency->getByDefault()) {
            $price = $slice->getPrice() * $currency->getRate();
        } else {
            $price = $slice->getPrice();
        }
        $price = round($price, 2);

        $postage->setAmount($price);
        $postage->setAmountTax(0);

        // taxed amount
        if (0 !== $config['tax']) {
            $taxRuleI18N = I18n::forceI18nRetrieving(
                $this->getRequest()->getSession()->getLang()->getLocale(),
                'TaxRule',
                $config['tax']
            );
            $taxRule = TaxRuleQuery::create()->findPk($config['tax']);
            if (null !== $taxRule) {
                $taxCalculator = new Calculator();
                $taxCalculator->loadTaxRuleWithoutProduct($taxRule, $country);

                $postage->setAmount(
                    round($taxCalculator->getTaxedPrice($price), 2)
                );

                $postage->setAmountTax($postage->getAmount() - $price);

                $postage->setTaxRuleTitle($taxRuleI18N->getTitle());
            }
        }

        return $postage;
    }
}
