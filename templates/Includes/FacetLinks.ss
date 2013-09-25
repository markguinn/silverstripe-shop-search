<% if $Facets %>
	<div class="facet-links">
		<% loop $Facets %>
			<div class="facet-wrapper">
				<h4>$Label</h4>
				<ul>
					<% loop $Values %>
						<li><a href="$Link">$Label ($Count)</a></li>
					<% end_loop %>
				</ul>
			</div>
		<% end_loop %>
	</div>
<% end_if %>