<?php
/**
 * VERY simply adapter to use mysql and 'like' searches. Bare mininum
 * that will probably get terrible results but doesn't require any
 * additional setup.
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 09.03.2013
 * @package shop_search
 */
class ShopSearchMysqlSimple implements ShopSearchAdapter
{
	/**
	 * @param array $data
	 * @return ArrayData
	 */
	function searchFromVars(array $data) {
		$searchable = ShopSearch::get_searchable_classes();
		$matches = new ArrayList;

		foreach ($searchable as $className) {
			// get searchable fields
			$fields = $this->scaffoldSearchFields($className);

			// convert that list into something we can pass to Datalist::filter
			$params = array();
			if (!empty($data['q'])) {
				foreach($fields as $searchField) {
					$name = (strpos($searchField, ':') !== FALSE) ? $searchField : "$searchField:PartialMatch";
					$params[$name] = $data['q'];
				}
			}

			// add any matches to the big list
			$list = DataObject::get($className);
			if (count($params) > 0) $list = $list->filterAny($params);
			$matches->merge($list);
		}

		return new ArrayData(array(
			'Matches'   => $matches,
		));
	}


	/**
	 * This is verbatim copied from GridFieldAddExistingAutocompleter, with the exception
	 * that the default is 'PartialMatch' instead of 'StartsWith'
	 *
	 * @param String $dataClass - the class name
	 * @return Array|null - names of the searchable fields, with filters if appropriate
	 */
	public function scaffoldSearchFields($dataClass) {
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
