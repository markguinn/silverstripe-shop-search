/**
 * Javascript for range type facets specifically. This is in it's own
 * file because I would expect some users would want to replace the
 * jquery ui component with their own.
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 10.18.13
 * @package shop_search
 * @subpackage javascript
 */
(function ($, window, document, undefined) {
	'use strict';
	if (typeof window.ShopSearch == 'undefined') window.ShopSearch = {};

	window.ShopSearch.RangeFacets = {
		init:function(){
			$('.range-facet-slider').each(function(index, el){
				var slider  = $(el),
					parent  = slider.parent(),
					label   = parent.find('label'),
					input   = parent.find('input'),
					vals    = input.val().split('~');

				vals.shift(); // get rid of "RANGE" marker
				vals[0] = parseFloat(vals[0]);
				vals[1] = parseFloat(vals[1]);

				var onUpdate = function(vals){
					input.val('RANGE~' + vals.join('~'));
					if (label.data('format') == 'Currency') {
						label.html('$' + vals[0].toFixed(2) + ' - $' + vals[1].toFixed(2));
					} else if (label.data('format') == 'Percentage100') {
						label.html(Math.floor(vals[0]) + '% - ' + Math.floor(vals[1]) + '%');
					} else if (label.data('format') == 'Percentage') {
						label.html(Math.floor(vals[0] * 100.0) + '% - ' + Math.floor(vals[1] * 100.0) + '%');
					} else {
						label.html(vals.join(' - '));
					}
				};

				slider.slider({
					range:  true,
					min:    parseFloat(slider.data('min')),
					max:    parseFloat(slider.data('max')),
					values: vals,
					slide:function(event, ui) {
						onUpdate(ui.values);
					},
					change:function(event, ui) {
						var url = input.data('url');
						if (url) {
							url = url
								.replace('RANGEFACETLABEL', encodeURIComponent(label.html()))
								.replace('RANGEFACETVALUE', encodeURIComponent('RANGE~' + ui.values.join('~')));
							$(document.body).trigger('searchstate', url);
						}
					}
				});

				onUpdate(vals);
			});
		}
	};

}(jQuery, this, this.document));
