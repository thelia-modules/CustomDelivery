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
use CustomDelivery\Form\ConfigurationForm;
use CustomDelivery\Model\CustomDeliverySliceQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Form\TheliaFormFactory;
use Thelia\Core\Hook\BaseHook;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Model\AreaQuery;
use Thelia\Model\Base\TaxRuleQuery;
use Thelia\Model\CurrencyQuery;

class HookManager extends BaseHook
{
    public function __construct(
        private readonly TheliaFormFactory $formFactory,
        private readonly RequestStack $requestStack,
        ?EventDispatcherInterface $dispatcher = null,
        ?ParserResolver $parserResolver = null,
    ) {
        parent::__construct($dispatcher, $parserResolver);
    }

    public static function getSubscribedHooks(): array
    {
        return [
            'module.configuration' => [
                ['type' => 'back', 'method' => 'onModuleConfiguration'],
            ],
            'module.config-js' => [
                ['type' => 'back', 'method' => 'onModuleConfigJs'],
            ],
        ];
    }

    public function onAccountOrderAfterProducts(HookRenderEvent $event): void
    {
        $orderId = $event->getArgument('order');

        if (null !== $orderId) {
            $render = $this->render(
                'account-order-after-products.html',
                [
                    'order_id' => $orderId,
                ]
            );
            $event->add($render);
        }

        $event->stopPropagation();
    }

    public function onModuleConfiguration(HookRenderEvent $event): void
    {
        $config = CustomDelivery::getConfig();

        $form = $this->formFactory->createForm(ConfigurationForm::getName());

        // Build areas with their slices for this module
        $moduleId = $this->getModule()->getModuleId();
        $areas = [];
        $areaCollection = AreaQuery::create()
            ->useAreaDeliveryModuleQuery()
                ->filterByDeliveryModuleId($moduleId)
            ->endUse()
            ->find();

        foreach ($areaCollection as $area) {
            $slices = [];
            $sliceCollection = CustomDeliverySliceQuery::create()
                ->filterByAreaId($area->getId())
                ->orderByWeightMax(Criteria::ASC)
                ->orderByPriceMax(Criteria::ASC)
                ->find();

            foreach ($sliceCollection as $slice) {
                $slices[] = [
                    'id' => $slice->getId(),
                    'priceMax' => $slice->getPriceMax(),
                    'weightMax' => $slice->getWeightMax(),
                    'price' => $slice->getPrice(),
                ];
            }

            $areas[] = [
                'id' => $area->getId(),
                'name' => $area->getName(),
                'slices' => $slices,
            ];
        }

        // Tax rules for the select
        $request = $this->requestStack->getCurrentRequest();
        $locale = $request ? $request->getLocale() : 'en_US';

        $taxRules = [];
        foreach (TaxRuleQuery::create()->orderById()->find() as $taxRule) {
            $taxRules[] = [
                'id' => $taxRule->getId(),
                'title' => $taxRule->setLocale($locale)->getTitle(),
            ];
        }

        // Default currency symbol
        $defaultCurrency = CurrencyQuery::create()->filterByByDefault(1)->findOne();
        $currencySymbol = $defaultCurrency ? $defaultCurrency->getSymbol() : '';

        $event->add(
            $this->render(
                'module-configuration.html.twig',
                [
                    'form' => $form->createView()->getView(),
                    'module_id' => $moduleId,
                    'method' => $config['method'],
                    'areas' => $areas,
                    'taxRules' => $taxRules,
                    'currencySymbol' => $currencySymbol,
                ]
            )
        );
    }

    public function onModuleConfigJs(HookRenderEvent $event): void
    {
        $event->add(
            $this->render('module-config-js.html.twig')
        );
    }
}
