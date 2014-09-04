<?php
/**
 * Adds SearchForm and results methods to the controller.
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 08.29.2013
 * @package shop_search
 */
class ShopSearchControllerExtension extends Extension
{
	private static $allowed_actions = array('SearchForm', 'results', 'search_suggest');

	/**
	 * @return ShopSearchForm
	 */
	public function SearchForm() {
		return new ShopSearchForm($this->owner, 'SearchForm', $this->owner->Link() . 'search-suggest');
	}


	/**
	 * @param array $data
	 * @return mixed
	 */
	public function results(array $data) {
		// do the search
		$results  = ShopSearch::inst()->search($data);
		$baseLink = $this->owner->getRequest()->getURL(false);

		// add links for any facets
		if ($results->Facets && $results->Facets->count()) {
			$qs_ps      = Config::inst()->get('ShopSearch', 'qs_parent_search');
			$baseParams = array_merge($data, array($qs_ps => $results->SearchLogID));
			unset($baseParams['url']);
			$results->Facets = FacetHelper::inst()->insertFacetLinks($results->Facets, $baseParams, $baseLink);
		}

		// add a dropdown for sorting
		$qs_sort    = Config::inst()->get('ShopSearch', 'qs_sort');
		$options    = Config::inst()->get('ShopSearch', 'sort_options');
		$sortParams = array_merge($data, array($qs_sort => 'NEWSORTVALUE'));
		unset($sortParams['url']);
		$results->SortControl = DropdownField::create($qs_sort, ShopSearch::config()->sort_label, $options, $results->Sort)
			->setAttribute('data-url', $baseLink . '?' . http_build_query($sortParams));

		// a little more output management
		$results->Title = "Search Results";

		// Give a hook for the parent controller to format the results, for example,
		// interpreting filters in a specific way to affect the title or content
		// when no results are returned. Since this is domain-specific we just leave
		// it up to the host app.
		if ($this->owner->hasMethod('onBeforeSearchDisplay')) {
			$this->owner->onBeforeSearchDisplay($results);
		}

		// give a hook for processing ajax requests through a different template (i.e. for returning only fragments)
		$tpl = Config::inst()->get('ShopSearch', 'ajax_results_template');
		if (!empty($tpl) && Director::is_ajax()) return $this->owner->customise($results)->renderWith($tpl);

		$this->owner->extend('updateSearchResultsResponse', $this->owner->request, $response, $results, $data);
		return $response ? $response : $this->owner->customise($results)->renderWith(array('ShopSearch_results', 'Page_results', 'Page'));
	}


	/**
	 * @param SS_HTTPRequest $req
	 * @return string
	 */
	public function search_suggest(SS_HTTPRequest $req) {
		/** @var SS_HTTPResponse $response */
		$response    = $this->owner->getResponse();
		$callback    = $req->requestVar('callback');

		// convert the search results into usable json for search-as-you-type
		if (ShopSearch::config()->search_as_you_type_enabled) {
			$searchVars = $req->requestVars();
			$searchVars[ ShopSearch::config()->qs_query ] = $searchVars['term'];
			unset($searchVars['term']);
			$results = ShopSearch::inst()->suggestWithResults($searchVars);
		} else {
			$results = array(
				'suggestions'   => ShopSearch::inst()->suggest($req->requestVar('term')),
			);
		}

		if ($callback) {
			$response->addHeader('Content-type', 'application/javascript');
			$response->setBody($callback . '(' . json_encode($results) . ');');
		} else {
			$response->addHeader('Content-type', 'application/json');
			$response->setBody(json_encode($results));
		}
		return $response;
	}


	/**
	 * If there is a search encoded in the link, go ahead and log it.
	 * This happens when you click through on a search suggestion
	 */
	public function onAfterInit() {
		$req = $this->owner->getRequest();
		$src = $req->requestVar( Config::inst()->get('ShopSearch', 'qs_source') );
		if ($src) {
			$qs_q   = Config::inst()->get('ShopSearch', 'qs_query');
			$qs_f   = Config::inst()->get('ShopSearch', 'qs_filters');
			$vars   = json_decode(base64_decode($src), true);

			// log the search
			$log = new SearchLog(array(
				'Query'         => strtolower($vars[$qs_q]),
				'Link'          => $req->getURL(false), // These searches will never have child searches, but this will allow us to know what they clicked
				'NumResults'    => $vars['total'],
				'MemberID'      => Member::currentUserID(),
				'Filters'       => !empty($vars[$qs_f]) ? json_encode($vars[$qs_f]) : null,
			));
			$log->write();

			// redirect to the clean page
			$this->owner->redirect($req->getURL(false));
		}
	}


	/**
	 * @param ArrayData $results
	 * @param array     $data
	 * @return string
	 */
	protected function generateLongTitle(ArrayData $results, array $data) {

	}

}
