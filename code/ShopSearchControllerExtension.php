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
