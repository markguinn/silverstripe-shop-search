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


		// For hierarchical checkboxes, update the parent to reflect the children states
		function updateParentCheckbox($label) {
			if (!$label || $label.length == 0) return;

			var myState = false;
			$label.closest('li').find('ul input[type=checkbox]').each(function(index, el){
				if (el.checked) myState = true;
			});

			$label.children('input')[0].checked = myState;

			if ($label.data('parent')) {
				updateParentCheckbox( $this.closest('.facet-checkbox').find('label[data-value='+$label.data('parent')+']') );
			}
		}

		// Facet checkboxes - this seems wonky to have to have a handler on both the label
		// and the checkbox but discard the label event but that's the only way i've found
		// that works consistently.
		$('.facet-checkbox label, .facet-checkbox input[type=checkbox]').click(function(e){
			if (e.target.nodeName != 'INPUT') return;
			var $this = $(this),
				$label = $this.closest('label');

			// If this is a hierarchical dataset, go ahead and check/uncheck the parents and children
			// This has no impact in our current implementation, but it's better for user experience
			// especially if the page takes a while to load.
			if ($this.closest('ul').data('hierarchy')) {
				var myState = e.target.checked;

				$this.closest('li').find('ul input[type=checkbox]').each(function(index, el){
					el.checked = myState;
				});

				if ($label.data('parent')) {
					updateParentCheckbox( $this.closest('.facet-checkbox').find('label[data-value='+$label.data('parent')+']') );
				}
			}

			// go to the new url
			var url = $label.data('url');
			$(document.body).trigger('searchstate', url);
		});


		// Search sorter
		$('#sort').change(function(){
			var $this = $(this);
			var url = $this.data('url');
			url = url.replace('NEWSORTVALUE', encodeURIComponent($this.val()));
			$(document.body).trigger('searchstate', url);
		});

	});

	// We define this global event which, by default, just reloads
	// the page. This allows one to unregister all handlers on this
	// event and register one of it's own which may, for example,
	// use Ajax or otherwise check the form state.
	// We define it outside the onready handler so that it can easily
	// be unregistered from inside any other onready handler.
	$(document.body).on('searchstate', function(e, url){
		if (url) document.location.href = url;
	});

}(jQuery, this, this.document));