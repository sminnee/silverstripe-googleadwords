<html>
<head>
<style>
body {
	padding: 15px;
	margin: 0;
	background-color: #fff !important;
}
fieldset {
	padding: 0;
	margin: 0;
	border-style: none;
}
</style>
<link rel="stylesheet" type="text/css" href="cms/css/typography.css" >
<link rel="stylesheet" type="text/css" href="cms/css/layout.css" >
<% base_tag %>
</head>

<body>
	
<% if CanAccess %>
	<% if LoggedIn %>
		<% include Ad_View %>
	<% else %>
		$LoginForm
	<% end_if %>
<% else %>
	You don't have permission to access this resource.
<% end_if %>
</body>
</html>