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
		$qs_q   = Config::inst()->get('ShopSearch', 'qs_query');
		$qs_f   = Config::inst()->get('ShopSearch', 'qs_filters');
		$qs_ps  = Config::inst()->get('ShopSearch', 'qs_parent_search');
		if (!isset($data[$qs_q])) $this->owner->httpError(400);

		// do the search
		$results = ShopSearch::inst()->search($data);

		// add links for any facets
		if ($results->Facets && $results->Facets->count()) {
			$baseLink = $this->owner->getRequest()->getURL(false);
			foreach ($results->Facets as $facet) {
				foreach ($facet->Values as $value) {
					// TODO: Handle more than on on the same facet with an array
					$params = $data + array(
						$qs_f . '[' . $facet->getField('Field') . ']'  => $value->getField('Value'),
						$qs_ps                                         => $results->SearchLogID,
					);
					unset($params['url']);
					$value->Link = $baseLink . '?' . http_build_query($params);
				}
			}
		}

		// a little more output management
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
