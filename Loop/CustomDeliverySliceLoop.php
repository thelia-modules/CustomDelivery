<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/


namespace CustomDelivery\Loop;

use CustomDelivery\Model\Base\CustomDeliverySliceQuery;
use CustomDelivery\Model\CustomDeliverySlice;
use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Type\EnumListType;
use Thelia\Type\TypeCollection;

/**
 * Class CustomDeliverySlideLoop
 * @package CustomDelivery\Loop
 * @author Julien Chanséaume <julien@thelia.net>
 */
class CustomDeliverySliceLoop extends BaseLoop implements PropelSearchLoopInterface
{
    protected $timestampable = false;

    protected $versionable = false;

    /**
     * @param LoopResult $loopResult
     *
     * @return LoopResult
     */
    public function parseResults(LoopResult $loopResult): LoopResult
    {
        /** @var CustomDeliverySlice $slice */
        foreach ($loopResult->getResultDataCollection() as $slice) {

            $loopResultRow = new LoopResultRow($slice);

            $loopResultRow
                ->set("ID", $slice->getId())
                ->set("AREA_ID", $slice->getAreaId())
                ->set("PRICE_MAX", $slice->getPriceMax())
                ->set("WEIGHT_MAX", $slice->getWeightMax())
                ->set("PRICE", $slice->getPrice());

            $this->addOutputFields($loopResultRow, $slice);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }

    /**
     * this method returns a Propel ModelCriteria
     *
     */
    public function buildModelCriteria(): \Propel\Runtime\ActiveQuery\ModelCriteria
    {
        $query = CustomDeliverySliceQuery::create();

        $id = $this->getArgValue('id');
        if (null !== $id) {
            $query->filterById($id, Criteria::IN);
        }

        $id = $this->getArgValue('area_id');
        if (null !== $id) {
            $query->filterByAreaId($id, Criteria::IN);
        }

        $orders = $this->getArgValue('order');

        foreach ($orders as $order) {
            switch ($order) {
                case "id":
                    $query->orderById(Criteria::ASC);
                    break;
                case "id_reverse":
                    $query->orderById(Criteria::DESC);
                    break;
                case "weight_max":
                    $query->orderByWeightMax(Criteria::ASC);
                    break;
                case "weight_max_reverse":
                    $query->orderByWeightMax(Criteria::DESC);
                    break;
                case "price_max":
                    $query->orderByPriceMax(Criteria::ASC);
                    break;
                case "price_max_reverse":
                    $query->orderByPriceMax(Criteria::DESC);
                    break;
                case "price":
                    $query->orderByPrice(Criteria::ASC);
                    break;
                case "price_reverse":
                    $query->orderByPrice(Criteria::DESC);
                    break;
            }
        }

        return $query;
    }

    /**
     * Definition of loop arguments
     *
     * example :
     *
     * public function getArgDefinitions()
     * {
     *  return new ArgumentCollection(
     *
     *       Argument::createIntListTypeArgument('id'),
     *           new Argument(
     *           'ref',
     *           new TypeCollection(
     *               new Type\AlphaNumStringListType()
     *           )
     *       ),
     *       Argument::createIntListTypeArgument('category'),
     *       Argument::createBooleanTypeArgument('new'),
     *       ...
     *   );
     * }
     *
     */
    protected function getArgDefinitions(): ArgumentCollection
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createIntListTypeArgument('area_id'),
            new Argument(
                'order',
                new TypeCollection(
                    new EnumListType(
                        [
                            'id',
                            'id_reverse',
                            'weight_max',
                            'weight_max_reverse',
                            'price_max',
                            'price_max_reverse',
                            'price',
                            'price_reverse',
                        ]
                    )
                ),
                'id'
            )
        );
    }
}
