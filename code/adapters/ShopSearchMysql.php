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
	 * @return ArrayData
	 */
	function searchFromVars($keywords, array $filters=array(), array $facetSpec=array()) {
		$searchable = ShopSearch::get_searchable_classes();
		$matches = new ArrayList;

		foreach ($searchable as $className) {
			$list = DataObject::get($className);

			// get searchable fields
			$keywordFields = $this->getSearchFields($className);

			// build the filter
			$list = $list->where(sprintf("MATCH (%s) AGAINST ('%s')", $keywordFields, Convert::raw2sql($keywords)));

			// add in any other filters
			$list = FacetHelper::inst()->addFiltersToDataList($list, $filters);

			// add any matches to the big list
			$matches->merge($list);
		}

		return new ArrayData(array(
			'Matches'   => $matches,
			'Facets'    => FacetHelper::inst()->buildFacets($matches, $facetSpec),
		));
	}


	/**
	 * @param $className
	 * @return string
	 * @throws Exception
	 */
	protected function getSearchFields($className) {
		$indexes = Config::inst()->get($className, 'indexes');
		//Debug::dump($indexes);
		foreach ($indexes as $name => $index) {
			if (is_array($index)) {
				if (!empty($index['type']) && $index['type'] == 'fulltext' && !empty($index['value'])) {
					return $index['value']; //preg_split('/,\s*/', trim($index['values']));
				}
			} elseif (preg_match('/fulltext\((.+)\)/', $index, $m)) {
				return $m[1]; //preg_split('/,\s*/', trim($m[1]));
			}
		}

		throw new Exception("Class $className does not appear to have any fulltext indexes");
	}
}