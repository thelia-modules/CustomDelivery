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
use Thelia\Install\Database;
use Thelia\Model\Cart;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
use Thelia\Model\Message;
use Thelia\Model\MessageQuery;
use Thelia\Module\BaseModule;
use Thelia\Module\DeliveryModuleInterface;
use Thelia\Module\Exception\DeliveryException;

class CustomDelivery extends BaseModule implements DeliveryModuleInterface
{

    const CONFIG_TRACKING_URL = 'custom_delivery_tracking_url';
    const CONFIG_PICKING_METHOD = 'custom_delivery_picking_method';
    const CONFIG_TAX_RULE_ID = 'custom_delivery_taxe_rule';

    const DEFAULT_TRACKING_URL = '%ID%';
    const DEFAULT_PICKING_METHOD = 0;
    const DEFAULT_TAX_RULE_ID = 0;

    const METHOD_PRICE_WEIGHT = 0;
    const METHOD_PRICE = 1;
    const METHOD_WEIGHT = 2;

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

        /*
        // delete existing message
        $message = MessageQuery::create()
            ->filterByName('virtual_product_offer')
            ->findOne($con);

        if (null !== $message) {
            $message->delete($con);
        }

        // create new message
        $message = new Message();
        $message
            ->setName('virtual_product_offer')
            ->setSecured(0)
        ;

        $message->save();
        */
    }

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

    /**
     * This method is called by the Delivery  loop, to check if the current module has to be displayed to the customer.
     * Override it to implements your delivery rules/
     *
     * If you return true, the delivery method will de displayed to the customer
     * If you return false, the delivery method will not be displayed
     *
     * @param Country $country the country to deliver to.
     *
     * @return boolean
     */
    public function isValidDelivery(Country $country)
    {
        // Retrieve the cart
        $areaId = $country->getAreaId();
        $cart = $this->getRequest()->getSession()->getSessionCart();

        /** @var CustomDeliverySlice $slice */
        $slice = $this->getSlicePostage($cart, $areaId);

        return null !== $slice;
    }

    /**
     * @param Cart $cart
     * @param $areaId
     * @return CustomDeliverySlice|null
     */
    protected function getSlicePostage(Cart $cart, $areaId)
    {
        $slice = CustomDeliverySliceQuery::create()
            ->filterByWeightMax($cart->getWeight(), Criteria::LESS_THAN)
            ->orderByWeightMax(Criteria::DESC)
            ->useAreaDeliveryModuleQuery()
                ->filterByAreaId($areaId)
            ->endUse()
            ->findOne()
        ;

        return $slice;
    }

    /**
     * Calculate and return delivery price in the shop's default currency
     *
     * @param Country $country the country to deliver to.
     *
     * @return float             the delivery price
     * @throws DeliveryException if the postage price cannot be calculated.
     */
    public function getPostage(Country $country)
    {
        $areaId = $country->getAreaId();
        $cart = $this->getRequest()->getSession()->getSessionCart();

        /** @var CustomDeliverySlice $slice */
        $slice = $this->getSlicePostage($cart, $areaId);

        if (null === $slice) {
            throw new DeliveryException();
        }

        return $slice->getPrice();
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
}
