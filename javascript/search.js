/**
 * Javascript for autocomplete feature of shop_search
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 9.23.13
 * @package shop_search
 */
(function ($, window, document, undefined) {
	'use strict';
	if (typeof(window.ShopSearch) == 'undefined') window.ShopSearch = {};

	ShopSearch.init = function() {
		// Search sorter
		$('#sort').change(function(){
			var $this = $(this);
			var url = $this.data('url');
			url = url.replace('NEWSORTVALUE', encodeURIComponent($this.val()));
			$(document.body).trigger('searchstate', url);
		});

		// initialize the other components
		if (ShopSearch.Suggest)        ShopSearch.Suggest.init();
		if (ShopSearch.CheckboxFacets) ShopSearch.CheckboxFacets.init();
		if (ShopSearch.RangeFacets)    ShopSearch.RangeFacets.init();
	};

	// We define this global event which, by default, just reloads
	// the page. This allows one to unregister all handlers on this
	// event and register one of it's own which may, for example,
	// use Ajax or otherwise check the form state.
	// We define it outside the onready handler so that it can easily
	// be unregistered from inside any other onready handler.
	$(document.body).on('searchstate', function(e, url){
		if (url && url.substr(0, 1) != '/' && url.substr(0, 5) != 'http:') {
			// There is a bug/feature in IE that causes document.location.href = '...'
			// not to respect the base href with relative urls. This takes care of it.
			var base = 	$('base');
			if (base.length > 0) {
				var baseHref = base.prop('href');
				if (baseHref.substr(-1) != '/') baseHref += '/';
				url = baseHref + url;
			}
		}

		if (url) document.location.href = url;
	});

	// initialize search onready
	$(document).ready(ShopSearch.init);

}(jQuery, this, this.document));
