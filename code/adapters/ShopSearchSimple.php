<?php
/**
 * VERY simple adapter to use DataList and :PartialMatch searches. Bare mininum
 * that will probably get terrible results but doesn't require any
 * additional setup.
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 09.03.2013
 * @package shop_search
 */
class ShopSearchSimple extends Object implements ShopSearchAdapter
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
			$keywordFields = $this->scaffoldSearchFields($className);

			// convert that list into something we can pass to Datalist::filter
			$keywordFilter = array();
			if (!empty($keywords)) {
				foreach($keywordFields as $searchField) {
					$name = (strpos($searchField, ':') !== FALSE) ? $searchField : "$searchField:PartialMatch";
					$keywordFilter[$name] = $keywords;
				}
			}
			if (count($keywordFilter) > 0) $list = $list->filterAny($keywordFilter);

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
	 * This is verbatim copied from GridFieldAddExistingAutocompleter, with the exception
	 * that the default is 'PartialMatch' instead of 'StartsWith'
	 *
	 * @param String $dataClass - the class name
	 * @return Array|null - names of the searchable fields, with filters if appropriate
	 */
	protected function scaffoldSearchFields($dataClass) {
		$obj = singleton($dataClass);
		$fields = null;
		if($fieldSpecs = $obj->searchableFields()) {
			$customSearchableFields = $obj->stat('searchable_fields');
			foreach($fieldSpecs as $name => $spec) {
				if(is_array($spec) && array_key_exists('filter', $spec)) {
					// The searchableFields() spec defaults to PartialMatch,
					// so we need to check the original setting.
					// If the field is defined $searchable_fields = array('MyField'),
					// then default to StartsWith filter, which makes more sense in this context.
					if(!$customSearchableFields || array_search($name, $customSearchableFields)) {
						$filter = 'PartialMatch';
					} else {
						$filter = preg_replace('/Filter$/', '', $spec['filter']);
					}
					$fields[] = "{$name}:{$filter}";
				} else {
					$fields[] = $name;
				}
			}
		}
		if (is_null($fields)) {
			if ($obj->hasDatabaseField('Title')) {
				$fields = array('Title');
			} elseif ($obj->hasDatabaseField('Name')) {
				$fields = array('Name');
			}
		}

		return $fields;
	}

}
