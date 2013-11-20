<li>
	<label data-url="$Link" data-value="$Value"<% if $ParentValue %> data-parent="$ParentValue"<% end_if %>>
		<input type="checkbox" value="$Value" <% if $Active %>checked<% end_if %>> $Label
	</label>
	<% if $Children %>
		<ul>
			<% loop $Children %>
				<% include FacetTypeCheckboxInner ParentValue=$Up.Value %>
			<% end_loop %>
		</ul>
	<% end_if %>
</li>
