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
	/** @var bool - if this is turned on it will use an algorith that doesn't require traversing the dataset if possible */
	private static $faster_faceting = false;

	/** @var bool - should the facets (link and checkbox only) be sorted - this can mess with things like category lists */
	private static $sort_facet_values = true;

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
	 * Processes the facet spec and removes any shorthand (field => label).
	 * @param array $facetSpec
	 * @return array
	 */
	public function expandFacetSpec(array $facetSpec) {
		if (is_null($facetSpec)) return array();
		$facets = array();

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

		return $facets;
	}


	/**
	 * This is super-slow. I'm assuming if you're using facets you
	 * probably also ought to be using Solr or something else. Or
	 * maybe you have unlimited time and can refactor this feature
	 * and submit a pull request...
	 *
	 * TODO: If this is going to be used for categories we're going	 *
	 * to have to really clean it up and speed it up.
	 * Suggestion:
	 *  - option to turn off counts
	 *  - switch order of nested array so we don't go through results unless needed
	 *  - if not doing counts, min/max and link facets can be handled w/ queries
	 *  - separate that bit out into a new function
	 * NOTE: This is partially done with the "faster_faceting" config
	 * option but more could be done, particularly by covering link facets as well.
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
		$facets = $this->expandFacetSpec($facetSpec);
		if (empty($facets) || !$matches || !$matches->count()) return new ArrayList();
		$fasterMethod = (bool)$this->config()->faster_faceting;

		// fill them in
		foreach ($facets as $field => &$facet) {
			// NOTE: using this method range and checkbox facets don't get counts
			if ($fasterMethod && $facet['Type'] != ShopSearch::FACET_TYPE_LINK) {
				if ($facet['Type'] == ShopSearch::FACET_TYPE_RANGE) {
					if (isset($facet['RangeMin'])) $facet['MinValue'] = $facet['RangeMin'];
					if (isset($facet['RangeMax'])) $facet['MaxValue'] = $facet['RangeMax'];
				}

				continue;
			}

			foreach ($matches as $rec) {
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
		$sortValues = self::config()->sort_facet_values;
		foreach ($facets as $f) {
			if ($sortValues) ksort($f['Values']);
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

						// Fill the proposed filter state with the current state,
						// then add/substract this item and it's kids
						$meAndMyKids = array($value->Value => $value->Value);
						if (!empty($value->Children)) $meAndMyKids += $this->getRecursiveChildValues($value->Children);

						$clickState = !$value->Active;
						foreach ($facet->Values as $val2) {
							// In this mode, we only actually send a filter for the lowest level items
							if (!empty($facet->FilterOnlyLeaves) && !empty($val2->Children)) continue;

							$active = (isset($meAndMyKids[$val2->Value])) ? $clickState : $val2->Active;
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
	 * @param ArrayList $children
	 * @return array
	 */
	protected function getRecursiveChildValues(ArrayList $children) {
		$out = array();

		foreach ($children as $child) {
			$out[$child->Value] = $child->Value;
			if (!empty($child->Children)) $out += $this->getRecursiveChildValues($child->Children);
		}

		return $out;
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
					$this->updateCheckboxFacetState(
						!empty($facet->NestedValues) ? $facet->NestedValues : $facet->Values,
						$filterVals,
						!empty($facet->FilterOnlyLeaves));
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


	/**
	 * For checkboxes, updates the state based on filters. Handles hierarchies and FilterOnlyLeaves
	 * @param ArrayList $values
	 * @param array     $filterVals
	 * @param bool      $filterOnlyLeaves [optional]
	 * @return bool - true if any of the children are true, false if all children are false
	 */
	protected function updateCheckboxFacetState(ArrayList $values, array $filterVals, $filterOnlyLeaves=false) {
		$out = false;

		foreach ($values as $value) {
			if ($filterOnlyLeaves && !empty($value->Children)) {
				if (in_array($value->Value, $filterVals)) {
					// This wouldn't be normal, but even if it's not a leaf, we want to handle
					// the case where a filter might be set for this node. It should still show up correctly.
					$value->Active = true;
					foreach ($value->Children as $c) $c->Active = true;
					// TODO: handle more than one level of recursion here
				} else {
					$value->Active = $this->updateCheckboxFacetState($value->Children, $filterVals, $filterOnlyLeaves);
				}
			} else {
				$value->Active = in_array($value->Value, $filterVals);
			}

			if ($value->Active) $out = true;
		}

		return $out;
	}


	/**
	 * If there are any facets (link or checkbox) that have a HierarchyDivider field
	 * in the spec, transform them into a hierarchy so they can be displayed as such.
	 *
	 * @param ArrayList $facets
	 * @return ArrayList
	 */
	public function transformHierarchies(ArrayList $facets) {
		foreach ($facets as $facet) {
			if (!empty($facet->HierarchyDivider)) {
				$out = new ArrayList();
				$parentStack = array();

				foreach ($facet->Values as $value) {
					if (empty($value->Label)) continue;
					$value->FullLabel = $value->Label;

					// Look for the most recent parent that matches the beginning of this one
					while (count($parentStack) > 0) {
						$curParent = $parentStack[ count($parentStack)-1 ];
						if (strpos($value->Label, $curParent->FullLabel) === 0) {
							if (!isset($curParent->Children)) $curParent->Children = new ArrayList();

							// Modify the name so we only show the last component
							$value->FullLabel = $value->Label;
							$p = strrpos($value->Label, $facet->HierarchyDivider);
							if ($p > -1) $value->Label = trim( substr($value->Label, $p + 1) );

							$curParent->Children->push($value);
							break;
						} else {
							array_pop($parentStack);
						}
					}

					// If we went all the way back to the root without a match, this is
					// a new parent item
					if (count($parentStack) == 0) {
						$out->push($value);
					}

					// Each item could be a potential parent. If it's not it will get popped
					// immediately on the next iteration
					$parentStack[] = $value;
				}

				$facet->NestedValues = $out;
			}
		}

		return $facets;
	}

}