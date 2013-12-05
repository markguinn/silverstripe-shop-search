/**
 * Javascript for autocomplete/suggest/search-as-you-type.
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 10.18.13
 * @package shop_search
 * @subpackage javascript
 */
(function ($, window, document, undefined) {
	'use strict';
	if (typeof window.ShopSearch == 'undefined') window.ShopSearch = {};

	window.ShopSearch.Suggest = {
		init:function(){
			var searchField = $('#ShopSearchForm_SearchForm_q'),
				suggestURL  = searchField.data('suggest-url'),
				cache       = {};

			if (suggestURL && searchField.length > 0) {
				searchField.autocomplete({
					minLength:  2,
					source:function(request, response){
						var cacheKey;
						var term = request.term;
						var select = searchField.closest('form').find('select');
						if (select.length > 0) {
							request[select.attr('name')] = select.val();
							cacheKey = 'CAT' + select.val() + '_' + term;
						} else {
							cacheKey = 'CAT0_' + term;
						}

						// Need to factor category in if present
						if (cacheKey in cache) {
							response(cache[cacheKey]);
							return;
						}

						$.getJSON(suggestURL, request, function(data, status, xhr) {
							// Result comes back with 2 arrays, transform them into something we can render more easily
							// This is slightly more cumbersome, but it allows the frontend dev to use a different
							// autocomplete (or whatever) javascript.
							var out = [];

							if (data.suggestions.length > 0) {
								for (var i = 0; i < data.suggestions.length; i++) {
									out.push({
										label:      data.suggestions[i],
										category:   'Search Suggestions'
									});
								}
							}

							if (data.products.length > 0) {
								for (var i = 0; i < data.products.length; i++) {
									data.products[i].category = 'Products';
									out.push(data.products[i]);
								}
							}

							cache[cacheKey] = out;
							response(out);
						});
					}
				});

				searchField.data( "ui-autocomplete" )._renderMenu = function( ul, items ) {
					var self = this,
						currentCategory = "";

					ul.addClass('shop-search');
					$.each( items, function( index, item ) {
						if ( item.category != currentCategory ) {
							ul.append( "<li class='ui-autocomplete-category'>" + item.category + "</li>" );
							currentCategory = item.category;
						}
						self._renderItemData( ul, item );
					});
				};

				searchField.data( "ui-autocomplete" )._renderItem = function(ul, item) {
					var li = $('<li>');

					if (item.title) {
						var a = $('<a>').addClass('product').attr('href', item.link);

						if (item.thumb) {
							$('<img>').attr('src', item.thumb).appendTo(a);
							a.addClass('thumb');
						}

						$('<span>').addClass('title').html(item.title).appendTo(a);

						if (item.desc) $('<span>').addClass('desc').html(item.desc).appendTo(a);
						var price = $('<span>').addClass('price').html(item.price).appendTo(a);
						if (item.original_price) price.prepend('<del>'+item.original_price+'</del> ');

						a.appendTo(li);
					} else {
						$('<a>').html(item.label).appendTo(li);
					}

					return li.appendTo(ul);
				};
			}
		}
	};

}(jQuery, this, this.document));