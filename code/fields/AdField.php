<?php

/**
 * A field displaying a summary of an adwords ad
 */
class AdField extends FormField {
	protected $ad;
	protected $state;
	
	public function Link($action=null) {
		if(!$action) $action = "index";
		return Director::baseURL() . "admin/adwords/" . Director::urlParam("Page") . "/$action";
	}
	
	function __construct($name, $ad, $state) {
		$this->id = $name;
		$this->ad = $ad;
		$this->state = $state;
		
		parent::__construct($name);
	}
	
	public function FieldHolder() {
		Requirements::javascript("jsparty/loader.js");
		Requirements::javascript("jsparty/prototype.js");
		Requirements::javascript("jsparty/behaviour.js");
		Requirements::javascript("jsparty/prototype_improvements.js");
		Requirements::customScript("
		Behaviour.register({
			'span.bid a' : {
				onclick: function() {
					this.style.display='none';
					this.parentNode.getElementsByTagName('div')[0].style.display='inline';
					}
				}});","bidToggler");
		
		return $this->renderWith("Navigation");
	}
	
	function Data()
	{
		return $this->ad->Data;
	}
	function Status()
	{
		return $this->ad->Status;
	}
	function changeStatus()
	{
		if($this->Status() == "Deleted")
		{
			return new LiteralField("","");
		}
		return new Form($this,$this->id."StatusForm",
			new FieldSet(
					$this->state,
					new HiddenField("CampaignID","",$this->ad->GoogleCampaign),
					new HiddenField("AdID","",$this->ad->GoogleID),
					new FormAction_WithoutLabel("PauseUnpause",(stristr($this->Status(),"Active")?"Pause":"Unpause"))
				),
			new FieldSet()
			);
	}
	function Bid()
	{
		return $this->ad->Bid;
	}
	function BidField()
	{
		return new Form($this,$this->id."BidForm",
			new FieldSet(
					$this->state,
					new HiddenField("CampaignID","",$this->ad->GoogleCampaign),
					new HiddenField("AdID","",$this->ad->GoogleID),
					new CurrencyField("BidText",null,$this->ad->Bid),
					new FormAction_WithoutLabel("SaveChange","Save")
				),
			new FieldSet()
			);
	}
	function Impressions()
	{
		$dataset =  $this->ad->Keywords;
		$total = 0;
		if(!$dataset || !$dataset->Count())
		{
			return 0;
		}
		foreach($dataset as $keyword)
		{
			$total += $keyword->Impressions;
		}
		return $total;
	}
	function CTR()
	{
		$dataset =  $this->ad->Keywords;
		$numerator = 0;
		$denominator = 0;
		if(!$dataset || !$dataset->Count())
		{
			return "-";
		}
		foreach($dataset as $keyword)
		{
			$numerator +=$keyword->Impressions * $keyword->CTR;
			$denominator +=$keyword->Impressions;
		}
		if($numerator == 0 & $denominator ==0)
			return "-";
		if($denominator ==0)
			return "inf.";
		return $numerator / $denominator;
	}
	function Keywords()
	{
		return new Form($this,$this->id."KeywordForm",
			new FieldSet(
				$this->state,
					new KeywordField("KeywordField",$this->ad->Keywords)
				),
			new FieldSet()
			);
	}
}
?>