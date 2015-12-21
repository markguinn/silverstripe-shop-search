<?php
/**
 * Interface for shop search drivers.
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 09.03.2013
 * @package shop_search
 */
interface ShopSearchAdapter
{
    /**
     * @param string $keywords
     * @param array $filters [optional]
     * @param array $facetSpec [optional]
     * @param int $start [optional]
     * @param int $limit [optional]
     * @param string $sort [optional]
     * @return ArrayData - must contain at least 'Matches' with an list of data objects that match the search
     */
    public function searchFromVars($keywords, array $filters=array(), array $facetSpec=array(), $start=-1, $limit=-1, $sort='');
}
