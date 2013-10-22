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
class FacetedCategory extends Extension
{
	/** @var array - facet definition - see ShopSearch and/or docs/en/Facets.md for format */
	private $facets = array();


	/**
	 * @return Controller
	 */
	protected function getController() {
		return ($this->owner instanceof Controller) ? $this->owner : Controller::curr();
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
	 * @return ArrayList
	 */
	public function Facets() {
		$request    = $this->getController()->getRequest();
		$baseLink   = $request->getURL(false);
		$filters    = $this->getFilters();
		$baseParams = array_merge($request->requestVars(), array());
		unset($baseParams['url']);
		$facets     = FacetHelper::inst()->buildFacets($this->FilteredProducts(), Config::inst()->get('FacetedCategory', 'facets'));
		$facets     = FacetHelper::inst()->updateFacetState($facets, $filters);
		$facets     = FacetHelper::inst()->insertFacetLinks($facets, $baseParams, $baseLink);
		return $facets;
	}
}