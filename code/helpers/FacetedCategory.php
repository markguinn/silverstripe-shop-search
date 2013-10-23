<?php
/**
 * This extension can be applied to ProductCategory
 * to allow categories to have facets as well.
 *
 * NOTE: You could apply this to either ProductCategory
 * or ProductCategory_Controller. I tend to use the model b/c
 * that will also cover some other cases like where you
 * might list subcategory products on the parent category page.
 * In such a case those products would be filtered as well.
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 10.22.2013
 * @package shop_search
 * @subpackage helpers
 */
class FacetedCategory extends SiteTreeExtension
{
	private static $db = array(
		'DisabledFacets' => 'Text', // This will be a comma-delimited list of facets that aren't used for a given category
	);

	/** @var array - facet definition - see ShopSearch and/or docs/en/Facets.md for format */
	private static $facets = array();

	/** @var bool - if true there will be a tab in the cms to disable some or all defined facets */
	private static $show_disabled_facets_tab = true;


	/**
	 * @param FieldList $fields
	 */
	public function updateCMSFields(FieldList $fields) {
		if (Config::inst()->get('FacetedCategory', 'show_disabled_facets_tab')) {
			$spec = FacetHelper::inst()->expandFacetSpec( $this->getFacetSpec() );
			$facets = array();
			foreach ($spec as $f => $v) $facets[$f] = $v['Label'];
			$fields->addFieldToTab('Root.Facets', new CheckboxSetField('DisabledFacets', "Don't show the following facets for this category:", $facets));
		}
	}


	/**
	 * @return Controller
	 */
	protected function getController() {
		return ($this->owner instanceof Controller) ? $this->owner : Controller::curr();
	}


	/**
	 * @return array
	 */
	protected function getFacetSpec() {
		$spec = Config::inst()->get('FacetedCategory', 'facets');
		return (empty($spec) || !is_array($spec)) ? array() : $spec;
	}


	/**
	 * @return array
	 */
	protected function getFilters() {
		$qs_f       = Config::inst()->get('ShopSearch', 'qs_filters');
		if (!$qs_f) return array();
		$request    = $this->getController()->getRequest();
		$filters    = $request->requestVar($qs_f);
		if (empty($filters) || !is_array($filters)) return array();
		return $filters;
	}


	/**
	 * @param bool $recursive
	 * @return mixed
	 */
	public function FilteredProducts($recursive=true) {
		if (!isset($this->_filteredProducts)) {
			$this->_filteredProducts = $this->owner->ProductsShowable($recursive);
			$this->_filteredProducts = FacetHelper::inst()->addFiltersToDataList($this->_filteredProducts, $this->getFilters());
		}

		return $this->_filteredProducts;
	}

	protected $_filteredProducts;


	/**
	 * @return array
	 */
	public function getDisabledFacetsArray() {
		if (empty($this->owner->DisabledFacets)) return array();
		return explode(',', $this->owner->DisabledFacets);
	}


	/**
	 * @return ArrayList
	 */
	public function Facets() {
		$spec       = $this->getFacetSpec();
		if (empty($spec)) return new ArrayList;

		// remove any disabled facets
		foreach ($this->getDisabledFacetsArray() as $disabled) {
			if (isset($spec[$disabled])) unset($spec[$disabled]);
		}

		$request    = $this->getController()->getRequest();
		$baseLink   = $request->getURL(false);
		$filters    = $this->getFilters();
		$baseParams = array_merge($request->requestVars(), array());
		unset($baseParams['url']);

		$facets     = FacetHelper::inst()->buildFacets($this->FilteredProducts(), $spec);
		$facets     = FacetHelper::inst()->updateFacetState($facets, $filters);
		$facets     = FacetHelper::inst()->insertFacetLinks($facets, $baseParams, $baseLink);

		return $facets;
	}
}