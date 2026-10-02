<?php

declare(strict_types=1);

namespace DpdLabel\Hook;

use DpdLabel\DpdLabel;
use DpdLabel\Form\ApiConfigurationForm;
use DpdLabel\Form\LabelGenerationForm;
use DpdLabel\Model\DpdlabelLabelsQuery;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Form\TheliaFormFactory;
use Thelia\Core\Hook\BaseHook;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Model\Map\ModuleTableMap;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderQuery;
use Thelia\Tools\TokenProvider;

class BackHook extends BaseHook
{
    public function __construct(
        private readonly TheliaFormFactory $formFactory,
        private readonly RequestStack $requestStack,
        private readonly TokenProvider $tokenProvider,
        ?EventDispatcherInterface $dispatcher = null,
        ?ParserResolver $parserResolver = null,
    ) {
        parent::__construct($dispatcher, $parserResolver);
    }

    public static function getSubscribedHooks(): array
    {
        return [
            'module.configuration' => [
                ['type' => 'back', 'method' => 'onModuleConfig'],
            ],
            'main.in-top-menu-items' => [
                ['type' => 'back', 'method' => 'onMenuItems'],
            ],
            'order-edit.bill-top' => [
                ['type' => 'back', 'method' => 'onOrderBillTop'],
            ],
        ];
    }

    public function onModuleConfig(HookRenderEvent $event): void
    {
        $codes = ModuleQuery::create()
            ->select(ModuleTableMap::COL_CODE)
            ->filterByCode(DpdLabel::DPD_MODULES)
            ->find()
            ->toArray();

        $form = $this->formFactory->createForm(ApiConfigurationForm::getName());

        $event->add(
            $this->render('DpdLabel/module-configuration.html.twig', [
                'form' => $form->createView()->getView(),
                'codes' => $codes,
            ])
        );
    }

    public function onMenuItems(HookRenderEvent $event): void
    {
        $event->add($this->render('DpdLabel/hook/main-in-top-menu-items.html.twig'));
    }

    public function onOrderBillTop(HookRenderEvent $event): void
    {
        $orderId = (int) $event->getArgument('order_id');

        $order = OrderQuery::create()->findPk($orderId);
        if (null === $order) {
            return;
        }

        $moduleCode = $order->getModuleRelatedByDeliveryModuleId()?->getCode();
        if (!\in_array($moduleCode, DpdLabel::DPD_MODULES, true)) {
            return;
        }

        $label = DpdlabelLabelsQuery::create()
            ->filterByOrderId($orderId)
            ->findOne();

        $form = $this->formFactory->createForm(LabelGenerationForm::getName());

        $event->add(
            $this->render('DpdLabel/hook/order-edit-bill-top.html.twig', [
                'form' => $form->createView()->getView(),
                'order_id' => $orderId,
                'order_ref' => $order->getRef(),
                'weight' => $order->getWeight(),
                'label_number' => $label?->getLabelNumber(),
                'label_created_at' => $label?->getCreatedAt(),
                'label_type' => $this->resolveLabelType($order->getRef()),
                'token' => $this->tokenProvider->assignToken(),
                'err' => $this->requestStack->getCurrentRequest()?->query->get('err'),
            ])
        );
    }

    private function resolveLabelType(string $orderRef): string
    {
        foreach (glob(DpdLabel::DPD_LABEL_DIR.$orderRef.'.*') ?: [] as $file) {
            return strtoupper(pathinfo($file, \PATHINFO_EXTENSION));
        }

        return '???';
    }
}
