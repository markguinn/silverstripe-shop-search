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
		$results = ShopSearch::inst()->search($data);

		// add links for any facets
		if ($results->Facets && $results->Facets->count()) {
			$qs_ps      = Config::inst()->get('ShopSearch', 'qs_parent_search');
			$baseLink   = $this->owner->getRequest()->getURL(false);
			$baseParams = array_merge($data, array($qs_ps => $results->SearchLogID));
			unset($baseParams['url']);
			$results->Facets = FacetHelper::inst()->insertFacetLinks($results->Facets, $baseParams, $baseLink);
		}

		// a little more output management
		$results->Title   = _t('ShopSearch.SearchResults', 'Search Results');
		$results->Results = $results->Matches;
		return $this->owner->customise($results)->renderWith(array('Page_results', 'Page'));
	}


	/**
	 * @param SS_HTTPRequest $req
	 * @return string
	 */
	public function search_suggest(SS_HTTPRequest $req) {
		/** @var SS_HTTPResponse $response */
		$response    = $this->owner->getResponse();
		$callback    = $req->requestVar('callback');
		$suggestions = ShopSearch::inst()->suggest($req->requestVar('term'));

		// convert the search results into usable json for search-as-you-type
		$products    = array();
		if (Config::inst()->get('ShopSearch', 'search_as_you_type_enabled')) {
			$searchVars = $req->requestVars();
			$searchVars[ Config::inst()->get('ShopSearch', 'qs_query') ] = $searchVars['term'];
			unset($searchVars['term']);
			$search     = ShopSearch::inst()->search($searchVars, false);
			$prodList   = $search->Matches->limit(5);
			$searchVars['total'] = $search->TotalMatches; // this gets encoded into the product links

			foreach ($prodList as $prod) {
				$img  = $img = $prod->Image();

				$json = array(
					'link'  => $prod->Link() . '?' . Config::inst()->get('ShopSearch', 'qs_source') . '=' . urlencode(base64_encode(json_encode($searchVars))),
					'title' => $prod->Title,
					'desc'  => $prod->obj('Content')->Summary(),
					'thumb' => ($img && $img->exists()) ? $img->getThumbnail()->Link() : '',
					'price' => $prod->getPrice()->Nice(),
				);

				if ($prod->hasExtension('HasPromotionalPricing') && $prod->hasValidPromotion()) {
					$json['original_price'] = $prod->getOriginalPrice()->Nice();
				}

				$products[] = $json;
			}
		}

		$results     = array(
			'suggestions'   => $suggestions,
			'products'      => $products,
		);

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
	 * If there is a
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
}
