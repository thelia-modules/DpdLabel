<?php

namespace DpdLabel\EventListeners;


use DpdLabel\enum\AuthorizedModuleEnum;
use DpdLabel\Service\LabelService;
use Picking\Event\GenerateLabelEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Controller\Admin\BaseAdminController;

/**
 * Class GenerateLabelListener
 *
 * This class is used only when you have the Picking module
 *
 * @package DpdLabel\EventListeners
 */
class GenerateLabelListener extends BaseAdminController implements EventSubscriberInterface
{
    protected $labelService;

    /**
     * @param LabelService $labelService
     */
    public function __construct(LabelService $labelService)
    {
        $this->labelService = $labelService;
    }

    /**
     * @param GenerateLabelEvent $event
     */
    public function generateLabel(GenerateLabelEvent $event)
    {
        $deliveryModuleCode = $event->getOrder()->getModuleRelatedByDeliveryModuleId()->getCode();
        if ($deliveryModuleCode === AuthorizedModuleEnum::DpdPickup->value) {
            $data = [];
            $orderId = $event->getOrder()->getId();
            $data['order_id'] = $orderId;
            $data['weight'] = $event->getWeight();
            $event->setResponse($this->service->generateLabel($data));
        }
    }

    public static function getSubscribedEvents()
    {
        $events = [];
        if (class_exists('Picking\Event\GenerateLabelEvent')){
            $events[GenerateLabelEvent::PICKING_GENERATE_LABEL] = ['generateLabel', 65];
        }
        return $events;
    }
}