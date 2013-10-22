<?php
/**
 * Adds methods for limited kinds of faceting using the silverstripe ORM.
 * This is used by the default ShopSearchSimple adapter but also can
 * be added to other contexts (such as ProductCategory).
 *
 * TODO: Facet class + subclasses
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 10.21.2013
 * @package shop_search
 * @subpackage helpers
 */
class FacetHelper extends Object
{
	/**
	 * @return FacetHelper
	 */
	public static function inst() {
		return Injector::inst()->get('FacetHelper');
	}


	/**
	 * @param SS_List $list
	 * @param array    $filters
	 * @param DataObject|string $sing - just a singleton object we can get information off of
	 * @return SS_List
	 */
	public function addFiltersToDataList(SS_List $list, array $filters, $sing=null) {
		if (!$sing) $sing = singleton($list->dataClass());
		if (is_string($sing)) $sing = singleton($sing);

		if (!empty($filters)) {
			foreach ($filters as $filterField => $filterVal) {
				$list = $list->filter($this->processFilterField($sing, $filterField, $filterVal));
			}
		}

		return $list;
	}


	/**
	 * @param DataObject $rec           This would normally just be a singleton but we don't want to have to create it over and over
	 * @param string     $filterField
	 * @param mixed      $filterVal
	 * @return array - returns the new filter added
	 */
	public function processFilterField($rec, $filterField, $filterVal) {
		// First check for VFI fields
		if ($rec->hasExtension('VirtualFieldIndex') && ($spec = $rec->getVFISpec($filterField))) {
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
				$filterField = $rec->getVFIFieldName($filterField);
			}
		}

		// Next check for regular db fields
		if ($rec->dbObject($filterField)) {
			// Is it a range value?
			if (preg_match('/^RANGE\~(.+)\~(.+)$/', $filterVal, $m)) {
				$filterField .= ':Between';
				$filterVal = array_slice($m, 1, 2);
			}

			return array($filterField => $filterVal);
		}

		return array();
	}


	/**
	 * This is super-slow. I'm assuming if you're using facets you
	 * probably also ought to be using Solr or something else. Or
	 * maybe you have unlimited time and can refactor this feature
	 * and submit a pull request...
	 *
	 * TODO: If this is going to be used for categories we're going
	 * to have to really clean it up and speed it up.
	 * Suggestion:
	 *  - option to turn off counts
	 *  - switch order of nested array so we don't go through results unless needed
	 *  - if not doing counts, min/max and link facets can be handled w/ queries
	 *  - separate that bit out into a new function
	 *
	 * Output - list of ArrayData in the format:
	 *   Label - name of the facet
	 *   Source - field name of the facet
	 *   Type - one of the ShopSearch::FACET_TYPE_XXXX constants
	 *   Values - SS_List of possible values for this facet
	 *
	 * @param SS_List $matches
	 * @param array $facetSpec
	 * @return ArrayList
	 */
	public function buildFacets(SS_List $matches, array $facetSpec) {
		if (empty($facetSpec) || !$matches || !$matches->count()) return new ArrayList();
		$facets = array();

		// set up the facets
		foreach ($facetSpec as $field => $label) {
			if (is_array($label)) {
				$facets[$field] = $label;
			} else {
				$facets[$field] = array('Label' => $label);
			}

			if (empty($facets[$field]['Source'])) $facets[$field]['Source'] = $field;
			if (empty($facets[$field]['Type']))  $facets[$field]['Type']  = ShopSearch::FACET_TYPE_LINK;

			if (empty($facets[$field]['Values'])) {
				$facets[$field]['Values'] = array();
			} else {
				$vals = $facets[$field]['Values'];
				if (is_string($vals)) $vals = eval('return ' . $vals . ';');
				$facets[$field]['Values'] = array();
				foreach ($vals as $val => $lbl) {
					$facets[$field]['Values'][$val] = new ArrayData(array(
						'Label'     => $lbl,
						'Value'     => $val,
						'Count'     => 0,
					));
				}
			}
		}

		// fill them in
		foreach ($matches as $rec) {
			foreach ($facets as $field => &$facet) {
				// If it's a range facet, set up the min/max
				if ($facet['Type'] == ShopSearch::FACET_TYPE_RANGE) {
					if (isset($facet['RangeMin'])) $facet['MinValue'] = $facet['RangeMin'];
					if (isset($facet['RangeMax'])) $facet['MaxValue'] = $facet['RangeMax'];
				}

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

						// if it's a range facet, calculate the min and max
						if ($facet['Type'] == ShopSearch::FACET_TYPE_RANGE) {
							if (!isset($facet['MinValue']) || $val < $facet['MinValue']) {
								$facet['MinValue'] = $val;
								$facet['MinLabel'] = $lbl;
							}
							if (!isset($facet['RangeMin']) || $val < $facet['RangeMin']) {
								$facet['RangeMin'] = $val;
							}
							if (!isset($facet['MaxValue']) || $val > $facet['MaxValue']) {
								$facet['MaxValue'] = $val;
								$facet['MaxLabel'] = $lbl;
							}
							if (!isset($facet['RangeMax']) || $val > $facet['RangeMax']) {
								$facet['RangeMax'] = $val;
							}
						}

						// Tally the value in the facets
						if (!isset($facet['Values'][$val])) {
							$facet['Values'][$val] = new ArrayData(array(
								'Label'     => $lbl,
								'Value'     => $val,
								'Count'     => 1,
							));
						} elseif ($facet['Values'][$val]) {
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
	 * Inserts a "Link" field into the values for each facet which can be
	 * used to get a filtered search based on that facets
	 *
	 * @param ArrayList $facets
	 * @param array     $baseParams
	 * @param string    $baseLink
	 * @return ArrayList
	 */
	public function insertFacetLinks(ArrayList $facets, array $baseParams, $baseLink) {
		$qs_f   = Config::inst()->get('ShopSearch', 'qs_filters');
		$qs_t   = Config::inst()->get('ShopSearch', 'qs_title');

		foreach ($facets as $facet) {
			if ($facet->Type == ShopSearch::FACET_TYPE_RANGE) {
				$params = array_merge($baseParams, array());
				if (!isset($params[$qs_f])) $params[$qs_f] = array();
				$params[$qs_f][$facet->Source] = 'RANGEFACETVALUE';
				$params[$qs_t] = $facet->Label . ': RANGEFACETLABEL';
				$facet->Link = $baseLink . '?' . http_build_query($params);
			} else {
				foreach ($facet->Values as $value) {
					// make a copy of the existing params
					$params = array_merge($baseParams, array());

					// add the filter for this value
					if (!isset($params[$qs_f])) $params[$qs_f] = array();
					if ($facet->Type == ShopSearch::FACET_TYPE_CHECKBOX) {
						$f = array();
						foreach ($facet->Values as $val2) {
							$active = $val2->Active;
							if ($value->Value == $val2->Value) $active = !$active;
							if ($active) $f[] = $val2->Value;
						}

						$params[$qs_f][$facet->Source] = $f;
						$params[$qs_t] = ($value->Active ? 'Remove ' : '') . $facet->Label . ': ' . $value->Label;
					} else {
						$params[$qs_f][$facet->Source] = $value->Value;
						$params[$qs_t] = $facet->Label . ': ' . $value->Label;
					}

					// build a new link
					$value->Link = $baseLink . '?' . http_build_query($params);
				}
			}
		}

		return $facets;
	}


	/**
	 * For checkbox and range facets, this updates the state (checked and min/max)
	 * based on current filter values.
	 *
	 * @param ArrayList $facets
	 * @param array     $filters
	 * @return ArrayList
	 */
	public function updateFacetState(ArrayList $facets, array $filters) {
		foreach ($facets as &$facet) {
			if ($facet->Type == ShopSearch::FACET_TYPE_CHECKBOX) {
				if (empty($filters[$facet->Source])) {
					// If the filter is not being used at all, we count
					// all values as active.
					foreach ($facet->Values as &$value) {
						$value->Active = true;
					}
				} else {
					$filterVals = $filters[$facet->Source];
					if (!is_array($filterVals)) $filterVals = array($filterVals);
					foreach ($facet->Values as &$value) {
						$value->Active = in_array($value->Value, $filterVals);
					}
				}
			} elseif ($facet->Type == ShopSearch::FACET_TYPE_RANGE) {
				if (!empty($filters[$facet->Source]) && preg_match('/^RANGE\~(.+)\~(.+)$/', $filters[$facet->Source], $m)) {
					$facet->MinValue = $m[1];
					$facet->MaxValue = $m[2];
				}
			}
		}

		return $facets;
	}
}