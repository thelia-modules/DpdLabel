<?php

namespace DpdLabel\Hook;


use DpdLabel\enum\AuthorizedModuleEnum;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;
use Thelia\Model\OrderQuery;

class BackHook extends BaseHook
{
    public function onModuleConfig(HookRenderEvent $event)
    {
        $event->add($this->render('module_configuration.html'));
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
            $event->add($this->render('hook/dpdlabel-order-edit-label.html'));
        }
    }
}