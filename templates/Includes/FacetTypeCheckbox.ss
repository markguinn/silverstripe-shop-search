<h4>$Label</h4>
<ul>
	<% loop $Values %>
		<li><label data-url="$Link"><input type="checkbox" value="$Value" <% if $Active %>checked<% end_if %>> $Label</label></li>
	<% end_loop %>
</ul>
