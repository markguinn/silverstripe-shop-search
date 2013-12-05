<?php
/**
 * Search driver for the fulltext module with solr backend.
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 08.29.2013
 * @package shop_search
 */
class ShopSearchSolr extends SolrIndex implements ShopSearchAdapter
{
	/** @var array - maps our names for fields to Solr's names (i.e. Title => SiteTree_Title) */
	protected $fieldMap = array();

	/**
	 * Sets up the index
	 */
	function init() {
		$searchables = ShopSearch::get_searchable_classes();

		// Add each class to the index
		foreach ($searchables as $class) {
			$this->addClass($class);
		}

		// add the fields they've specifically asked for
		$fields = $this->getFulltextSpec();
		foreach ($fields as $def) {
			$this->addFulltextField($def['field'], $def['type'], $def['params']);
		}

		// add the filters they've asked for
		$filters = $this->getFilterSpec();
		foreach ($filters as $filterName => $def) {
			// NOTE: I'm pulling the guts out of this function so we can access Solr's full name
			// for the field (SiteTree_Title for Title) and build the fieldMap in one step instead
			// of two.
			//$this->addFilterField($def['field'], $def['type'], $def['params']);
			$singleFilter = $this->fieldData($def['field'], $def['type'], $def['params']);
			$this->filterFields = array_merge($this->filterFields, $singleFilter);
			foreach ($singleFilter as $solrName => $solrDef) {
				if ($def['field'] == $solrDef['field']) {
					$this->fieldMap[$filterName] = $solrName;
				}
			}
		}

//		Debug::dump($this->filterFields);

		// Add spellcheck fields
//		$spellFields = $cfg->get('ShopSearch', 'spellcheck_dictionary_source');
//		if (empty($spellFields) || !is_array($spellFields)) {
//			$spellFields = array();
//			$ftFields = $this->getFulltextFields();
//			foreach	($ftFields as $name => $fieldDef) {
//				$spellFields[] = $name;
//			}
//		}
//
//		foreach ($spellFields as $f) {
//			$this->addCopyField($f, '_spellcheckContent');
//		}

		// Technically, filter and sort fields are the same in Solr/Lucene
//		$this->addSortField('ViewCount');
//		$this->addSortField('LastEdited', 'SSDatetime');

		// Aggregate fields for spelling checks
//		$this->addCopyField('Title', 'spellcheckData');
//		$this->addCopyField('Content', 'spellcheckData');

//		$this->addFullTextField('Category', 'Int', array(
//			'multi_valued'  => true,
//			'stored'        => true,
//			'lookup_chain'  => array(
//				'call'      => 'method',
//				'method'    => 'getAllProductCategoryIDs',
//			)
//		));

		// I can't get this to work. Need a way to create the Category field that get used
//		$this->addFilterField('Category', 'Int');
//		$this->addFilterField('Parent.ID');
//		$this->addFilterField('ProductCategories.ID');
//		$this->addCopyField('SiteTree_Parent_ID', 'Category');
//		$this->addCopyField('Product_ProductCategories_ID', 'Category');

		// These will be added in a pull request to shop module. If they're not present they'll be ignored
//		$this->addFilterField('AllCategoryIDs', 'Int', array('multiValued' => 'true'));
//		$this->addFilterField('AllRecursiveCategoryIDs', 'Int', array('multiValued' => 'true'));

		// This will cause only live pages to be indexed. There are two ways to do
		// this. See fulltextsearch/docs/en/index.md for more information.
		// Not sure if this is really the way to go or not, but for now this is it.
		$this->excludeVariantState(array('SearchVariantVersioned' => 'Stage'));
	}


	/**
	 * Transforms different formats of field list into something we can pass to solr
	 * @param array $in
	 * @return array
	 */
	protected function scrubFieldList($in) {
		$out = array();
		if (empty($in) || !is_array($in)) return $out;

		foreach ($in as $name => $val) {
			// supports an indexed array format of simple field names
			if (is_numeric($name)) {
				$name = $val;
				$val = true;
			}

			// supports a boolean value meaning "use the default setup"
			$params = !is_array($val) ? array() : array_slice($val, 0);

			// build a normalized structur
			$def = array(
				'field'     => isset($params['field']) ? $params['field'] : $name,
				'type'      => isset($params['type']) ? $params['type'] : null,
				'params'    => $params,
			);

			if (isset($def['params']['field'])) unset($def['params']['field']);
			if (isset($def['params']['type']))  unset($def['params']['type']);

			$out[$name] = $def;
		}

		return $out;
	}


	/**
	 * @return array
	 */
	protected function getFulltextSpec() {
		$fields = Config::inst()->get('ShopSearch', 'solr_fulltext_fields');
		if (empty($fields)) $fields = array('Title', 'Content');
		return $this->scrubFieldList($fields);
	}


	/**
	 *
	 */
	protected function getFilterSpec() {
		$fields = Config::inst()->get('ShopSearch', 'solr_filter_fields');
		return $this->scrubFieldList($fields);
	}


	/**
	 * @return string
	 */
	function getFieldDefinitions() {
		$xml = parent::getFieldDefinitions();
//		$xml .= "\n\t\t<field name='_spellcheckContent' type='htmltext' indexed='true' stored='false' multiValued='true' />";

		// create a sorting column
		if (isset($this->fieldMap['Title'])) {
			$xml .= "\n\t\t" . '<field name="_titleSort" type="alphaOnlySort" indexed="true" stored="false" required="false" multiValued="false" />';
			$xml .= "\n\t\t" . '<copyField source="SiteTree_Title" dest="_titleSort"/>';
		}

		// create an autocomplete column
		if (ShopSearch::config()->suggest_enabled) {
			$xml .= "\n\t\t<field name='_autocomplete' type='autosuggest_text' indexed='true' stored='false' multiValued='true'/>";
		}

		return $xml;
	}


	/**
	 * @return string
	 */
	function getCopyFieldDefinitions() {
		$xml = parent::getCopyFieldDefinitions();

		if (ShopSearch::config()->suggest_enabled) {
			foreach ($this->fulltextFields as $name => $field) {
				$xml .= "\n\t<copyField source='{$name}' dest='_autocomplete' />";
				//$xml .= "\n\t<copyField source='{$name}' dest='_spellcheckContent' />";
			}
		}

		return $xml;
	}


		/**
	 * Overrides the parent to add a field for autocomplete
	 * @return HTMLText
	 */
	function getTypes() {
		$val = parent::getTypes();
		if (!$val || !is_object($val)) return $val;
		$xml = $val->getValue();
		$xml .= <<<XML

	        <fieldType name="autosuggest_text" class="solr.TextField"
	                   positionIncrementGap="100">
	            <analyzer type="index">
	                <tokenizer class="solr.StandardTokenizerFactory"/>
	                <filter class="solr.LowerCaseFilterFactory"/>
	                <filter class="solr.ShingleFilterFactory" minShingleSize="2" maxShingleSize="4" outputUnigrams="true" outputUnigramsIfNoShingles="true" />
	                <filter class="solr.PatternReplaceFilterFactory" pattern="^([0-9. ])*$" replacement=""
	                        replace="all"/>
	                <filter class="solr.RemoveDuplicatesTokenFilterFactory"/>
	            </analyzer>
	            <analyzer type="query">
	                <tokenizer class="solr.StandardTokenizerFactory"/>
	                <filter class="solr.LowerCaseFilterFactory"/>
	            </analyzer>
	        </fieldType>

XML;
		$val->setValue($xml);
		return $val;
	}

	/**
	 * This is an intermediary to bridge the search form input
	 * and the SearchQuery class. It allows us to have other
	 * drivers that may not use the FullTextSearch module.
	 *
	 * @param string $keywords
	 * @param array $filters [optional]
	 * @param array $facetSpec [optional]
	 * @param int $start [optional]
	 * @param int $limit [optional]
	 * @param string $sort [optional]
	 * @return ArrayData
	 */
	public function searchFromVars($keywords, array $filters=array(), array $facetSpec=array(), $start=-1, $limit=-1, $sort='score desc') {
		$query = new SearchQuery();
		$params = array(
			'sort'  => $sort,
		);

		// swap out title search
		if ($params['sort'] == 'SiteTree_Title') $params['sort'] = '_titleSort';

		// search by keywords
		$query->search(empty($keywords) ? '*:*' : $keywords);

		// search by filter
		foreach ($filters as $k => $v) {
			if (isset($this->fieldMap[$k])) {
				if (is_string($v) && preg_match('/^RANGE\~(.+)\~(.+)$/', $v, $m)) {
					// Is it a range value?
					$range = new SearchQuery_Range($m[1], $m[2]);
					$query->filter($this->fieldMap[$k], $range);
				} else {
					// Or a normal scalar value
					$query->filter($this->fieldMap[$k], $v);
				}
			}
		}

		// add facets
		$facetSpec = FacetHelper::inst()->expandFacetSpec($facetSpec);
		$params += $this->buildFacetParams($facetSpec);

		// TODO: add spellcheck

		return $this->search($query, $start, $limit, $params, $facetSpec);
	}


	/**
	 * @param string $keywords
	 * @param array  $filters
	 * @return array
	 */
	public function suggestWithResults($keywords, array $filters = array()) {
		$limit      = (int)ShopSearch::config()->sayt_limit;

		// process the keywords a bit
		$terms      = preg_split('/\s+/', trim(strtolower($keywords)));
		$lastTerm   = count($terms) > 0 ? array_pop($terms) : '';
		$prefix     = count($terms) > 0 ? implode(' ', $terms) . ' ' : '';
		$terms[]    = $lastTerm;
		$terms[]    = $lastTerm . '*'; // this allows for partial words to still match

		// convert that to something solr adapater can handle
		$query = new SearchQuery();
		$query->search(implode(' ', $terms) . ' ' . $lastTerm . '*');

		$params = array(
			'sort'          => 'score desc',
			'facet'         => 'true',
			'facet.field'   => '_autocomplete',
			'facet.limit'   => ShopSearch::config()->suggest_limit,
			'facet.prefix'  => $lastTerm,
		);

//		$facetSpec = array(
//			'_autocomplete' => array(
//				'Type'      => ShopSearch::FACET_TYPE_LINK,
//				'Label'     => 'Suggestions',
//				'Source'    => '_autocomplete',
//			),
//		);
//
//		Debug::dump($query);
//
//		$search     = $this->search($query, 0, $limit, $params, $facetSpec);
//		Debug::dump($search);
//		$prodList   = $search->Matches;
//
//		$suggestsion = array();
////		if ($)

		$service = $this->getService();

		SearchVariant::with(count($query->classes) == 1 ? $query->classes[0]['class'] : null)->call('alterQuery', $query, $this);

		$q = $terms;
		$fq = array();

		// Build the search itself
//		foreach ($query->search as $search) {
//			$text = $search['text'];
//			preg_match_all('/"[^"]*"|\S+/', $text, $parts);
//
//			$fuzzy = $search['fuzzy'] ? '~' : '';
//
//			foreach ($parts[0] as $part) {
//				$fields = (isset($search['fields'])) ? $search['fields'] : array();
//				if(isset($search['boost'])) $fields = array_merge($fields, array_keys($search['boost']));
//				if ($fields) {
//					$searchq = array();
//					foreach ($fields as $field) {
//						$boost = (isset($search['boost'][$field])) ? '^' . $search['boost'][$field] : '';
//						$searchq[] = "{$field}:".$part.$fuzzy.$boost;
//					}
//					$q[] = '+('.implode(' OR ', $searchq).')';
//				}
//				else {
//					$q[] = '+'.$part.$fuzzy;
//				}
//			}
//		}

		// Filter by class if requested
		$classq = array();

		foreach ($query->classes as $class) {
			if (!empty($class['includeSubclasses'])) $classq[] = 'ClassHierarchy:'.$class['class'];
			else $classq[] = 'ClassName:'.$class['class'];
		}

		if ($classq) $fq[] = '+('.implode(' ', $classq).')';

		// Filter by filters
		foreach ($query->require as $field => $values) {
			$requireq = array();

			foreach ($values as $value) {
				if ($value === SearchQuery::$missing) {
					$requireq[] = "(*:* -{$field}:[* TO *])";
				}
				else if ($value === SearchQuery::$present) {
					$requireq[] = "{$field}:[* TO *]";
				}
				else if ($value instanceof SearchQuery_Range) {
					$start = $value->start; if ($start === null) $start = '*';
					$end = $value->end; if ($end === null) $end = '*';
					$requireq[] = "$field:[$start TO $end]";
				}
				else {
					$requireq[] = $field.':"'.$value.'"';
				}
			}

			$fq[] = '+('.implode(' ', $requireq).')';
		}

		foreach ($query->exclude as $field => $values) {
			$excludeq = array();
			$missing = false;

			foreach ($values as $value) {
				if ($value === SearchQuery::$missing) {
					$missing = true;
				}
				else if ($value === SearchQuery::$present) {
					$excludeq[] = "{$field}:[* TO *]";
				}
				else if ($value instanceof SearchQuery_Range) {
					$start = $value->start; if ($start === null) $start = '*';
					$end = $value->end; if ($end === null) $end = '*';
					$excludeq[] = "$field:[$start TO $end]";
				}
				else {
					$excludeq[] = $field.':"'.$value.'"';
				}
			}

			$fq[] = ($missing ? "+{$field}:[* TO *] " : '') . '-('.implode(' ', $excludeq).')';
		}

		if(!headers_sent()) {
			if ($q) header('X-Query: '.implode(' ', $q));
			if ($fq) header('X-Filters: "'.implode('", "', $fq).'"');
		}

		$params = array_merge($params, array('fq' => implode(' ', $fq)));

		$res = $service->search(
			implode(' ', $q),
			0,
			$limit,
			$params,
			Apache_Solr_Service::METHOD_POST
		);

		$results = new ArrayList();
		if($res->getHttpStatus() >= 200 && $res->getHttpStatus() < 300) {
			foreach ($res->response->docs as $doc) {
				$result = DataObject::get_by_id($doc->ClassName, $doc->ID);
				if($result) {
					$results->push($result);
				}
			}
			$numFound = $res->response->numFound;
		} else {
			$numFound = 0;
		}

		$ret = array();
		$ret['products'] = new PaginatedList($results);
		$ret['products']->setLimitItems(false);
		$ret['products']->setTotalItems($numFound);
		$ret['products']->setPageStart(0);
		$ret['products']->setPageLength($limit);

		// Facets (this is how we're doing suggestions for now...
		$ret['suggestions'] = array();
		if (isset($res->facet_counts->facet_fields->_autocomplete)) {
			foreach ($res->facet_counts->facet_fields->_autocomplete as $term => $count) {
				$ret['suggestions'][] = $prefix . $term;
			}
		}

		// Suggestions (requires custom setup, assumes spellcheck.collate=true)
//		if(isset($res->spellcheck->suggestions->collation)) {
//			$ret['Suggestion'] = $res->spellcheck->suggestions->collation;
//		}

		return $ret;
	}

	/**
	 * @param $facets
	 * @return array
	 */
	protected function buildFacetParams(array $facets) {
		$params = array();

		if (!empty($facets)) {
			$params['facet'] = 'true';

			foreach ($facets as $name => $spec) {
				// With our current implementation, "range" facets aren't true facets in solr terms.
	            // They're just a type of filter which can be handled elsewhere.
				// For the other types we just ignore the rest of the spec and let Solr do its thing
				if ($spec['Type'] != ShopSearch::FACET_TYPE_RANGE && isset($this->fieldMap[$name])) {
					$params['facet.field'] = $this->fieldMap[$name];
				}
			}
		}

		return $params;
	}


	/**
	 * Fulltextsearch module doesn't yet support facets very well, so I've just copied this function here so
	 * we have access to the results. I'd prefer to modify it minimally so we can eventually get rid of it
	 * once they add faceting or hooks to get directly at the returned response.
	 *
	 * @param SearchQuery $query
	 * @param integer $offset
	 * @param integer $limit
	 * @param  Array $params Extra request parameters passed through to Solr
	 * @param array $facetSpec - Added for ShopSearch so we can process the facets
	 * @return ArrayData Map with the following keys:
	 *  - 'Matches': ArrayList of the matched object instances
	 */
	public function search(SearchQuery $query, $offset = -1, $limit = -1, $params = array(), $facetSpec = array()) {
		$service = $this->getService();

		SearchVariant::with(count($query->classes) == 1 ? $query->classes[0]['class'] : null)->call('alterQuery', $query, $this);

		$q = array();
		$fq = array();

		// Build the search itself

		foreach ($query->search as $search) {
			$text = $search['text'];
			preg_match_all('/"[^"]*"|\S+/', $text, $parts);

			$fuzzy = $search['fuzzy'] ? '~' : '';

			foreach ($parts[0] as $part) {
				$fields = (isset($search['fields'])) ? $search['fields'] : array();
				if(isset($search['boost'])) $fields = array_merge($fields, array_keys($search['boost']));
				if ($fields) {
					$searchq = array();
					foreach ($fields as $field) {
						$boost = (isset($search['boost'][$field])) ? '^' . $search['boost'][$field] : '';
						$searchq[] = "{$field}:".$part.$fuzzy.$boost;
					}
					$q[] = '+('.implode(' OR ', $searchq).')';
				}
				else {
					$q[] = '+'.$part.$fuzzy;
				}
			}
		}

		// Filter by class if requested

		$classq = array();

		foreach ($query->classes as $class) {
			if (!empty($class['includeSubclasses'])) $classq[] = 'ClassHierarchy:'.$class['class'];
			else $classq[] = 'ClassName:'.$class['class'];
		}

		if ($classq) $fq[] = '+('.implode(' ', $classq).')';

		// Filter by filters

		foreach ($query->require as $field => $values) {
			$requireq = array();

			foreach ($values as $value) {
				if ($value === SearchQuery::$missing) {
					$requireq[] = "(*:* -{$field}:[* TO *])";
				}
				else if ($value === SearchQuery::$present) {
					$requireq[] = "{$field}:[* TO *]";
				}
				else if ($value instanceof SearchQuery_Range) {
					$start = $value->start; if ($start === null) $start = '*';
					$end = $value->end; if ($end === null) $end = '*';
					$requireq[] = "$field:[$start TO $end]";
				}
				else {
					$requireq[] = $field.':"'.$value.'"';
				}
			}

			$fq[] = '+('.implode(' ', $requireq).')';
		}

		foreach ($query->exclude as $field => $values) {
			$excludeq = array();
			$missing = false;

			foreach ($values as $value) {
				if ($value === SearchQuery::$missing) {
					$missing = true;
				}
				else if ($value === SearchQuery::$present) {
					$excludeq[] = "{$field}:[* TO *]";
				}
				else if ($value instanceof SearchQuery_Range) {
					$start = $value->start; if ($start === null) $start = '*';
					$end = $value->end; if ($end === null) $end = '*';
					$excludeq[] = "$field:[$start TO $end]";
				}
				else {
					$excludeq[] = $field.':"'.$value.'"';
				}
			}

			$fq[] = ($missing ? "+{$field}:[* TO *] " : '') . '-('.implode(' ', $excludeq).')';
		}

		if(!headers_sent()) {
			if ($q) header('X-Query: '.implode(' ', $q));
			if ($fq) header('X-Filters: "'.implode('", "', $fq).'"');
		}

		if ($offset == -1) $offset = $query->start;
		if ($limit == -1) $limit = $query->limit;
		if ($limit == -1) $limit = SearchQuery::$default_page_size;

		$params = array_merge($params, array('fq' => implode(' ', $fq)));

		$res = $service->search(
			$q ? implode(' ', $q) : '*:*',
			$offset,
			$limit,
			$params,
			Apache_Solr_Service::METHOD_POST
		);
		//Debug::dump($res);

		$results = new ArrayList();
		if($res->getHttpStatus() >= 200 && $res->getHttpStatus() < 300) {
			foreach ($res->response->docs as $doc) {
				$result = DataObject::get_by_id($doc->ClassName, $doc->ID);
				if($result) {
					$results->push($result);

					// Add highlighting (optional)
					$docId = $doc->_documentid;
					if($res->highlighting && $res->highlighting->$docId) {
						// TODO Create decorator class for search results rather than adding arbitrary object properties
						// TODO Allow specifying highlighted field, and lazy loading
						// in case the search API needs another query (similar to SphinxSearchable->buildExcerpt()).
						$combinedHighlights = array();
						foreach($res->highlighting->$docId as $field => $highlights) {
							$combinedHighlights = array_merge($combinedHighlights, $highlights);
						}

						// Remove entity-encoded U+FFFD replacement character. It signifies non-displayable characters,
						// and shows up as an encoding error in browsers.
						$result->Excerpt = DBField::create_field(
							'HTMLText',
							str_replace(
								'&#65533;',
								'',
								implode(' ... ', $combinedHighlights)
							)
						);
					}
				}
			}
			$numFound = $res->response->numFound;
		} else {
			$numFound = 0;
		}

		$ret = array();
		$ret['Matches'] = new PaginatedList($results);
		$ret['Matches']->setLimitItems(false);
		// Tell PaginatedList how many results there are
		$ret['Matches']->setTotalItems($numFound);
		// Results for current page start at $offset
		$ret['Matches']->setPageStart($offset);
		// Results per page
		$ret['Matches']->setPageLength($limit);

		// Facets
		//Debug::dump($res);
		if (isset($res->facet_counts->facet_fields)) {
			$ret['Facets'] = $this->buildFacetResults($res->facet_counts->facet_fields, $facetSpec);
		}

		// Suggestions (requires custom setup, assumes spellcheck.collate=true)
		if(isset($res->spellcheck->suggestions->collation)) {
			$ret['Suggestion'] = $res->spellcheck->suggestions->collation;
		}

		return new ArrayData($ret);
	}


	/**
	 * @param stdClass $facetFields
	 * @param array $facetSpec
	 * @return ArrayList
	 */
	protected function buildFacetResults($facetFields, array $facetSpec) {
		$out = new ArrayList;

		foreach ($facetSpec as $field => $facet) {
			if ($facet['Type'] == ShopSearch::FACET_TYPE_RANGE) {
				// If it's a range facet, set up the min/max
				// TODO: we could probably get the real min and max with solr's range faceting if we tried
				if (isset($facet['RangeMin'])) $facet['MinValue'] = $facet['RangeMin'];
				if (isset($facet['RangeMax'])) $facet['MaxValue'] = $facet['RangeMax'];
				$out->push(new ArrayData($facet));
			} elseif (isset($this->fieldMap[$field])) {
				// Otherwise, look through Solr's results
				$mySolrName = $this->fieldMap[$field];
				foreach ($facetFields as $solrName => $values) {
					if ($solrName == $mySolrName) {
						// we found a match, look through the values we were given
						foreach ($values as $val => $count) {
							if (!isset($facet['Values'][$val])) {
								// for link type facets we want to add anything
								// for checkboxes, if it's not in the provided list we leave it out
								if ($facet['Type'] != ShopSearch::FACET_TYPE_CHECKBOX) {
									$facet['Values'][$val] = new ArrayData(array(
										'Label'     => $val,
										'Value'     => $val,
										'Count'     => $count,
									));
								}
							} elseif ($facet['Values'][$val]) {
								$facet['Values'][$val]->Count = $count;
							}
						}
					}
				}

				// then add that to the stack
				$facet['Values'] = new ArrayList($facet['Values']);
				$out->push(new ArrayData($facet));
			}
		}

		//Debug::dump($out);
		return $out;
	}
}