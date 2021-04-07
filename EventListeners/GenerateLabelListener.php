<?php

namespace DpdLabel\EventListeners;


use Picking\Event\GenerateLabelEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Controller\Admin\BaseAdminController;


class GenerateLabelListener extends BaseAdminController implements EventSubscriberInterface
{
    protected $container;

    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @param GenerateLabelEvent $event
     */
    public function generateLabel(GenerateLabelEvent $event)
    {
        $deliveryModuleCode = $event->getOrder()->getModuleRelatedByDeliveryModuleId()->getCode();
        if ($deliveryModuleCode === "DpdPickup") {
            $data = [];
            $orderId = $event->getOrder()->getId();
            $data['new_status'] = '';
            $data['order_id'] = $orderId;
            $data['weight'] = $event->getWeight();
            $service = $this->container->get('dpdlabel.generate.label.service');
            $event->setResponse($service->generateLabel($data));
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