<?php

/*
 * This class represents a users adwords account
 * It is the code around the database storage of the user's authentication criteria
 */
class AdwordsAccount extends DataObject {
	static $db = array(
		"Email" => "Varchar",
		"Authentication" => "Text",
		"LastAccess" => "Datetime");

	static $defaults = array(
		"LastAccess" => "Now");
		
	static $has_many = array(
		"Ads" => "AdwordsAds");
		
		


}

?>