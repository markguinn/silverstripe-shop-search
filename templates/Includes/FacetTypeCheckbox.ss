<h4>$Label</h4>
<ul data-root data-hierarchy="true" data-link-details="$ATT_val('LinkDetails')">
    <% if $NestedValues %>
    	<% loop $NestedValues %>
    		<% include FacetTypeCheckboxInner %>
    	<% end_loop %>
	<% else %>
    	<% loop $Values %>
    		<% include FacetTypeCheckboxInner %>
    	<% end_loop %>
	<% end_if %>
</ul>

<% require javascript('framework/thirdparty/jquery-ui/jquery-ui.js') %>
<% require javascript('shop_search/javascript/search.facets.checkbox.js') %>
<% require css('framework/thirdparty/jquery-ui-themes/smoothness/jquery-ui.css') %>
<% require css('shop_search/css/expander.css') %>
<% require javascript('shop_search/javascript/expander.js') %>
