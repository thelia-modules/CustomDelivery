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


namespace CustomDelivery\Hook;

use CustomDelivery\CustomDelivery;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;

/**
 * Class HookManager
 * @package CustomDelivery\Hook
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class HookManager extends BaseHook
{

    public function onAccountOrderAfterProducts(HookRenderEvent $event)
    {
        $orderId = $event->getArgument('order');

        if (null !== $orderId) {
            $render = $this->render(
                'account-order-after-products.html',
                [
                    "order_id" => $orderId
                ]
            );
            $event->add($render);
        }

        $event->stopPropagation();
    }

    public function onModuleConfiguration(HookRenderEvent $event)
    {
        $moduleId = $this->getModule()->getModuleId();
        $method = 0;
        $config = CustomDelivery::getConfig();

        $event->add(
            $this->render("configuration.html"),
            [
                'module_id' => $moduleId,
                'method' => $config['method']
            ]
        );
    }
}
