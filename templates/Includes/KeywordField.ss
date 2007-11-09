<table cellpadding="5"  cellspacing="0" border="1" class='report'>
<caption>Keywords (
<a class='edit_stage1'><u>Edit</u></a><div style='display:none;'><input type='submit' name='action_SaveChange' value='Save'></div>
	)</caption>
	<tr class='heading'>
		<td><div style='display:none;'>Delete</div></td><td>Keyword</td><td>Bid</td><td>URL</td><td>Clicks</td><td>Impressions</td><td>CTR</td><td>Position</td><td class='rightcolumn'>Score</td>
	</tr>
<% control Items %>
	<tr>
		<td><div style='display:none;'><input type='checkbox' name='$ID_del'></div></td>
		<td><a>$Keyword</a><div style='display:none;'>$KeywordEditor</div></td>
		<td><a>\$$Bid</a><div style='display:none;'>$BidEditor</div></td>
		<td><a><% if URL %> $URL <% else %> - <% end_if %></a><div style='display:none;'>$URLEditor</div></td>
		<td>$Clicks</td>
		<td>$Impressions</td>
		<td>$CTR</td>
		<td>$Position</td>
		<td class='rightcolumn'>$Score</td>
	</tr>
<% end_control %>
<tr>
<td><div style='display:none;'>Add:</div></td>
<td><div style='display:none;'><input type='text' name='new_kw' /></div></td>
<td><div style='display:none;'><input type='text' name='new_bid' /></div></td>
<td><div style='display:none;'><input type='text' name='new_url' /></div></td>
</tr>
</table>
<div style='display:none;'>
Note: The Bid and URL fields are optional, they will take revert to the defaults of the Ad if they are not set.<br />
</div>

