<li class="expander-container">
	<% if $Children %>
		<a href="javascript:;" class="expander">
			<i class="icon-caret-down fa fa-caret-down for-expanded"></i>
			<i class="icon-caret-right fa fa-caret-right for-contracted"></i>
		</a>
	<% end_if %>
	<label data-url="$Link" data-value="$Value" title="$Label.XML"
		<% if $ParentValue %> data-parent="$ParentValue"<% end_if %>
		<% if $Children %> data-children="$Children.Count"<% end_if %>>
		<input type="checkbox" value="$Value" <% if $Active %>checked<% end_if %>> $Label <% if $Count %><span class="count">($Count)</span><% end_if %>
	</label>
	<% if $Children %>
		<ul data-hierarchy="true" class="expander-target">
			<% loop $Children %>
				<% include FacetTypeCheckboxInner ParentValue=$Up.Value %>
			<% end_loop %>
		</ul>
	<% end_if %>
</li>
