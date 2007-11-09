<?php

/**
 * A field allowing modification of the keywords of an ad
 */
class KeywordField extends FormField {
	protected $keywords;	
	
	public function __construct($name,$keywords) {
		Requirements::javascript('googleadwords/javascript/KeywordField.js');
		Requirements::CustomScript("
		Behaviour.register({
			'.edit_stage1' : {
				onclick: function() {
					var tohide = this.parentNode.parentNode.getElementsByTagName('a');
					for(var i=0;i < tohide.length; ++i)
						tohide[i].style.display='none';
					var toshow = this.parentNode.parentNode.getElementsByTagName('div');
					for(var i=0;i < toshow.length; ++i)
						toshow[i].style.display='inline';
					}
				}});","KeywordEditor");
		
		$this->keywords = $keywords;

		parent::__construct($name);
	}

	function FieldHolder() {
		return $this->renderWith("KeywordField");
	}
	
	function Items()
	{
		//We need to add information about available table actions to the keywords
		//before they're rendered by the engine
		foreach($this->keywords as $kw)
		{
			$kw->KeywordEditor = new TextField($kw->ID."_ke","",$kw->Keyword);
			$kw->BidEditor = new CurrencyField($kw->ID."_be","",$kw->Bid);
			$kw->URLEditor = new TextField($kw->ID."_ue","",$kw->URL);
		}
		return $this->keywords;
	}

}	

?>