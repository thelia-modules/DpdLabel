<?php


namespace DpdLabel\Loop;


use DpdLabel\DpdLabel;
use DpdLabel\enum\AuthorizedModuleEnum;
use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Template\Loop\Order;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderQuery;


class DpdOrders extends Order
{
    public function buildModelCriteria()
    {
        $filter = [];

        foreach (AuthorizedModuleEnum::cases() as $case) {
            if ($module = ModuleQuery::create()->filterByCode($case->value)->filterByActivate(1)->findOne()) {
                $filter[] = $module->getId();
            }
        }

        return OrderQuery::create()
            ->filterByDeliveryModuleId($filter)
            ->filterByStatusId([DpdLabel::STATUS_PAID, DpdLabel::STATUS_PROCESSING])
            ->orderByCreatedAt(Criteria::DESC);
    }
}