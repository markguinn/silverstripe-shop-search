/**
 * Javascript for autocomplete feature of shop_search
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 9.23.13
 * @package shop_search
 */
(function ($, window, document, undefined) {
	'use strict';

	$(function(){
		var searchField = $('#ShopSearchForm_SearchForm_q'),
			suggestURL  = searchField.data('suggest-url');
		console.log('search init', suggestURL);
		if (suggestURL) {
			searchField.autocomplete({
				minLength:  2,
				source:     suggestURL
			});
		}
	});

}(jQuery, this, this.document));