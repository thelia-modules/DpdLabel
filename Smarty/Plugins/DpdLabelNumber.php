<?php

namespace DpdLabel\Smarty\Plugins;

use DpdLabel\Model\DpdlabelLabelsQuery;
use TheliaSmarty\Template\AbstractSmartyPlugin;
use TheliaSmarty\Template\SmartyPluginDescriptor;

class DpdLabelNumber extends AbstractSmartyPlugin
{
    public function getPluginDescriptors()
    {
        return [
            new SmartyPluginDescriptor('function', 'DpdLabelNumber', $this, 'dpdLabelNumber'),
        ];
    }

    /**
     * @param $params
     * @param $smarty
     */
    public function dpdLabelNumber($params, $smarty)
    {
        $orderId = $params["order_id"];

        $labelNumber = DpdlabelLabelsQuery::create()->filterByOrderId($orderId)->findOne();

        $smarty->assign('labelNbr', $labelNumber ? $labelNumber->getLabelNumber() : null);
    }
}