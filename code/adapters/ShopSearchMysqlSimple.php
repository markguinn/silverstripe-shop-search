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
	 * @param array $facetSpec [optional]
	 * @return ArrayData
	 */
	function searchFromVars(array $data, array $facetSpec=array()) {
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
			'Facets'    => $this->buildFacets($matches, $facetSpec),
		));
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
				try {
					if (strpos($field, ',') !== false) {
						// compound fields
						$fields = explode(',', $field);
						$vals = array();
						foreach ($fields as $f) {
							if ($rec->hasField($f)) {
								$vals[] = $rec->obj($f);
							} else {
								$vals[] = $rec->$f();
							}
						}
						$this->countFacetValue($vals, $facet);
					} elseif ($rec->hasField($field)) {
						// fields
						$obj = $rec->obj($field);
						$this->countFacetValue($obj, $facet);
					} else {
						// relations
						$obj = $rec->$field();
						$this->countFacetValue($obj, $facet);
					}
				} catch(Exception $e) {}
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
	 * @param $obj
	 * @param $facet
	 */
	protected function countFacetValue($obj, &$facet) {
		if (is_array($obj) || $obj instanceof ArrayList || $obj instanceof DataList) {
			foreach ($obj as $o) {
				$this->countFacetValue($o, $facet);
			}
			return;
		}

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

		// apply it
		if (!isset($facet['Values'][$val])) {
			$facet['Values'][$val] = new ArrayData(array(
				'Label'     => $lbl,
				'Count'     => 1,
			));
		} else {
			$facet['Values'][$val]->Count++;
		}
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
