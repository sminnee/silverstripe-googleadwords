<?php

/*
 * This class represents the ads in a users accounts
 * Each ad is stored seperately, irregardless of adgroup or campaign
 */
class AdwordsAds extends DataObject {
	static $db = array(
		"Name" => "Varchar",
		"Data" => "HTMLText",
		"URL" => "Varchar",
		"Bid" => "Currency",
		"Status" => "Varchar",
		"GoogleID" => "Varchar",
		"GoogleCampaign" => "Varchar");

	static $defaults = array(
		"Status" => "Inactive");
		
	static $has_one = array(
		"Parent" => "AdwordsAccount");
	
	static $has_many = array(
		"Keywords" => "AdwordsWords");

	/**
	 * Parse the information out of the google keyword format
	 */
	function KeywordString()
	{
		$keywords = DataObject::get("AdwordWords","ParentID=".$this->ID);
		$string="";
		foreach($keywords as $keyword)
		{
			$string .= trim($keyword['Keyword']);
			
			$bid = $keyword['Bid'];
			if($bid == 0) {$bid = $this->Bid;}
			
			if($keyword['URL']!="" && $keyword['URL']!=$this->URL)
			{
				$string .= " ** " . $bid . " ** " . $keyword['URL'];
			}
			else if($bid != $this->Bid)
			{
				$string .= " ** " . $bid;
			}
			$string .= "\n";
		}
		return trim($string);
	}
}

?>