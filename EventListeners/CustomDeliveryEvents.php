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
use Thelia\Model\OrderStatusQuery;

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
     * At 32, after Thelia\Action\Order (128) has written the new status, so the order
     * already carries it and the event knows the status it left.
     */
    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::ORDER_UPDATE_STATUS => ["updateStatus", 32]
        ];
    }

    /**
     * Sends the module's own shipping message when an order shipped by this module
     * enters the "sent" status. From Thelia 3.3 the core sends its own shipping e-mail
     * for every carrier: the module then stays quiet, unless the merchant keeps its
     * message through the transition switch of the configuration page.
     */
    public function updateStatus(OrderEvent $event)
    {
        $order = $event->getOrder();
        $customDelivery = new CustomDelivery();

        if (!$order->isSent() || $order->getDeliveryModuleId() != $customDelivery->getModuleModel()->getId()) {
            return;
        }

        if ($this->wasAlreadySent($event)) {
            return;
        }

        if (CustomDelivery::coreSendsTheShippingEmail() && !CustomDelivery::keepsItsOwnShippingEmail()) {
            return;
        }

        $customer = $order->getCustomer();
        $contactEmail = ConfigQuery::getStoreEmail();

        if (!$contactEmail) {
            Tlog::getInstance()->debug(
                "Custom Delivery shipping message no contact email customer_id ".$customer->getId()
            );

            return;
        }

        if (null === MessageQuery::create()->filterByName('mail_custom_delivery')->findOne()) {
            throw new \Exception("Failed to load message 'mail_custom_delivery'.");
        }

        $package = $order->getDeliveryRef();
        $trackingUrl = null;

        if (!empty($package)) {
            $template = CustomDelivery::getTrackingUrlTemplate();
            $trackingUrl = '' === $template ? $package : str_replace('%ID%', rawurlencode((string) $package), $template);
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
    }

    /**
     * Saving an order that is already sent, or moving it between two statuses that
     * both mean sent, must not mail the customer again. The previous status is known
     * to the event from Thelia 3.2; on an older core every update is taken as an entry.
     */
    private function wasAlreadySent(OrderEvent $event): bool
    {
        if (!method_exists($event, 'getPreviousStatusId') || null === $event->getPreviousStatusId()) {
            return false;
        }

        if ($event->getPreviousStatusId() === $event->getStatus()) {
            return true;
        }

        return true === OrderStatusQuery::create()->findPk($event->getPreviousStatusId())?->isSent();
    }
}
