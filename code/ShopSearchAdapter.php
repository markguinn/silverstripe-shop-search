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
	 * @param array $data
	 * @param array $facetSpec [optional]
	 * @return ArrayData - must contain at least 'Matches' with an list of data objects that match the search
	 */
	function searchFromVars(array $data, array $facetSpec=array());
}
