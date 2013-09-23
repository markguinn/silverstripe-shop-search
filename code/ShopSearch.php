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
	/** @var string - class name of adapter class to use */
	private static $adapter_class = 'ShopSearchMysqlSimple';

	/** @var array - these classes will be added to the index */
	private static $searchable = array('ProductCategory');

	/** @var bool - if true, all buyable models will be added to the index automatically  */
	private static $buyables_are_searchable = true;

	/** @var int - how many suggestions to provide */
	private static $suggest_limit = 10;

	/** @var bool */
	private static $suggest_enabled = true;

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
		// do the search
		$results = self::adapter()->searchFromVars($vars);

		// massage the results a bit
		if (!empty($vars['q']) && !$results->hasValue('Query')) $results->Query = $vars['q'];
		if (!$results->hasValue('TotalMatches')) $results->TotalMatches = $results->Matches->count();
		// TODO: filters
		// TODO: Paging
		// TODO: don't log multiple times for paging

		// save the log record
		if ($logSearch && $results->Query) {
			$log = new SearchLog(array(
				'Query'         => strtolower($results->Query),
				'NumResults'    => $results->TotalMatches,
				'MemberID'      => Member::currentUserID(),
				// TODO: filters
			));
			$log->write();
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
		return $this->getSuggestQuery($str)->execute()->column('Query');
	}
}
