<?php
/**
 * Adds some methods to the Product class to list out all categories
 * a product is in. This is generally useful, but also really helps
 * with building search indexes.
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 05.23.2014
 * @package shop_search
 * @subpackage helpers
 */
class ProductCategoryHelperMethods extends DataExtension
{
	/**
	 * Includes Parent() and ProductCategories() and all the parent categories of each.
	 * @param bool $recursive [optional] - include the parents of the categories as well? - default: true
	 * @return array - array of productCategory records
	 */
	protected function buildParentCategoriesArray($recursive=true) {
		$out = array();

		// add the main parent
		$parent = $this->owner->Parent();
		if (!$parent || !$parent->exists()) return $out;
		if ($parent->ClassName == 'ProductCategory') $out[$parent->ID] = $parent;

		if ($recursive) {
			foreach ($parent->getAncestors() as $rec) {
				if ($rec->ClassName == 'ProductCategory') $out[$rec->ID] = $rec;
			}
		}

		// add any secondary categories
		foreach ($this->owner->ProductCategories() as $cat) {
			$out[$cat->ID] = $cat;
			if ($recursive) {
				foreach ($cat->getAncestors() as $rec) {
					if ($rec->ClassName == 'ProductCategory') $out[$rec->ID] = $rec;
				}
			}
		}

		return $out;
	}


	/**
	 * Includes Parent() and ProductCategories() and all the parent categories of each.
	 * @return ArrayList - array of productCategory records
	 */
	public function getAllCategories() {
		return new ArrayList( $this->buildParentCategoriesArray(false) );
	}


	/**
	 * Includes Parent() and ProductCategories() and all the parent categories of each.
	 * @return ArrayList - array of productCategory records
	 */
	public function getAllCategoriesRecursive() {
		return new ArrayList( $this->buildParentCategoriesArray(true) );
	}


	/**
	 * Includes Parent() and ProductCategories()
	 * @return array - array of ID's
	 */
	public function getAllCategoryIDs() {
		return array_keys( $this->buildParentCategoriesArray(false) );
	}


	/**
	 * Includes Parent() and ProductCategories() and all the parent categories of each.
	 * @return array - array of ID's
	 */
	public function getAllCategoryIDsRecursive() {
		return array_keys( $this->buildParentCategoriesArray(true) );
	}


	/**
	 * Returns an array of the titles of all the categories.
	 * @return array - (strings)
	 */
	public function getAllCategoryTitles() {
		$cats = $this->buildParentCategoriesArray(false);
		$out = array();
		foreach ($cats as $cat) $out[] = $cat->Title;
		return $out;
	}


	/**
	 * Returns an array of the titles of all the categories, including grandparents, etc
	 * @return array - (strings)
	 */
	public function getAllCategoryTitlesRecursive() {
		$cats = $this->buildParentCategoriesArray(true);
		$out = array();
		foreach ($cats as $cat) $out[] = $cat->Title;
		return $out;
	}


	/**
	 * Returns a string of the titles of all the categories.
	 * @param string $sep
	 * @return string
	 */
	public function getJoinedCategoryTitles($sep = ', ') {
		return implode($sep, $this->getAllCategoryTitles());
	}


	/**
	 * Returns a string of the titles of all the categories.
	 * @param string $sep
	 * @return string
	 */
	public function getJoinedCategoryTitlesRecursive($sep = ', ') {
		return implode($sep, $this->getAllCategoryTitlesRecursive());
	}

}