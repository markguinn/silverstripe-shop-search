<?php
/**
 * Search driver for the fulltext module with solr backend.
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 08.29.2013
 * @package shop_search
 */
class ShopSearchSolr extends SolrIndex
	implements ShopSearchAdapter
{
	/**
	 * Sets up the index
	 */
	function init() {
		$searchables = ShopSearch::get_searchable_classes();

		// Add each class to the index
		foreach ($searchables as $class) {
			$this->addClass($class);
		}

		// TODO: replace this with searchable_fields or custom config
		$this->addAllFulltextFields();
	}

	/**
	 * This is an intermediary to bridge the search form input
	 * and the SearchQuery class. It allows us to have other
	 * drivers that may not use the FullTextSearch module.
	 *
	 * @param string $keywords
	 * @param array $filters [optional]
	 * @param array $facetSpec [optional]
	 * @return ArrayData
	 */
	function searchFromVars($keywords, array $filters=array(), array $facetSpec=array()) {
		$query = new SearchQuery();
		$query->search($keywords);
		return $this->search($query);
	}
}