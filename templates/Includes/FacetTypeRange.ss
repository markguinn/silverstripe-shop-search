<h4>$Label</h4>
<input type="hidden" id="facet-value-$Source" class="range-facet-value" value="RANGE~$MinValue~$MaxValue" data-url="$Link"/>
<div id="facet-slider-$Source" class="range-facet-slider" data-min="$RangeMin" data-max="$RangeMax"></div>
<label id="facet-label-$Source" class="range-facet-label" data-format="$LabelFormat"></label>

<% require javascript('framework/thirdparty/jquery-ui/jquery-ui.js') %>
<% require javascript('shop_search/javascript/search.facets.range.js') %>
<% require css('framework/thirdparty/jquery-ui-themes/smoothness/jquery-ui.css') %>
