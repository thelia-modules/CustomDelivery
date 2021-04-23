<?php

namespace CustomDelivery\EventListeners;

use CustomDelivery\CustomDelivery;
use OpenApi\Events\DeliveryModuleOptionEvent;
use OpenApi\Events\OpenApiEvents;
use OpenApi\Model\Api\DeliveryModuleOption;
use OpenApi\Service\ImageService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Base\ModuleQuery;
use Thelia\Model\OrderPostage;
use Thelia\Module\Exception\DeliveryException;

class ApiListener implements EventSubscriberInterface
{
    /** @var ContainerInterface  */
    protected $container;

    /** @var Request */
    protected $request;

    /** @var ImageService  */
    protected $imageService;

    /**
     * APIListener constructor.
     * @param ContainerInterface $container We need the container because we use a service from another module
     * which is not mandatory, and using its service without it being installed will crash
     * @param RequestStack $requestStack
     * @param ImageService $imageService
     */
    public function __construct(
        ContainerInterface $container,
        RequestStack $requestStack,
        ImageService $imageService
    ) {
        $this->container = $container;
        $this->request = $requestStack->getCurrentRequest();
        $this->imageService = $imageService;
    }

    public function getDeliveryModuleOptions(DeliveryModuleOptionEvent $deliveryModuleOptionEvent)
    {
        if ($deliveryModuleOptionEvent->getModule()->getId() !== CustomDelivery::getModuleId()) {
            return ;
        }
        $isValid = true;
        $postage = null;
        $postageTax = null;

        $locale = $this->request->getSession()->getLang()->getLocale();

        $propelModule = ModuleQuery::create()
            ->filterById(CustomDelivery::getModuleId())
            ->findOne()
            ->setLocale($locale);

        try {
            $module = $propelModule->getModuleInstance($this->container);
            $country = $deliveryModuleOptionEvent->getCountry();
            $state = $deliveryModuleOptionEvent->getState();

            if (empty($module->isValidDelivery($country, $state))) {
                throw new DeliveryException(Translator::getInstance()->trans("Custom delivery is not available"));
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

        $image = null;
        $imageQuery = ModuleImageQuery::create()->findByModuleId($deliveryModuleOptionEvent->getModule()->getId())->getFirst();

        if (null !== $imageQuery) {
            try {
                $image = $this->imageService->getImageUrl($imageQuery, 'module');
            } catch (\Exception $e) {
                Tlog::getInstance()->addError($e);
            }
        }

        /** @var DeliveryModuleOption $deliveryModuleOption */
        $deliveryModuleOption = ($this->container->get('open_api.model.factory'))->buildModel('DeliveryModuleOption');
        $deliveryModuleOption
            ->setCode(CustomDelivery::getModuleCode())
            ->setValid($isValid)
            ->setTitle($propelModule->getTitle())
            ->setImage($image)
            ->setMinimumDeliveryDate($minimumDeliveryDate)
            ->setMaximumDeliveryDate($maximumDeliveryDate)
            ->setPostage($postage)
            ->setPostageTax($postageTax)
            ->setPostageUntaxed($postage - $postageTax)
        ;

        $deliveryModuleOptionEvent->appendDeliveryModuleOptions($deliveryModuleOption);
    }

    public static function getSubscribedEvents()
    {
        $listenedEvents = [];

        /** Check for old versions of Thelia where the events used by the API didn't exists */
        if (class_exists(DeliveryModuleOptionEvent::class)) {
            $listenedEvents[OpenApiEvents::MODULE_DELIVERY_GET_OPTIONS] = array("getDeliveryModuleOptions", 129);
        }

        return $listenedEvents;
    }
}
