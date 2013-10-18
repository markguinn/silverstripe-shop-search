<?php
/**
 * Fulltext search index for shop buyables
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 08.29.2013
 * @package shop_search
 */
class ShopSearch extends Object
{
	const FACET_TYPE_LINK       = 'link';
	const FACET_TYPE_CHECKBOX   = 'checkbox';
	const FACET_TYPE_RANGE      = 'range';

	/** @var string - class name of adapter class to use */
	private static $adapter_class = 'ShopSearchMysqlSimple';

	/** @var array - these classes will be added to the index - e.g. Category, Page, etc. */
	private static $searchable = array();

	/** @var bool - if true, all buyable models will be added to the index automatically  */
	private static $buyables_are_searchable = true;

	/** @var int - how many suggestions to provide */
	private static $suggest_limit = 10;

	/** @var bool */
	private static $suggest_enabled = true;

	/** @var bool */
	private static $search_as_you_type_enabled = true;

	/** @var string - these allow you to use different querystring params in you need to */
	private static $qs_query    = 'q';
	private static $qs_filters  = 'f';
	private static $qs_parent_search = '__ps';
	private static $qs_title    = '__t';
	private static $qs_source   = '__src'; // used to log searches from search-as-you-type

	/**
	 * @var array - default search facets (price, category, etc)
	 *   Key    field name - e.g. Price - can be a VirtualFieldIndex field
	 *   Value  facet label - e.g. Search By Category - if the value is a relation or returns an array or
	 *          list all values will be faceted individually
	 *          NOTE: this can also be another array with keys: Label, Type, and Values (for checkbox only)
	 */
	private static $facets = array();


	/**
	 * @return array
	 */
	public static function get_searchable_classes() {
		// First get any explicitly declared searchable classes
		$searchable = Config::inst()->get('ShopSearch', 'searchable');
		if (is_string($searchable) && strlen($searchable) > 0) {
			$searchable = array($searchable);
		} elseif (!is_array($searchable)) {
			$searchable = array();
		}

		// Add in buyables automatically if asked
		if (Config::inst()->get('ShopSearch', 'buyables_are_searchable')) {
			$buyables = SS_ClassLoader::instance()->getManifest()->getImplementorsOf('Buyable');
			if (is_array($buyables) && count($buyables) > 0) {
				foreach ($buyables as $c) {
					$searchable[] = $c;
				}
			}
		}

		return array_unique($searchable);
	}

	/**
	 * Returns an array of categories suitable for a dropdown menu
	 * TODO: cache this
	 *
	 * @param int $parentID [optional]
	 * @param string $prefix [optional]
	 * @return array
	 * @static
	 */
	public static function get_category_hierarchy($parentID = 0, $prefix = '') {
		$out = array();
		$cats = ProductCategory::get()->filter('ParentID', $parentID)->sort('Sort');

		foreach ($cats as $cat) {
			$out[$cat->ID] = $prefix . $cat->Title;
			$out += self::get_category_hierarchy($cat->ID, $prefix . $cat->Title . ' > ');
		}

		return $out;
	}

	/**
	 * @return ShopSearchAdapter
	 */
	public static function adapter() {
		$adapterClass = Config::inst()->get('ShopSearch', 'adapter_class');
		return Injector::inst()->get($adapterClass);
	}

	/**
	 * @return ShopSearch
	 */
	public static function inst() {
		return Injector::inst()->get('ShopSearch');
	}

	/**
	 * The result will contain at least the following:
	 *      Matches - SS_List of results
	 *      TotalMatches - total # of results, unlimited
	 *      Query - query string
	 * Also saves a log record.
	 *
	 * @param array $vars
	 * @param bool $logSearch [optional]
	 * @return ArrayData
	 */
	public function search(array $vars, $logSearch=true) {
		$qs_q   = $this->config()->get('qs_query');
		$qs_f   = $this->config()->get('qs_filters');
		$qs_ps  = $this->config()->get('qs_parent_search');
		$qs_t   = $this->config()->get('qs_title');
		$facets = $this->config()->get('facets');
		if (!is_array($facets)) $facets = array();

		// do the search
		$keywords = !empty($vars[$qs_q]) ? $vars[$qs_q] : '';
		$filters  = !empty($vars[$qs_f]) ? $vars[$qs_f] : array();
		foreach ($filters as $k => $v) if (empty($v)) unset($filters[$k]);
		$results  = self::adapter()->searchFromVars($keywords, $filters, $facets);

		// massage the results a bit
		if (!empty($keywords) && !$results->hasValue('Query')) $results->Query = $keywords;
		if (!empty($filters) && !$results->hasValue('Filters')) $results->Filters = new ArrayData($filters);
		if (!$results->hasValue('TotalMatches')) $results->TotalMatches = $results->Matches->count();

		// for some types of facets, update the state
		if ($results->hasValue('Facets')) {
			foreach ($results->Facets as &$facet) {
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
		}

		// TODO: Paging
		// TODO: don't log multiple times for paging

		// save the log record
		if ($logSearch && (!empty($keywords) || !empty($filters))) {
			$log = new SearchLog(array(
				'Query'         => strtolower($results->Query),
				'Title'         => !empty($vars[$qs_t]) ? $vars[$qs_t] : '',
				'Link'          => Controller::curr()->getRequest()->getURL(true), // I'm not 100% happy with this, but can't think of a better way
				'NumResults'    => $results->TotalMatches,
				'MemberID'      => Member::currentUserID(),
				'Filters'       => !empty($filters) ? json_encode($filters) : null,
				'ParentSearchID'=> !empty($vars[$qs_ps]) ? $vars[$qs_ps] : 0,
			));
			$log->write();
			$results->SearchLogID = $log->ID;
			$results->SearchBreadcrumbs = $log->getBreadcrumbs();
		}

		return $results;
	}

	/**
	 * @param string $str
	 * @return SS_Query
	 */
	public function getSuggestQuery($str='') {
		$q = new SQLQuery();
		$q = $q->setSelect('"SearchLog"."Query"')
			// TODO: what to do with filter?
			->selectField('count(distinct "SearchLog"."ID")', 'SearchCount')
			->selectField('max("SearchLog"."Created")', 'LastSearch')
			->selectField('max("SearchLog"."NumResults")', 'NumResults')
			->setFrom('"SearchLog"')
			->setGroupBy('"SearchLog"."Query"')
			->setOrderBy(array('HasResults DESC', 'SearchCount DESC'))
			->setLimit(Config::inst()->get('ShopSearch', 'suggest_limit'))
		;

		if (DB::getConn() instanceof MySQLDatabase) {
			$q = $q->selectField('if(max("SearchLog"."NumResults") > 0, 1, 0)', 'HasResults');
		} else {
			// sqlite3 - should give 1 if there are any results and 0 otherwise
			$q = $q->selectField('min(1, max("SearchLog"."NumResults"))', 'HasResults');
		}

		if (strlen($str) > 0) {
			$q = $q->addWhere(sprintf('"SearchLog"."Query" LIKE \'%%%s%%\'', Convert::raw2sql($str)));
		}

		return $q;
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
	 * @param string $str
	 * @return array
	 */
	public function suggest($str='') {
		return $this->getSuggestQuery($str)->execute()->column('Query');
	}
}
