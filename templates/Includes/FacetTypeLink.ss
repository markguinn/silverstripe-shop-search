<h4>$Label</h4>
<ul>
	<% loop $Values %>
		<% if $Count > 0 %>
			<li><a href="$Link">$Label ($Count)</a></li>
		<% end_if %>
	<% end_loop %>
</ul>
