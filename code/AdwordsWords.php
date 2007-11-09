<?php

/*
 * This class represents a single keyword
 * It is the code that lets you add and remove keywords from an ad
 */
class AdwordsWords extends DataObject {
	static $db = array(
		"Keyword" => "Text",
		"Bid" => "Currency",
		"URL" => "Varchar(255)",
		"Clicks" => "Int",
		"Impressions" => "Int",
		"CTR" => "Varchar",
		"Position" => "Varchar",
		"Score" => "Varchar");
		
	static $has_one = array(
		"Parent" => "AdwordsAds");
		
	/**
	 * essentially sets data fields in the object.
	 * The function encapsilates some translations,
	 * as the terminoligy is different from the field names
	 * used by google.
	 */
	public function LoadInto($field, $data,$raw_data)
	{
		$translations = array("Quality Score"=>"Score","Impr."=>"Impressions","Avg. Pos"=>"Position");
		
		$translatedField = (isset($translations[$field]))?$translations[$field]:$field;
		
		if(strpos($field,"Bid")!==false && strpos($field,"Settings")===false) {
			$translatedField="Bid";
			$data=(double)str_replace("$","",$data);
		}
		
		//Custom URL's are a pain
		if(strpos($field,"Settings")!==false)
		{
			$translatedField="URL";
			//since there's javascript on custom urls, we parse it a bit different
			$data = substr($raw_data,strpos($raw_data,"CPC"));
			$data = substr($data,strpos($data,"href")+6);
			$data = substr($data,0,strpos($data,"\""));
		}
		
		$this->$translatedField = $data;
	}

}

?>