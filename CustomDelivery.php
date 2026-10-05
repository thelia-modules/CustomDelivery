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
use CustomDelivery\Service\InstallSql;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Thelia\Core\Translation\Translator;
use Thelia\Core\Install\Database;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
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
use Thelia\Model\ModuleConfigQuery;
use Thelia\Model\OrderPostage;
use Thelia\Model\State;
use Thelia\Module\AbstractDeliveryModuleWithState;
use Thelia\Module\BaseModule;
use Thelia\Module\DeliveryModuleInterface;
use Thelia\Module\Exception\DeliveryException;
use Thelia\Domain\Taxation\TaxEngine\Calculator;
use Thelia\Tools\I18n;

class CustomDelivery extends AbstractDeliveryModuleWithState
{
    const MESSAGE_DOMAIN = "customdelivery";

    const CONFIG_TRACKING_URL = 'custom_delivery_tracking_url';
    const CONFIG_PICKING_METHOD = 'custom_delivery_picking_method';
    const CONFIG_TAX_RULE_ID = 'custom_delivery_taxe_rule';

    const DEFAULT_TRACKING_URL = '%ID%';

    /**
     * Where the core reads the tracking address of a delivery module (see the parcel
     * tracking link of Thelia 3.3). Written as a string so the module keeps loading on
     * an older core.
     */
    const CORE_TRACKING_URL_KEY = 'tracking_url';

    /**
     * Transition switch: keep sending the module's own shipping e-mail on a core that
     * sends its own. Off by default.
     */
    const CONFIG_SEND_OWN_SHIPPING_EMAIL = 'send_own_shipping_email';

    const CORE_SHIPPING_EMAIL_LISTENER = 'Thelia\\Domain\\Order\\EventListener\\SendShippingEmailListener';

    const CORE_TRACKING_URL_RESOLVER = 'Thelia\\Domain\\Order\\Service\\OrderTrackingUrlResolver';
    const DEFAULT_PICKING_METHOD = 0;

    const METHOD_PRICE_WEIGHT = 0;
    const METHOD_PRICE = 1;
    const METHOD_WEIGHT = 2;

    /** @var Translator */
    protected $translator;

    public static function getConfig()
    {
        $config = [
            'url' => self::getTrackingUrlTemplate(),
            'send_own_shipping_email' => self::keepsItsOwnShippingEmail(),
            'method' => (
            intval(ConfigQuery::read(self::CONFIG_PICKING_METHOD, self::DEFAULT_PICKING_METHOD))
            ),
            'tax' => (
            intval(ConfigQuery::read(self::CONFIG_TAX_RULE_ID))
            )
        ];

        return $config;
    }

    /**
     * The tracking address template, where %ID% stands for the tracking number, kept
     * where the core reads the tracking address of a delivery module. One place only:
     * the shipping page of the back office and this module's configuration page both
     * edit it.
     */
    public static function getTrackingUrlTemplate(): string
    {
        return trim((string) self::getConfigValue(self::CORE_TRACKING_URL_KEY, ''));
    }

    public static function saveTrackingUrlTemplate(string $template): void
    {
        $template = trim($template);

        if ('' === $template) {
            ModuleConfigQuery::create()->deleteConfigValue(self::getModuleId(), self::CORE_TRACKING_URL_KEY);

            return;
        }

        self::setConfigValue(self::CORE_TRACKING_URL_KEY, $template);
    }

    /**
     * An http(s) address carrying %ID% outside the host. The core rule is used when the
     * core has one, so the module and the core never disagree on what a valid address is.
     */
    public static function isValidTrackingUrlTemplate(string $template): bool
    {
        $template = trim($template);

        if (class_exists(self::CORE_TRACKING_URL_RESOLVER)) {
            $resolver = self::CORE_TRACKING_URL_RESOLVER;

            return $resolver::isValidTemplate($template);
        }

        return self::isValidTrackingUrlTemplateWithoutCore($template);
    }

    /**
     * The same rule for a core that has none, kept public so it can be checked against
     * the core rule wherever both exist.
     */
    public static function isValidTrackingUrlTemplateWithoutCore(string $template): bool
    {
        $template = trim($template);
        $authority = (string) preg_replace('#^https?://([^/?\#]*).*$#is', '$1', $template);

        return str_contains($template, '%ID%')
            && !str_contains($authority, '%ID%')
            && 1 === preg_match('#^https?://[^\p{Z}\p{C}\\\\/?\#@]+(?:[/?\#][^\p{Z}\p{C}\\\\]*)?\z#iu', $template);
    }

    /**
     * The tracking link of a parcel for the module's own message: the template with the
     * url-encoded number, or the bare number when no valid template is set, as before.
     */
    public static function trackingUrlOf(string $trackingNumber): ?string
    {
        $trackingNumber = trim($trackingNumber);

        if ('' === $trackingNumber) {
            return null;
        }

        $template = self::getTrackingUrlTemplate();

        if (!self::isValidTrackingUrlTemplate($template)) {
            return $trackingNumber;
        }

        return str_replace('%ID%', rawurlencode($trackingNumber), $template);
    }

    public static function keepsItsOwnShippingEmail(): bool
    {
        return '1' === (string) self::getConfigValue(self::CONFIG_SEND_OWN_SHIPPING_EMAIL, '0');
    }

    /**
     * Whether the core tells the customers their order has left (Thelia 3.3 and later).
     * The core then owns that message, switched on or off in its store configuration:
     * the module only sends its own through the transition switch.
     */
    public static function coreHandlesTheShippingEmail(): bool
    {
        return class_exists(self::CORE_SHIPPING_EMAIL_LISTENER);
    }

    /**
     * Moves the historical global setting under the core key, once, so a shop that
     * updates keeps its tracking address without typing it again. The global setting
     * is put back to its old default afterwards, so an address cleared later on can
     * never be copied back.
     */
    private static function moveTrackingUrlUnderTheCoreKey(): void
    {
        $legacy = trim((string) ConfigQuery::read(self::CONFIG_TRACKING_URL, ''));

        if ('' === $legacy || self::DEFAULT_TRACKING_URL === $legacy) {
            return;
        }

        if ('' === self::getTrackingUrlTemplate()) {
            self::setConfigValue(self::CORE_TRACKING_URL_KEY, $legacy);
        }

        ConfigQuery::write(self::CONFIG_TRACKING_URL, self::DEFAULT_TRACKING_URL);
    }

    public function update($currentVersion, $newVersion, ?ConnectionInterface $con = null): void
    {
        $finder = Finder::create()
            ->name('*.sql')
            ->depth(0)
            ->sortByName()
            ->in(__DIR__.'/Config/update');

        $database = new Database($con);

        /** @var SplFileInfo $file */
        foreach ($finder as $file) {
            if (version_compare($currentVersion, $file->getBasename('.sql'), '<')) {
                $database->insertSql(null, [$file->getPathname()]);
            }
        }

        if (version_compare($currentVersion, '4.1.0', '<')) {
            self::moveTrackingUrlUnderTheCoreKey();
        }
    }

    public function postActivation(?ConnectionInterface $con = null): void
    {
        if (!$this->getConfigValue('is_initialized', false)) {
            $database = new Database($con);
            $installScript = tempnam(sys_get_temp_dir(), 'customdelivery');

            if (false === $installScript) {
                throw new \RuntimeException('Unable to write the CustomDelivery install script to the temporary directory');
            }

            file_put_contents($installScript, InstallSql::keepingExistingTables((string) file_get_contents(__DIR__ . '/Config/thelia.sql')));

            try {
                $database->insertSql(null, [$installScript]);
            } finally {
                unlink($installScript);
            }

            $this->setConfigValue('is_initialized', true);
        }

        // register config variables
        if (null === ConfigQuery::read(self::CONFIG_TRACKING_URL, null)) {
            ConfigQuery::write(self::CONFIG_TRACKING_URL, self::DEFAULT_TRACKING_URL);
        }

        if (null === ConfigQuery::read(self::CONFIG_PICKING_METHOD, null)) {
            ConfigQuery::write(self::CONFIG_PICKING_METHOD, self::DEFAULT_PICKING_METHOD);
        }

        self::moveTrackingUrlUnderTheCoreKey();

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
                    $this->trans('Your order {{ order_ref }} has been shipped', [], $locale)
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
    public function isValidDelivery(Country $country, ?State $state = null): bool
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
    public function getPostage(Country $country, ?State $state = null): OrderPostage|float
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
    public function handleVirtualProductDelivery(): bool
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

    public function getDeliveryMode()
    {
        return 'delivery';
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
    protected function getSlicePostage(Cart $cart, Country $country, ?State $state = null)
    {
        $config = self::getConfig();
        $currency = $cart->getCurrency();
        /** @var CustomDeliverySlice $slice */
        $slice = null;

        if (null !== $state && null !== $areas = CountryAreaQuery::create()
                ->filterByStateId($state->getId())
                ->select([CountryAreaTableMap::COL_AREA_ID])
                ->find()
        ) {
            $slice = $this->getAreaSlice($areas, $cart, $currency, $config);
        }

        if (null === $slice && null !== $areas = CountryAreaQuery::create()
                ->filterByCountryId($country->getId())
                ->filterByStateId(null)
                ->select([CountryAreaTableMap::COL_AREA_ID])
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
        if (0 == $currency->getByDefault()) {
            $untaxedPostage = $slice->getPrice() * $currency->getRate();
        } else {
            $untaxedPostage = $slice->getPrice();
        }
        $untaxedPostage = round($untaxedPostage, 2);

        $locale = $this->getRequest()->getSession()->getLang()->getLocale();

        return $this->buildOrderPostage($untaxedPostage, $country, $locale, $config['tax']);
    }

    /**
     * Defines how services are loaded in your modules
     *
     * @param ServicesConfigurator $servicesConfigurator
     */
    public static function configureServices(ServicesConfigurator $servicesConfigurator): void
    {
        $servicesConfigurator->load(self::getModuleCode().'\\', __DIR__)
            ->exclude([__DIR__.'/I18n/*', __DIR__.'/Config/**/*.php', __DIR__.'/Tests/*', __DIR__.'/CustomDelivery.php'])
            ->autowire(true)
            ->autoconfigure(true);
    }
}
