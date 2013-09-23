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
	function __construct($controller, $method, $suggestURL = '') {
		$searchField = new TextField('q', '');
		$searchField->setAttribute('placeholder', _t('ShopSearch.SEARCH', 'Search'));
		if ($suggestURL) $searchField->setAttribute('data-suggest-url', $suggestURL);

		parent::__construct($controller, $method,
			new FieldList(array(
				$searchField,
			)),
			new FieldList(array(
				FormAction::create('results', _t('ShopSearch.GO', 'Go'))
			))
		);

		$this->setFormMethod('GET');
		$this->disableSecurityToken();

		if (Config::inst()->get('ShopSearch', 'suggest_enabled')) {
			Requirements::javascript(THIRDPARTY_DIR . '/jquery-ui/jquery-ui.js');
			Requirements::css(THIRDPARTY_DIR . '/jquery-ui-themes/smoothness/jquery-ui.css');
			Requirements::javascript(SHOP_SEARCH_FOLDER . '/javascript/ShopSearch.js');
		}
	}
}
