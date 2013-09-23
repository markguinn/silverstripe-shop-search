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
		return new ShopSearchForm($this->owner, 'SearchForm', $this->owner->Link('search-suggest'));
	}

	/**
	 * @param array $data
	 * @return mixed
	 */
	public function results(array $data) {
		if (!isset($data['q'])) $this->httpError(400);
		$results = ShopSearch::inst()->search($data);
		$results->Title = _t('ShopSearch.SearchResults', 'Search Results');
		$results->Results = $results->Matches;
		return $this->owner->customise($results)->renderWith(array('Page_results', 'Page'));
	}

	/**
	 * @param SS_HTTPRequest $req
	 * @return string
	 */
	public function search_suggest(SS_HTTPRequest $req) {
		/** @var SS_HTTPResponse $response */
		$response = $this->owner->getResponse();
		$callback = $req->requestVar('callback');
		$results = ShopSearch::inst()->suggest($req->requestVar('term'));

		if ($callback) {
			$response->addHeader('Content-type', 'application/javascript');
			$response->setBody($callback . '(' . json_encode($results) . ');');
		} else {
			$response->addHeader('Content-type', 'application/json');
			$response->setBody(json_encode($results));
		}
		return $response;
	}
}
