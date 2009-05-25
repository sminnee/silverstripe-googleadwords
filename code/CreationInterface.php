<?php
/**
 * This class is a proxy pass-thru (helper) for the new-ad interface.
 * It has some additional code that is injected into the google pages
 * as they are returned to the browser, which removes the main google interface,
 * and only displays the actual ad-creation section of the interface.
 */
class CreationInterface {
	public static function Process($data,$caller) {
		$t1 = _t('CreationInterface.LOADING','Loading...');
		$topstuff = <<<EOF
</head>
<div style='z-index:500; position:fixed; top:0; bottom:0; left:0; right:0; background:white; padding-top:50%; padding-right:50%;' id='loading'>
{$t1}
</div>
EOF;
		$t2 = _t('CreationInterface.RETURNTOMAIN','Return To Main View');
		$extrastuff = <<<EOF
<script type='text/javascript'>
	document.getElementById("navigation").style.display="none";
	var tables = document.getElementsByTagName("table");
	var rows = tables[0].getElementsByTagName("tr");
	rows[0].style.display="none";
	
	document.getElementById("wzChooseKeywords-QuestionPanel").style.display="none";
	document.getElementById("wzFooter").style.display="none";
	document.getElementById("loading").style.display="none";
	// Provide a Consistant interface throughout
	var snippit = '<div style="float:right;" class="useropts"><strong>{$caller->Account()->Email}</strong> | {$caller->LogoutForm()->forTemplate()}</div>';
	var snippit2 = '<a style="color:black; padding:2px;" href="{$caller->Link()}">{$t2}</a>';
	document.body.innerHTML = snippit + document.body.innerHTML + snippit2;
</script>
</body>
EOF;
		$data =str_replace("</head>",$topstuff,$data);
		return str_replace("</body>",$extrastuff,$data);
	}
}
?>