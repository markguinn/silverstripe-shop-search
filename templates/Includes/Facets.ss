<% if $Facets %>
	<div class="facets">
		<% loop $Facets %>
			<div class="facet-$Type">
				<% if $Type == 'link' %><% include FacetTypeLink %><% end_if %>
				<% if $Type == 'checkbox' %><% include FacetTypeCheckbox %><% end_if %>
				<% if $Type == 'range' %><% include FacetTypeRange %><% end_if %>
			</div>
		<% end_loop %>
	</div>
<% end_if %>