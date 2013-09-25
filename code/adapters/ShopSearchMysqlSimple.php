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
	 * @param string $keywords
	 * @param array $filters [optional]
	 * @param array $facetSpec [optional]
	 * @return ArrayData
	 */
	function searchFromVars($keywords, array $filters=array(), array $facetSpec=array()) {
		$searchable = ShopSearch::get_searchable_classes();
		$matches = new ArrayList;

		foreach ($searchable as $className) {
			$sing = singleton($className);
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
			if (!empty($filters)) {
				foreach ($filters as $filterField => $filterVal) {
					// If they gave us an array, it needs to be an OR filter, otherwise just add it to the stack
//					if (is_array($filterVal)) {
//						$orFilter = array();
//						foreach ($filterVal as $val) {
//							$orFilter += $this->processFilterField($sing, $filterField, $val);
//							Debug::dump(array($filterVal, $val, $orFilter));
//						}
//						$list = $list->filterAny($orFilter);
//					} else {
						$list = $list->filter($this->processFilterField($sing, $filterField, $filterVal));
//					}
				}
			}

//			Debug::dump($list->sql());
			// add any matches to the big list
			$matches->merge($list);
		}

		return new ArrayData(array(
			'Matches'   => $matches,
			'Facets'    => $this->buildFacets($matches, $facetSpec),
		));
	}


	/**
	 * @param DataObject $rec
	 * @param string     $filterField
	 * @param mixed      $filterVal
	 * @return array - returns the new filter added
	 */
	protected function processFilterField($rec, $filterField, $filterVal) {
		if ($rec->hasExtension('VirtualFieldIndex') && ($spec = $rec->getVFISpec($filterField))) {
			// First check for VFI fields
			if ($spec['Type'] == VirtualFieldIndex::TYPE_LIST) {
				// Lists have to be handled a little differently
				$f = $rec->getVFIFieldName($filterField) . ':PartialMatch';
				if (is_array($filterVal)) {
					foreach ($filterVal as &$val) $val = '|' . $val . '|';
					return array($f => $filterVal);
				} else {
					return array($f => '|' . $filterVal . '|');
				}
			} else {
				// Simples are simple
				return array($rec->getVFIFieldName($filterField) => $filterVal);
			}
		} elseif ($rec->dbObject($filterField)) {
			// Next check for regular db fields
			return array($filterField => $filterVal);
		}
	}


	/**
	 * This is super-slow. I'm assuming if you're using facets you
	 * probably also ought to be using Solr or something else. Or
	 * maybe you have unlimited time and can refactor this feature
	 * and submit a pull request...
	 *
	 * Output - list of ArrayData in the format:
	 *   Label - name of the facet
	 *   Field - field name of the facet
	 *   Values - SS_List of possible values for this facet
	 *
	 * @param ArrayList $matches
	 * @param array $facetSpec
	 * @return ArrayList
	 */
	protected function buildFacets(ArrayList $matches, array $facetSpec) {
		if (empty($facetSpec) || !$matches || !$matches->count()) return new ArrayList();
		$facets = array();

		// set up the facets
		foreach ($facetSpec as $field => $label) {
			$facets[$field] = array(
				'Label'    => $label,
				'Field'    => $field,
				'Values'   => array(), // this will be converted to arraylist below
			);
		}

		// fill them in
		foreach ($matches as $rec) {
			foreach ($facets as $field => &$facet) {
				// If the field is accessible via normal methods, including
				// a user-defined getter, prefer that
				$fieldValue = $rec->relObject($field);
				if (is_null($fieldValue) && $rec->hasMethod($meth = "get{$field}")) $fieldValue = $rec->$meth();

				// If not, look for a VFI field
				if (!$fieldValue && $rec->hasExtension('VirtualFieldIndex')) $fieldValue = $rec->getVFI($field);

				// If we found something, process it
				if (!empty($fieldValue)) {
					// normalize so that it's iterable
					if (!is_array($fieldValue) && !$fieldValue instanceof SS_List) $fieldValue = array($fieldValue);

					foreach ($fieldValue as $obj) {
						if (empty($obj)) continue;

						// figure out the right label
						if (is_object($obj) && $obj->hasMethod('Nice')) {
							$lbl = $obj->Nice();
						} elseif (is_object($obj) && !empty($obj->Title)) {
							$lbl = $obj->Title;
						} else {
							$lbl = (string)$obj;
						}

						// figure out the value for sorting
						if (is_object($obj) && $obj->hasMethod('getAmount')) {
							$val = $obj->getAmount();
						} elseif (is_object($obj) && !empty($obj->ID)) {
							$val = $obj->ID;
						} else {
							$val = (string)$obj;
						}

						// Tally the value in the facets
						if (!isset($facet['Values'][$val])) {
							$facet['Values'][$val] = new ArrayData(array(
								'Label'     => $lbl,
								'Value'     => $val,
								'Count'     => 1,
							));
						} else {
							$facet['Values'][$val]->Count++;
						}
					}
				}
			}
		}

		// convert values to arraylist
		$out = new ArrayList();
		foreach ($facets as $f) {
			ksort($f['Values']);
			$f['Values'] = new ArrayList($f['Values']);
			$out->push(new ArrayData($f));
		}

		return $out;
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
