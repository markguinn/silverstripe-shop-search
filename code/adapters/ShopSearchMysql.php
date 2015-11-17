<?php
/**
 * Adapter that will use MySQL's full text search features.
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 11.13.2013
 * @package shop_search
 * @subpackage adapters
 */
class ShopSearchMysql extends Object implements ShopSearchAdapter
{
	/**
	 * @param string $keywords
	 * @param array $filters [optional]
	 * @param array $facetSpec [optional]
	 * @param int $start [optional]
	 * @param int $limit [optional]
	 * @param string $sort [optional]
	 * @return ArrayData
	 */
	function searchFromVars($keywords, array $filters=array(), array $facetSpec=array(), $start=-1, $limit=-1, $sort='') {
		$searchable = ShopSearch::get_searchable_classes();
		$matches = new ArrayList;

		foreach ($searchable as $className) {
			$list = DataObject::get($className);

			// get searchable fields
			$keywordFields = $this->getSearchFields($className);

			// build the filter
			$filter = array();

			// Use parametrized query if SilverStripe >= 3.2
			if(SHOP_SEARCH_IS_SS32){
				foreach($keywordFields as $indexFields){
					$filter[] = array("MATCH ($indexFields) AGAINST (?)" => $keywords);
				}
				$list = $list->whereAny($filter);
			} else {
				foreach($keywordFields as $indexFields){
					$filter[] = sprintf("MATCH ($indexFields) AGAINST ('%s')", Convert::raw2sql($keywords));
				}
				// join all the filters with an "OR" statement
				$list = $list->where(implode(' OR ', $filter));
			}

			// add in any other filters
			$list = FacetHelper::inst()->addFiltersToDataList($list, $filters);

			// add any matches to the big list
			$matches->merge($list);
		}

		return new ArrayData(array(
			'Matches'   => $matches,
			'Facets'    => FacetHelper::inst()->buildFacets($matches, $facetSpec, (bool)Config::inst()->get('ShopSearch', 'auto_facet_attributes')),
		));
	}


	/**
	 * @param $className
	 * @return array an array containing fields per index
	 * @throws Exception
	 */
	protected function getSearchFields($className) {
		$indexes = Config::inst()->get($className, 'indexes');

		$indexList = array();
		foreach ($indexes as $name => $index) {
			if (is_array($index)) {
				if (!empty($index['type']) && $index['type'] == 'fulltext' && !empty($index['value'])) {
					$indexList[] = trim($index['value']);
				}
			} elseif (preg_match('/fulltext\((.+)\)/', $index, $m)) {
				$indexList[] = trim($m[1]);
			}
		}

		if(count($indexList) === 0){
			throw new Exception("Class $className does not appear to have any fulltext indexes");
		}

		return $indexList;
	}
}
