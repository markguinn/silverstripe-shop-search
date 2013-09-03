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
	private static $allowed_actions = array('SearchForm', 'results');

	/**
	 * TODO: this should probably return a ShopSearchForm class instead of creating here
	 * @return Form
	 */
	public function SearchForm() {
		$f = new Form($this->owner, 'SearchForm',
			new FieldList(array(
				TextField::create('q', '')->setAttribute('placeholder', _t('ShopSearch.SEARCH', 'Search'))
			)),
			new FieldList(array(
				FormAction::create('results', _t('ShopSearch.GO', 'Go'))
			))
		);

		$f->setFormMethod('GET');
		$f->disableSecurityToken();

		return $f;
	}

	/**
	 * @param array          $data
	 * @param Form           $form
	 * @param SS_HTTPRequest $req
	 * @return mixed
	 */
	public function results(array $data, Form $form, SS_HTTPRequest $req) {
		if (!isset($data['q'])) $this->httpError(400);
		$adapter = singleton(Config::inst()->get('ShopSearch', 'adapter_class'));
		$results = $adapter->searchFromVars($data);
		$results->Query = $data['q'];
		$results->Title = _t('ShopSearch.SearchResults', 'Search Results');
		$results->Results = $results->Matches;
		return $this->owner->customise($results)->renderWith(array('Page_results', 'Page'));
	}
}
