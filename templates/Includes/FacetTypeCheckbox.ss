<h4>$Label</h4>
<% if $NestedValues %>
	<ul data-root data-hierarchy="true" data-link-details="$ATT_val('LinkDetails')">
		<% loop $NestedValues %>
			<% include FacetTypeCheckboxInner %>
		<% end_loop %>
	</ul>
<% else %>
	<ul data-root>
		<% loop $Values %>
			<% include FacetTypeCheckboxInner %>
		<% end_loop %>
	</ul>
<% end_if %>

<% require javascript('framework/thirdparty/jquery-ui/jquery-ui.js') %>
<% require javascript('shop_search/javascript/search.facets.checkbox.js') %>
<% require css('framework/thirdparty/jquery-ui-themes/smoothness/jquery-ui.css') %>
