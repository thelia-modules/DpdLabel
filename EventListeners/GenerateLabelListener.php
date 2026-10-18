<?php

declare(strict_types=1);

namespace DpdLabel\EventListeners;

use DpdLabel\DpdLabel;
use DpdLabel\enum\AuthorizedModuleEnum;
use DpdLabel\Service\LabelService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Thelia\Core\Translation\Translator;
use Thelia\Tools\URL;

/**
 * Class GenerateLabelListener
 *
 * This class is used only when you have the Picking module: the event class belongs to
 * that optional module, so the listener does not type it and subscribes only when it exists.
 *
 * @package DpdLabel\EventListeners
 */
final class GenerateLabelListener implements EventSubscriberInterface
{
    public function __construct(protected LabelService $labelService)
    {
    }

    /**
     * @param \Picking\Event\GenerateLabelEvent $event
     */
    public function generateLabel(object $event): void
    {
        $order = $event->getOrder();

        if ($order->getModuleRelatedByDeliveryModuleId()->getCode() !== AuthorizedModuleEnum::DpdPickup->value) {
            return;
        }

        $weight = (float) $event->getWeight();

        if (0.0 === $weight) {
            $event->setResponse(new JsonResponse(['error' => 'weight argument not found']));

            return;
        }

        $labelPath = $this->labelService->getLabelPath($order);

        try {
            $label = $this->labelService->createLabel($order, $labelPath, $weight);

            $event->setResponse(new JsonResponse([
                'id' => $label->getId(),
                'url' => URL::getInstance()->absoluteUrl('/admin/module/DpdLabel/getLabel/'.$order->getRef()),
                'path' => $labelPath,
                'number' => $order->getRef(),
                'order' => [
                    'id' => $order->getId(),
                    'status' => [
                        'id' => $order->getOrderStatus()->getId(),
                    ],
                ],
            ]));
        } catch (\Exception $exception) {
            $event->setResponse(new JsonResponse([
                'error' => Translator::getInstance()->trans(
                    'Sorry, an unexpected error occurred: %err',
                    ['%err' => $exception->getMessage()],
                    DpdLabel::DOMAIN_NAME
                ),
            ]));
        }
    }

    public static function getSubscribedEvents(): array
    {
        $events = [];
        if (class_exists('Picking\Event\GenerateLabelEvent')) {
            $events[\Picking\Event\GenerateLabelEvent::PICKING_GENERATE_LABEL] = ['generateLabel', 65];
        }

        return $events;
    }
}
