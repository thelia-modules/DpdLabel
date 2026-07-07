<?php

declare(strict_types=1);

namespace DpdLabel\EventListeners;


use DpdLabel\enum\AuthorizedModuleEnum;
use DpdLabel\Service\LabelService;
use Picking\Event\GenerateLabelEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class GenerateLabelListener
 *
 * This class is used only when you have the Picking module.
 *
 * NOTE (legacy, pre-existing): generateLabel() calls $this->service->generateLabel(),
 * but the injected property is $this->labelService and LabelService has no generateLabel()
 * method. This code path only runs when the Picking module dispatches its event, which is
 * not the case on this project. Behaviour left untouched (out of migration scope) — to be
 * fixed on a project that actually ships the Picking module.
 *
 * @package DpdLabel\EventListeners
 */
final class GenerateLabelListener implements EventSubscriberInterface
{
    public function __construct(protected LabelService $labelService)
    {
    }

    public function generateLabel(GenerateLabelEvent $event): void
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

    public static function getSubscribedEvents(): array
    {
        $events = [];
        if (class_exists('Picking\Event\GenerateLabelEvent')) {
            $events[GenerateLabelEvent::PICKING_GENERATE_LABEL] = ['generateLabel', 65];
        }

        return $events;
    }
}
