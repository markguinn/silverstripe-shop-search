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
	private static $adapter_class = 'ShopSearchSimple';

	/** @var array - these classes will be added to the index - e.g. Category, Page, etc. */
	private static $searchable = array();

	/** @var bool - if true, all buyable models will be added to the index automatically  */
	private static $buyables_are_searchable = true;

	/** @var int - size of paging in the search */
	private static $page_size = 10;

	/** @var bool */
	private static $suggest_enabled = true;

	/** @var int - how many suggestions to provide */
	private static $suggest_limit = 5;

	/** @var bool */
	private static $search_as_you_type_enabled = true;

	/** @var int - how may sayt (search-as-you-type) entries to provide */
	private static $sayt_limit = 5;

	/** @var bool - automatically create facets for static attributes */
	private static $auto_facet_attributes = false;

	/** @var string - optionally, a different template to run ajax results through (sans-Page.ss) */
	private static $ajax_results_template = '';

	/** @var string - these allow you to use different querystring params in you need to */
	private static $qs_query         = 'q';
	private static $qs_filters       = 'f';
	private static $qs_parent_search = '__ps';
	private static $qs_title         = '__t';
	private static $qs_source        = '__src'; // used to log searches from search-as-you-type
	private static $qs_sort          = 'sort';

	/** @var array - I'm leaving this particularly bare b/c with config merging it's a pain to remove items */
	private static $sort_options = array(
		'score desc'            => 'Relevance',
//		'SiteTree_Title asc'    => 'Alphabetical (A-Z)',
//		'SiteTree_Title dsc'    => 'Alphabetical (Z-A)',
	);

	/**
	 * @var array - default search facets (price, category, etc)
	 *   Key    field name - e.g. Price - can be a VirtualFieldIndex field
	 *   Value  facet label - e.g. Search By Category - if the value is a relation or returns an array or
	 *          list all values will be faceted individually
	 *          NOTE: this can also be another array with keys: Label, Type, and Values (for checkbox only)
	 */
	private static $facets = array();

	/** @var array - field definition for Solr only */
	private static $solr_fulltext_fields = array();

	/** @var array - field definition for Solr only */
	private static $solr_filter_fields = array();

	/** @var string - if present, will create a copy of SiteTree_Title that's suited for alpha sorting */
	private static $solr_title_sort_field = '';

	/**
	 * @var string - If present, everything matching the following regex will be removed from
	 *               keyword search queries before passing to the search adapter.
	 */
	private static $keyword_filter_regex = '/[^a-zA-Z0-9\s\-]/';


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
	 * @param int $maxDepth [optional]
	 * @return array
	 * @static
	 */
	public static function get_category_hierarchy($parentID = 0, $prefix = '', $maxDepth = 999) {
		$out = array();
		$cats = ProductCategory::get()
			->filter(array(
				'ParentID'      => $parentID,
				'ShowInMenus'   => 1,
			))
			->sort('Sort');

		// If there is a single parent category (usually "Products" or something), we
		// probably don't want that in the hierarchy.
		if ($parentID == 0 && $cats->count() == 1) {
			return self::get_category_hierarchy($cats->first()->ID, $prefix, $maxDepth);
		}

		foreach ($cats as $cat) {
			$out[$cat->ID] = $prefix . $cat->Title;
			if ($maxDepth > 1) {
				$out += self::get_category_hierarchy($cat->ID, $prefix . $cat->Title . ' > ', $maxDepth - 1);
			}
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
	 * @param bool $useFacets [optional]
	 * @param int $start [optional]
	 * @param int $limit [optional]
	 * @return ArrayData
	 */
	public function search(array $vars, $logSearch=true, $useFacets=true, $start=-1, $limit=-1) {
		$qs_q   = $this->config()->get('qs_query');
		$qs_f   = $this->config()->get('qs_filters');
		$qs_ps  = $this->config()->get('qs_parent_search');
		$qs_t   = $this->config()->get('qs_title');
		$qs_sort= $this->config()->get('qs_sort');
		if ($limit < 0) $limit  = $this->config()->get('page_size');
		if ($start < 0) $start  = !empty($vars['start']) ? (int)$vars['start'] : 0; // as far as i can see, fulltextsearch hard codes 'start'
		$facets = $useFacets ? $this->config()->get('facets') : array();
		if (!is_array($facets)) $facets = array();
		if (empty($limit)) $limit = -1;

		// figure out and scrub the sort
		$sortOptions = $this->config()->get('sort_options');
		$sort        = !empty($vars[$qs_sort]) ? $vars[$qs_sort] : '';
		if (!isset($sortOptions[$sort])) {
			$sort    = current(array_keys($sortOptions));
		}

		// figure out and scrub the filters
		$filters  = !empty($vars[$qs_f]) ? FacetHelper::inst()->scrubFilters($vars[$qs_f]) : array();

		// do the search
		$keywords = !empty($vars[$qs_q]) ? $vars[$qs_q] : '';
		if ($keywordRegex = $this->config()->get('keyword_filter_regex')) {
			$keywords = preg_replace($keywordRegex, '', $keywords);
		}
		$results  = self::adapter()->searchFromVars($keywords, $filters, $facets, $start, $limit, $sort);

		// massage the results a bit
		if (!empty($keywords) && !$results->hasValue('Query')) $results->Query = $keywords;
		if (!empty($filters) && !$results->hasValue('Filters')) $results->Filters = new ArrayData($filters);
		if (!$results->hasValue('Sort')) $results->Sort = $sort;
		if (!$results->hasValue('TotalMatches')) {
			$results->TotalMatches = $results->Matches->hasMethod('getTotalItems')
				? $results->Matches->getTotalItems()
				: $results->Matches->count();
		}

		// for some types of facets, update the state
		if ($results->hasValue('Facets')) {
			FacetHelper::inst()->transformHierarchies($results->Facets);
			FacetHelper::inst()->updateFacetState($results->Facets, $filters);
		}

		// make a hash of the search so we can know if we've already logged it this session
		$loggedFilters = !empty($filters) ? json_encode($filters) : null;
		$loggedQuery   = strtolower($results->Query);
//		$searchHash    = md5($loggedFilters . $loggedQuery);
//		$sessSearches  = Session::get('loggedSearches');
//		if (!is_array($sessSearches)) $sessSearches = array();
//		Debug::dump($searchHash, $sessSearches);

		// save the log record
		if ($start == 0 && $logSearch && (!empty($keywords) || !empty($filters))) { // && !in_array($searchHash, $sessSearches)) {
			$log = new SearchLog(array(
				'Query'         => $loggedQuery,
				'Title'         => !empty($vars[$qs_t]) ? $vars[$qs_t] : '',
				'Link'          => Controller::curr()->getRequest()->getURL(true), // I'm not 100% happy with this, but can't think of a better way
				'NumResults'    => $results->TotalMatches,
				'MemberID'      => Member::currentUserID(),
				'Filters'       => $loggedFilters,
				'ParentSearchID'=> !empty($vars[$qs_ps]) ? $vars[$qs_ps] : 0,
			));
			$log->write();
			$results->SearchLogID = $log->ID;
			$results->SearchBreadcrumbs = $log->getBreadcrumbs();

//			$sessSearches[] = $searchHash;
//			Session::set('loggedSearches', $sessSearches);
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
	 * @param string $str
	 * @return array
	 */
	public function suggest($str='') {
		$adapter = self::adapter();
		if ($adapter->hasMethod('suggest')) {
			return $adapter->suggest($str);
		} else {
			return $this->getSuggestQuery($str)->execute()->column('Query');
		}
	}


	/**
	 * Returns an array that can be made into json and passed to the controller
	 * containing both term suggestions and a few product matches.
	 *
	 * @param array $searchVars
	 * @return array
	 */
	public function suggestWithResults(array $searchVars) {
		$qs_q       = $this->config()->get('qs_query');
		$qs_f       = $this->config()->get('qs_filters');
		$keywords   = !empty($searchVars[$qs_q]) ? $searchVars[$qs_q] : '';
		$filters    = !empty($searchVars[$qs_f]) ? $searchVars[$qs_f] : array();

		$adapter = self::adapter();

		// get suggestions and product list from the adapter
		if ($adapter->hasMethod('suggestWithResults')) {
			$results = $adapter->suggestWithResults($keywords, $filters);
		} else {
			$limit      = (int)ShopSearch::config()->sayt_limit;
			$search     = self::adapter()->searchFromVars($keywords, $filters, array(), 0, $limit, 'Popularity DESC');
			//$search     = ShopSearch::inst()->search($searchVars, false, false, 0, $limit);

			$results = array(
				'products'      => $search->Matches,
				'suggestions'   => $this->suggest($keywords),
			);
		}

		// the adapter just gave us a list of products, which we need to process a little further
		if (!empty($results['products'])) {
			// this gets encoded into the product links
			$searchVars['total'] = $results['products']->hasMethod('getTotalItems')
				? $results['products']->getTotalItems()
				: $results['products']->count();

			$products   = array();
			foreach ($results['products'] as $prod) {
				if (!$prod || !$prod->exists()) continue;
				$img = $prod->hasMethod('ProductImage') ? $prod->ProductImage() : $prod->Image();
				$thumb = ($img && $img->exists()) ? $img->getThumbnail() : null;

				$json = array(
					'link'  => $prod->Link() . '?' . ShopSearch::config()->qs_source . '=' . urlencode(base64_encode(json_encode($searchVars))),
					'title' => $prod->Title,
					'desc'  => $prod->obj('Content')->Summary(),
					'thumb' => $thumb ? $thumb->Link() : '',
					'price' => $prod->getPrice()->Nice(),
				);

				if ($prod->hasExtension('HasPromotionalPricing') && $prod->hasValidPromotion()) {
					$json['original_price'] = $prod->getOriginalPrice()->Nice();
				}

				$products[] = $json;
			}

			// replace the list of product objects with json
			$results['products'] = $products;
		}

		return $results;
	}
}
