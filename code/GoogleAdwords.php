<?php

/*
 * This class ties the module into the admin interface.
 * _config allows this class to modify the cms fields of all pages
 * We use that power to embed a new tab with a custom iframe to load in the module
 */
class GoogleAdwords {


	static function getAdwordsIframe() {
		$controller = Controller::currentController();
		if(method_exists($controller,"currentPage") && method_exists($controller->currentPage(),"ElementName"))
			$page = $controller->currentPage()->ElementName();
		else
			$page = "unknown";

		return <<<END
		<iframe name="GoogleAdwords" src="admin/adwords/$page/index" id="GoogleAdwords" border="0" style="width:100%; height:100%;">
		</iframe>
END;
	}
	static function getCMSFields($fields) {
	//	$fields->findOrMakeTab("Root.Adwords");
		$fields->addFieldToTab("Root.Adwords",new LiteralField("GoogleAdwords",self::getAdwordsIframe()));
		return $fields;
	}
	
	private static $driver = "";
	static function setDriver($method,$p1=null,$p2=null)
	{
		if($method=="Scraping")
			GoogleAdwords::$driver = "GoogleInterface";
		else
		{
			GoogleAdwords::$driver = "ApilityInterface";
			ApilityInterface::$developerToken = $p1;
			ApilityInterface::$applicationToken = $p2;
		}
	}
	static function driver()
	{
		return GoogleAdwords::$driver;
	}
}

?>