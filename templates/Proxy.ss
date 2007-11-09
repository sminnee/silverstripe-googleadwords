<% if CanAccess %>
	<% if LoggedIn %>
		$Content
	<% else %>
		$LoginForm
	<% end_if %>
<% end_if %>