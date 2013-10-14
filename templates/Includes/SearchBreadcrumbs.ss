<% if $SearchBreadcrumbs %>
	<nav class="search-breadcrumbs">
		<ul>
			<% loop $SearchBreadcrumbs %>
				<li><% if $Last %><span>$Title</span><% else %><a href="$Link">$Title</a><% end_if %></li>
			<% end_loop %>
		</ul>
	</nav>
<% end_if %>
