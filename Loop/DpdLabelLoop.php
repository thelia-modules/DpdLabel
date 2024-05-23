<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace DpdLabel\Loop;

use DpdLabel\enum\AuthorizedModuleEnum;
use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderQuery;
use Thelia\Tools\URL;

/**
 * @method int getOrderId()
 */
class DpdLabelLoop extends BaseLoop implements PropelSearchLoopInterface
{
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createAnyTypeArgument('order_id', null, true)
        );
    }

    public function buildModelCriteria()
    {
        $filter = [];

        foreach (AuthorizedModuleEnum::cases() as $case) {
            if ($module = ModuleQuery::create()->filterByCode($case->value)->filterByActivate(1)->findOne()) {
                $filter[] = $module->getId();
            }
        }

        return OrderQuery::create()
            ->filterById($this->getOrderId())
            ->filterByDeliveryModuleId($filter)
            ->orderByCreatedAt(Criteria::DESC);
    }

    public function parseResults(LoopResult $loopResult)
    {
        /** @var Order $order */
        foreach ($loopResult->getResultDataCollection() as $order) {
            $loopResultRow = new LoopResultRow();

            $loopResultRow
                ->set("LABEL_URL", URL::getInstance()?->absoluteUrl('/admin/module/DpdLabel/getLabel/' . $order->getRef() . '?download=1'))
            ;

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
