<?php


namespace DpdLabel\Loop;


use DpdLabel\DpdLabel;
use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Template\Loop\Order;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderQuery;


class DpdOrders extends Order
{
    public function buildModelCriteria()
    {
        $modules = ['DpdPickup', 'DpdClassic', 'Predict'];
        $filter = [];

        foreach ($modules as $moduleCode) {
            if ($module = ModuleQuery::create()->filterByCode($moduleCode)->filterByActivate(1)->findOne()) {
                $filter[] = $module->getId();
            }
        }

        return OrderQuery::create()
            ->filterByDeliveryModuleId($filter)
            ->filterByStatusId([DpdLabel::STATUS_PAID, DpdLabel::STATUS_PROCESSING])
            ->orderByCreatedAt(Criteria::DESC);
    }
}