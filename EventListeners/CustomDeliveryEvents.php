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


namespace CustomDelivery\EventListeners;

use CustomDelivery\CustomDelivery;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Template\ParserInterface;
use Thelia\Log\Tlog;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\ConfigQuery;
use Thelia\Model\MessageQuery;

/**
 * Class CustomDeliveryEvents
 * @package CustomDelivery\EventListeners
 * @author Julien Chanséaume <julien@thelia.net>
 */
class CustomDeliveryEvents implements EventSubscriberInterface
{
    protected $parser;

    protected $mailer;

    public function __construct(ParserInterface $parser, MailerFactory $mailer)
    {
        $this->parser = $parser;
        $this->mailer = $mailer;
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     *  * The method name to call (priority defaults to 0)
     *  * An array composed of the method name to call and the priority
     *  * An array of arrays composed of the method names to call and respective
     *    priorities, or 0 if unset
     *
     * For instance:
     *
     *  * array('eventName' => 'methodName')
     *  * array('eventName' => array('methodName', $priority))
     *  * array('eventName' => array(array('methodName1', $priority), array('methodName2'))
     *
     * @return array The event names to listen to
     *
     * @api
     */
    public static function getSubscribedEvents()
    {
        return [
            TheliaEvents::ORDER_UPDATE_STATUS => ["updateStatus", 128]
        ];
    }

    public function updateStatus(OrderEvent $event)
    {
        $order = $event->getOrder();
        $customDelivery = new CustomDelivery();

        if ($order->isSent() && $order->getDeliveryModuleId() == $customDelivery->getModuleModel()->getId()) {
            $contactEmail = ConfigQuery::getStoreEmail();

            if ($contactEmail) {

                $message = MessageQuery::create()
                    ->filterByName('mail_custom_delivery')
                    ->findOne();

                if (false === $message) {
                    throw new \Exception("Failed to load message 'mail_custom_delivery'.");
                }

                $order = $event->getOrder();
                $customer = $order->getCustomer();
                $package = $order->getDeliveryRef();
                $trackingUrl = null;

                if (!empty($package)) {
                    $config = CustomDelivery::getConfig();
                    $trackingUrl = $config['url'];
                    if (!empty($trackingUrl)) {
                        $trackingUrl = str_replace('%ID%', $package, $trackingUrl);
                    }
                }

                $this->mailer->sendEmailMessage(
                    'mail_custom_delivery',
                    [$contactEmail => ConfigQuery::getStoreName()],
                    [$customer->getEmail() => $customer->getFirstname() . " " . $customer->getLastname()],
                    [
                        'customer_id' => $customer->getId(),
                        'order_id' => $order->getId(),
                        'order_ref' => $order->getRef(),
                        'order_date' => $order->getCreatedAt(),
                        'update_date' => $order->getUpdatedAt(),
                        'package' => $package,
                        'tracking_url' => $trackingUrl
                    ]
                );

                Tlog::getInstance()->debug(
                    "Custom Delivery shipping message sent to customer " . $customer->getEmail()
                );
            } else {
                $customer = $order->getCustomer();
                Tlog::getInstance()->debug(
                    "Custom Delivery shipping message no contact email customer_id ".$customer->getId()
                );
            }
        }
    }
}
