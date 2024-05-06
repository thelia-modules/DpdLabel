<?php

namespace DpdLabel\Hook;


use DpdLabel\DpdLabel;
use DpdLabel\enum\AuthorizedModuleEnum;
use DpdLabel\Model\DpdlabelLabelsQuery;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;
use Thelia\Model\Map\ModuleTableMap;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderQuery;

class BackHook extends BaseHook
{
    public function onModuleConfig(HookRenderEvent $event)
    {
        $codes = ModuleQuery::create()
            ->select(ModuleTableMap::COL_CODE)
            ->filterByCode(DpdLabel::DPD_MODULES)
            ->find()
            ->toArray();
        $event->add($this->render('module_configuration.html', ['codes' => $codes]));
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

        $found = false;

        foreach (AuthorizedModuleEnum::cases() as $obj) {
            if ($obj->value === $moduleCode) {
                $found = true;
                break;
            }
        }

        if ($found) {
            $labelCreatedAt = DpdlabelLabelsQuery::create()
                ->filterByOrderId($event->getArgument('order_id'))
                ->findOne()
                ?->getCreatedAt();
            $event->add($this->render('hook/dpdlabel-order-edit-label.html', [
                'label_created_at' => $labelCreatedAt
            ]));
        }
    }
}
