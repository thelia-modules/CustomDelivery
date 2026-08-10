<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CustomDelivery\EventListeners;

use CustomDelivery\CustomDelivery;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Api\Bridge\Propel\Event\DeliveryModuleOptionEvent;
use Thelia\Api\Resource\DeliveryModuleOption;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Base\ModuleQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Lang;
use Thelia\Model\Module;
use Thelia\Model\OrderPostage;
use Thelia\Module\Exception\DeliveryException;

class ApiListener implements EventSubscriberInterface
{
    protected ContainerInterface $container;
    protected \Symfony\Component\HttpFoundation\Request|null|Request $request;

    /**
     * APIListener constructor.
     *
     * @param ContainerInterface $container We need the container because we use a service from another module
     *                                      which is not mandatory, and using its service without it being installed will crash
     */
    public function __construct(
        ContainerInterface $container,
        RequestStack $requestStack
    ) {
        $this->container = $container;
        $this->request = $requestStack->getCurrentRequest();
    }

    public function getDeliveryModuleOptions(DeliveryModuleOptionEvent $deliveryModuleOptionEvent): void
    {
        if ($deliveryModuleOptionEvent->getModule()->getId() !== CustomDelivery::getModuleId()) {
            return;
        }
        $isValid = true;
        $postage = null;
        $postageTax = null;

        $locale = $this->request->getSession()->getLang()->getLocale();

        $propelModule = ModuleQuery::create()
            ->filterById(CustomDelivery::getModuleId())
            ->findOne()
            ?->setLocale($locale);

        if (null === $propelModule) {
            return;
        }

        try {
            $module = $propelModule->getModuleInstance($this->container);
            $country = $deliveryModuleOptionEvent->getCountry();
            $state = $deliveryModuleOptionEvent->getState();

            if (empty($module->isValidDelivery($country, $state))) {
                throw new DeliveryException(Translator::getInstance()->trans('Custom delivery is not available'));
            }

            /** @var OrderPostage $orderPostage */
            $orderPostage = $module->getPostage($country, $state);
            $postage = $orderPostage->getAmount();
            $postageTax = $orderPostage->getAmountTax();
        } catch (\Exception $exception) {
            $isValid = false;
        }

        $minimumDeliveryDate = ''; // TODO (calculate delivery date from day of order)
        $maximumDeliveryDate = ''; // TODO (calculate delivery date from day of order

        /** @var DeliveryModuleOption $deliveryModuleOption */
        $deliveryModuleOption = new DeliveryModuleOption();
        $deliveryModuleOption
            ->setCode(CustomDelivery::getModuleCode())
            ->setValid($isValid)
            ->setTitle($this->resolveModuleTitle($propelModule, $locale))
            ->setImage('')
            ->setMinimumDeliveryDate($minimumDeliveryDate)
            ->setMaximumDeliveryDate($maximumDeliveryDate)
            ->setPostage($postage)
            ->setPostageTax($postageTax)
            ->setPostageUntaxed($postage - $postageTax)
        ;

        $deliveryModuleOptionEvent->appendDeliveryModuleOptions($deliveryModuleOption);
    }

    /**
     * DeliveryModuleOption::setTitle() takes a string, and Propel returns null for a
     * locale the module has no module_i18n row for, so an untranslated module would
     * otherwise throw a TypeError and take the whole checkout page down.
     *
     * Falling back to the default language mirrors what the core does in
     * ResourceService::formatI18ns(): the back office "If a translation is missing or
     * incomplete" setting decides. Delivery module options are exposed on the front
     * only, so the admin exclusion that applies there has no equivalent here.
     */
    private function resolveModuleTitle(Module $module, string $locale): string
    {
        $title = $module->setLocale($locale)->getTitle();

        // Explicit emptiness test rather than ?: — "0" is a legitimate title
        // and must not count as a missing translation.
        if (null !== $title && '' !== $title) {
            return $title;
        }

        $fallbackLocale = $this->fallbackLocale($locale);

        if (null !== $fallbackLocale) {
            $title = $module->setLocale($fallbackLocale)->getTitle();
        }

        return $title ?? '';
    }

    private function fallbackLocale(string $currentLocale): ?string
    {
        if (Lang::REPLACE_BY_DEFAULT_LANGUAGE !== (int) ConfigQuery::getDefaultLangWhenNoTranslationAvailable()) {
            return null;
        }

        $defaultLocale = Lang::getDefaultLanguage()->getLocale();

        return $defaultLocale === $currentLocale ? null : $defaultLocale;
    }

    public static function getSubscribedEvents(): array
    {
        $listenedEvents = [];

        /* Check for old versions of Thelia where the events used by the API didn't exists */
        if (class_exists(DeliveryModuleOptionEvent::class)) {
            $listenedEvents[TheliaEvents::MODULE_DELIVERY_GET_OPTIONS] = ['getDeliveryModuleOptions', 129];
        }

        return $listenedEvents;
    }
}
