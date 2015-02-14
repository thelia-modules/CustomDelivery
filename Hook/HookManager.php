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
 * @author Julien Chanséaume <julien@thelia.net>
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
        $config = CustomDelivery::getConfig();

        $event->add(
            $this->render(
                "configuration.html",
                [
                    'module_id' => $moduleId,
                    'method' => $config['method']
                ]
            )
        );
    }

    public function onModuleConfigJs(HookRenderEvent $event)
    {
        $event->add(
            $this->render("module-config-js.html")
        );
    }
}
