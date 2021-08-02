<?php

namespace DpdLabel\Hook;


use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;
use Thelia\Model\ExportQuery;
use Thelia\Model\OrderQuery;

class BackHook extends BaseHook
{
    public function onModuleConfig(HookRenderEvent $event)
    {
        $export = ExportQuery::create()->filterByRef('dpdlabel.export.delivery')->findOne();
        $event->add($this->render('module_configuration.html',['exportId'=> $export->getId()]));
    }


    public function onMenuItems(HookRenderEvent $event)
    {
        $event->add($this->render('hook/dpdlabel-menu-item.html'));
    }

    /**
     * @param HookRenderEvent $event
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function onOrderBillTop(HookRenderEvent $event)
    {
        $moduleCode = OrderQuery::create()->findOneById($event->getArgument("order_id"))->getModuleRelatedByDeliveryModuleId()->getCode();

        if (in_array($moduleCode, ["DpdPickup", "DpdClassic", "Predict"])) {
            $event->add($this->render('hook/dpdlabel-order-edit-label.html'));
        }
    }
}