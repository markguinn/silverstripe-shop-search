<?php
/**
 * Form object for shop search.
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 09.23.2013
 * @package shop_search
 */
class ShopSearchForm extends Form
{
	/** @var bool - setting this to true will remove the category dropdwon from the form */
	private static $disable_category_dropdown = false;

	/** @var string - this probably ought to be overridden with a vfi or solr facet unless you're products are only in one category */
	private static $category_field = 'f[ParentID]';

	/** @var string - setting to 'NONE' will mean the category dropdwon will have no empty option */
	private static $category_empty_string = 'All Products';

	/** @var int - how deep to list the categories for the dropdown */
	private static $category_max_depth = 10;


	/**
	 * @param Controller $controller
	 * @param String     $method
	 * @param string     $suggestURL
	 */
	function __construct($controller, $method, $suggestURL = '') {
		$searchField = TextField::create('q', '');
		$searchField->setAttribute('placeholder', _t('ShopSearch.SEARCH', 'Search'));
		if ($suggestURL) $searchField->setAttribute('data-suggest-url', $suggestURL);

		$fields = FieldList::create($searchField);
		if (!self::config()->disable_category_dropdown) {
			$cats     = ShopSearch::get_category_hierarchy(0, '', self::config()->category_max_depth);
			$catField = DropdownField::create(self::get_category_field(), '', $cats, Session::get('LastSearchCatID'));

			$emptyString = self::config()->category_empty_string;
			if ($emptyString !== 'NONE') {
				$catField->setEmptyString(_t('ShopSearch.'.$emptyString, $emptyString));
			}

			$fields->push($catField);
		}

		parent::__construct($controller, $method, $fields, FieldList::create(array(FormAction::create('results', _t('ShopSearch.GO', 'Go')))));

		$this->setFormMethod('GET');
		$this->disableSecurityToken();
		if ($c = self::config()->css_classes) $this->addExtraClass($c);

		Requirements::css(SHOP_SEARCH_FOLDER . '/css/ShopSearch.css');

		if (Config::inst()->get('ShopSearch', 'suggest_enabled')) {
			Requirements::javascript(THIRDPARTY_DIR . '/jquery-ui/jquery-ui.js');
			Requirements::css(THIRDPARTY_DIR . '/jquery-ui-themes/smoothness/jquery-ui.css');
			Requirements::javascript(SHOP_SEARCH_FOLDER . '/javascript/search.suggest.js');
			Requirements::javascript(SHOP_SEARCH_FOLDER . '/javascript/search.js');
		}
	}


	/**
	 * @return string
	 */
	public static function get_category_field() {
		return Config::inst()->get('ShopSearchForm', 'category_field');
	}


	/**
	 * @param array $data
	 * @return mixed
	 */
	public function results(array $data) {
		// do the search
		$results  = ShopSearch::inst()->search($data);
		$request  = $this->controller->getRequest();
		$baseLink = $request->getURL(false);

		// if there was only one category filter, remember it for the category dropdown to retain it's value
		if (!ShopSearchForm::config()->disable_category_dropdown) {
			$qs_filters  = (string)Config::inst()->get('ShopSearch', 'qs_filters');
			$categoryKey = (string)ShopSearchForm::config()->category_field;

			if (preg_match('/\[(.+)\]/', $categoryKey, $matches)) {
				// get right of the f[] around the actual key if present
				$categoryKey = $matches[1];
			}

			if (!empty($data[$qs_filters][$categoryKey])) {
				$categoryID = $data[$qs_filters][$categoryKey];
				if (is_numeric($categoryID)) {
					// If it's set in the dropdown it will just be a number
					// If it's set from the checkboxes it will be something like LIST~1,2,3,4
					// We only want to remember the value in the former case
					Session::set('LastSearchCatID', $categoryID);
				}
			} else {
				// If they unchecked every value, then clear the dropdown as well
				Session::clear('LastSearchCatID');
			}
		}

		// add links for any facets
		if ($results->Facets && $results->Facets->count()) {
			$qs_ps      = (string)Config::inst()->get('ShopSearch', 'qs_parent_search');
			$baseParams = array_merge($data, array($qs_ps => $results->SearchLogID));
			unset($baseParams['url']);
			$results->Facets = FacetHelper::inst()->insertFacetLinks($results->Facets, $baseParams, $baseLink);
		}

		// add a dropdown for sorting
		$qs_sort    = (string)Config::inst()->get('ShopSearch', 'qs_sort');
		$options    = Config::inst()->get('ShopSearch', 'sort_options');
		$sortParams = array_merge($data, array($qs_sort => 'NEWSORTVALUE'));
		unset($sortParams['url']);
		$results->SortControl = DropdownField::create($qs_sort, ShopSearch::config()->sort_label, $options, $results->Sort)
			->setAttribute('data-url', $baseLink . '?' . http_build_query($sortParams));

		// a little more output management
		$results->Title = "Search Results";
		$results->Results = $results->Matches; // this makes us compatible with the default search template

		// Give a hook for the parent controller to format the results, for example,
		// interpreting filters in a specific way to affect the title or content
		// when no results are returned. Since this is domain-specific we just leave
		// it up to the host app.
		if ($this->controller->hasMethod('onBeforeSearchDisplay')) {
			$this->controller->onBeforeSearchDisplay($results);
		}

		// give a hook for processing ajax requests through a different template (i.e. for returning only fragments)
		$tpl = Config::inst()->get('ShopSearch', 'ajax_results_template');
		if (!empty($tpl) && Director::is_ajax()) {
			return $this->controller->customise($results)->renderWith($tpl);
		}

		// Give a hook for modifying the search responses
		$this->controller->extend('updateSearchResultsResponse', $request, $response, $results, $data);

		return $response ?: $this->controller->customise($results)->renderWith(array('ShopSearch_results', 'Page_results', 'Page'));
	}

}
