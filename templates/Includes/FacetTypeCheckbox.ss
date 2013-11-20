<h4>$Label</h4>
<% if $NestedValues %>
	<ul data-hierarchy="true">
		<% loop $NestedValues %>
			<% include FacetTypeCheckboxInner %>
		<% end_loop %>
	</ul>
<% else %>
	<ul>
		<% loop $Values %>
			<% include FacetTypeCheckboxInner %>
		<% end_loop %>
	</ul>
<% end_if %>
