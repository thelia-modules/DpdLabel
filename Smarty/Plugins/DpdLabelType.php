<?php

namespace DpdLabel\Smarty\Plugins;

use DpdLabel\DpdLabel;
use DpdLabel\Model\DpdlabelLabelsQuery;
use Symfony\Component\Finder\Finder;
use TheliaSmarty\Template\AbstractSmartyPlugin;
use TheliaSmarty\Template\SmartyPluginDescriptor;
use function Sentry\init;

class DpdLabelType extends AbstractSmartyPlugin
{
    public function getPluginDescriptors()
    {
        return [
            new SmartyPluginDescriptor('function', 'DpdLabelType', $this, 'dpdLabelType'),
        ];
    }

    /**
     * @param $params
     * @param $smarty
     */
    public function dpdLabelType($params, $smarty)
    {
        $orderId = $params["order_id"];

        $ext = '???';

        if (null !== $label = DpdlabelLabelsQuery::create()->findOneByOrderId($orderId)) {

            $fileWithoutExt = DpdLabel::DPD_LABEL_DIR . $label->getOrder()->getRef();

            $files = (new Finder())
                ->files()
                ->in(DpdLabel::DPD_LABEL_DIR)
                ->name($label->getOrder()->getRef() . '.*')
                ->sortByName()
                ->reverseSorting();

            foreach ($files as $file) {
                $ext = strtoupper($file->getExtension());
                break;
            }
        }

        return $ext;
    }
}
