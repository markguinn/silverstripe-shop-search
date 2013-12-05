/**
 * Javascript for checkbox facets
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 9.23.13
 * @package shop_search
 */
(function ($, window, document, undefined) {
	'use strict';
	if (typeof window.ShopSearch == 'undefined') window.ShopSearch = {};

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

	window.ShopSearch.CheckboxFacets = {
		init:function() {
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
		}
	};
}(jQuery, this, this.document));