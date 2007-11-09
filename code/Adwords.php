<?php
/**
 * Primary logic for the Adwords Module
 * This code controls communication between actions & the back end
 * And provides the main forms.
 */
class Adwords_Controller extends controller {
	/**
	 * Provide URL Links, Required internally as a viewable item
	 */
	public function Link($action=null) {
		if(!$action) $action = "index";
		return Director::baseURL() . "admin/adwords/" . Director::urlParam("Page") . "/$action";
	}
	
	/*
	 * Taken From cms's LeftAndMain
	 * This is a CMS page, but because we're a module, and have the iframe thing
	 * going, it's impractical to subclass LeftAndMain just to get this function.
	 */
	public function CanAccess() {
		$member = Member::currentUser();
		if(!is_a($member,"Member")) {
			return false;
		}
		
		if($member) {
			if($groups = $member->Groups()) {
				foreach($groups as $group) if($group->CanCMS) return true;
			}
		}

		return $member->isAdmin();
	}
	
	/**
	 * The main index page
	 * Since errors are generated in re-direct, I don't know of a good way
	 * of having them as post arguments, so the easy work-around was sending
	 * them as base64 encoded get arguments.  The work aroud requires this
	 * extra logic in the index method.
	 */
	function index() { 
		// Check for errors
		if(isset($_REQUEST['error'])) {
			$this->LoginError = base64_decode($_REQUEST['error']);
		}
		else {
			$this->LoginError = false;			
		}
		
		return array(); 
	}
	
	/**
	 * Proxy communications to google.  Provides access to google's adwords website using
	 * the stored authentication tokens, so that a re-login isn't required.
	 */
	function proxy() {
		$urlData = parse_url($_SERVER['REQUEST_URI']);
				
		$passthru = "";		
		
		//The path after /proxy/ in the url represents the google url
		$urlData['path']=str_replace("//","/",$urlData['path']);
		$path = explode("/", $urlData['path']);
		$pathindex = array_search("proxy", $path);
		if(strstr($path[$pathindex + 1], "passthru")) {
			$pathindex++;
			list($empty, $passthru) = explode("=", $path[$pathindex]);
		}
		
		$googlepath = join("/", array_slice($path, $pathindex + 1));

		//Determine the full url, including query string
		$getstring = "";
		foreach($_GET as $key => $value) {
			if($key=="passthru" || $key=="url")
				continue;
			$getstring .= "&" . $key . "=" . $value;
		}
		if(strlen($getstring)>1)
			$google_url = "https://adwords.google.com/" . $googlepath . "?" . substr($getstring, 1);
		else
			$google_url = "https://adwords.google.com/" . $googlepath;
		
		
		$authentication = unserialize($this->Account()->Authentication);
		
		//process post vars into the right format
		foreach($_POST as $key => $value) {
			$google_post[$key] = $key . "=" . $value;
		}
		if(isset($google_post) && is_array($google_post))
			$google_post_string = join("&", $google_post);
		else
			$google_post_string = "";
		
		//Request the page from google
		$page = GoogleInterface::Proxy($google_url, $google_post_string, strlen($google_post_string) > 1, $authentication);
		
		//For Binary Files, We just want to show them
		$mimetype = $page['headers']['mime'];
		if(!strstr($mimetype,"text")) {
			header("Content-Type: $mimetype");
			die($page['response']);
		}
		
		//Otherwise, we want to re-write links in the page to go through our server
		$newbase=$this->Link('proxy');
		if(strlen($passthru)) {
			$newbase .= "/passthru=" . $passthru;
		}
		//Re-write the links
		$newpage = GoogleInterface::ScrubLinks($google_url, $page['response'], $newbase);
		
		/*
		 *If a pass-thru function was called to add extra modifications to the page,
		 *run it here.
		 */
		if(strlen($passthru)) {
			$passthru=explode("-",$passthru);
			$newpage = call_user_func($passthru,$newpage,$this);
		}
		$this->Content = $newpage;

		//If we're loading a javascript or css file, we don't want to run it through the template.
		if(!strstr($mimetype,"html")) {
			header("Content-Type: $mimetype");
			die($newpage);
		}
		return $this->renderWith("Proxy");
	}
	
	
	/**
	 * Display a login form, simply a deligate to the custom 'loginfield' field type;
	 * The login field mimics google's interface.
	 */
	public function LoginForm() {
        return new Form($this, "LoginForm", new FieldSet(
            // List the your fields here
            new LoginField("Login","ProcessLogin",$this->LoginError)
        ), new FieldSet());	
	}
	
	/**
	 * For now, the 'state' we carry arround is whiich set of tabs to display.
	 * This is a common function, because all the various forms need to have this
	 * In them.
	 */
	public function State() {
		//Return Fields that will ensure we remain in the same state (as a fieldset)
		$fields = new FieldGroup(new LiteralField("",""));
		
		if(isset($_REQUEST['all'])) $fields->push(new HiddenField("all","","all"));
		
		return $fields;
	}
	
	/**
	 * The form describing what mode the module is in,
	 * (IE what set of ads is being viewed), and allowing for that
	 * mode to be changed.
	 */
	public function ModeForm() {
		$viewing = (isset($_REQUEST['all']))?"Viewing All Ads":"Viewing Ads For This Page";
		$button =  (isset($_REQUEST['all']))?"View Ads for this page":"View all ads";
		$state = (!isset($_REQUEST['all']))?"all":"";
		return new Form($this,"ModeForm",
			new FieldSet(
				new LiteralField("first","<h2 style='color: #0074C6;'>$viewing"),
				new LiteralField("index","<a href='{$this->Link()}?$state' style='color:black;font-size:small;font-weight:normal;'/>$button</a>"),
				new LiteralField("second","</h2>")
			), new FieldSet());
	}
	
	/**
	 * Displays the Navigation form for the adwords interface.
	 * This is the ad Tabs, and an AdField within each tab
	 */
	public function NavigationForm() {
		$Tabs = new TabSet("Navigation");
		
		$ads = $this->Ads();
		if(!count($ads)) {
			return new Form($this,"NavigationForm",new FieldSet(new LiteralField("NoAds","There are no ads in this view.")),new FieldSet());
		}
		
		foreach($ads as $ad) {
			//Attach keywords to the ads in our main view
			$kws = DataObject::get("AdwordsWords","ParentID = ".$ad->ID);
			$ad->Keywords = $kws;
			
			$tab = new Tab($ad->Name,
				new AdField("Ad".$ad->ID,$ad,$this->State()));
			$Tabs->push($tab);
		}		
		
		return new Form($this,"NavigationForm",
		new FieldSet($Tabs),
		new FieldSet());
	}
	
	/**
	 * This form action handles pausing and unpausing an
	 * Ad group.
	 */
	function PauseUnpause($data,$form) {
		//Pause the Ad
		$status = call_user_func_array(array(GoogleAdwords::driver(),"AdwordsPauseAd"), array(
										unserialize($this->Account()->Authentication),
										$data['CampaignID'],
										$data['AdID'],
										$data['action_PauseUnpause']!='Pause'));
		if(!$status)
			return $this->logout();
		//Mark it as such
		$ad = DataObject::get_by_id("AdwordsAds",$data['AdID']);
		$ad->Status = $data['action_PauseUnpause']=='Pause'?"Paused":"Active";
		$ad->write();
		
		//And Send them back to where they were
		Director::redirect("index".(isset($data['all'])?"?all=true":""));
	}
	
	
	/**
	 * This is the override for sub-form actions.  They will activate either
	 * As Ad#_bidform or Ad#_keywordform; either way the call we make to google
	 * is to set the keyword properties for an Ad, so they combine into this one function
	 */
	function __call($func,$args) {
		//The sub-forms that have actions all centralize around here, which is nice
		//since they all do the same thing internally
		// but, first we make sure that this is a request that we are expecting
		if(!strstr($func,"BidForm") && !strstr($func,"KeywordForm"))
			return false;
		
		$myad = DataObject::get_by_id("AdwordsAds",$_REQUEST['AdID']);
		
		if(strstr($func,"BidForm")!==false) {
			//Change Default Bid
			$myad->Bid=$_REQUEST['BidText'];
		}
		else {
			//Changes & Deletions to Keywords
			foreach($myad->Keywords as $word) {
				if(isset($_REQUEST[$word->ID."_ke"]))
					$word->Keyword=$_REQUEST[$word->ID."_ke"];
				if(isset($_REQUEST[$word->ID."_be"]))
					$word->Bid=$_REQUEST[$word->ID."_be"];
				if(isset($_REQUEST[$word->ID."_ue"]))
					$word->URL=$_REQUEST[$word->ID."_ue"];
				if(isset($_REQUEST[$word->ID."_del"]))
					$word->delete();

				$word->write();
			}
			//Additions to Keywords
			if(isset($_REQUEST['new_ke'])) {
				$kw = new AdwordsWords();
				$kw->Keyword = $_REQUEST['new_kw'];
				if(isset($_REQUEST['new_bid']) && $_REQUEST['new_bid'] >0 && $_REQUEST['new_bid'] !="")
					$kw->Bid = $_REQUEST['new_bid'];
				if(isset($_REQUEST['new_url']) && $_REQUEST['new_url'] != "")
					$kw->URL = $_REQUEST['new_url'];
				$kw->ParentID = $myad->ID;
				$kw->write();
			}
		}
		//Make the API Call
		$status = call_user_func_array(array(GoogleAdwords::driver(),"AdwordsSetKeyword"), array(
											unserialize($this->Account()->Authentication),
											$myad->GoogleCampaign,
											$myad->GoogleID,
											$myad->Bid,
											$myad->KeywordString()));
		if(!$status)
			return $this->logout();

		//Re-sync our data
		foreach($myad->keywords as $kw) {
			$kw->delete();
		}
		$myad = call_user_func_array(array(GoogleAdwords::driver(),"AdwordsAd"),array(
											unserialize($this->Account()->Authentication),
											$myad->GoogleCampaign,
											$myad->GoogleID));
		$myad->write();
		foreach($myad->Keywords as $kw) {
			$kw->ParentID = $myad->ID;
			$kw->write();
		}
		
		//And send them back to where they were
		Director::redirect("index".(isset($_REQUEST['all'])?"?all=true":""));
	}
	
	/**
	 * Return all Ads that exist in a view
	 * @return DataObjectSet of AdwordsAds.
	 */
	public function Ads() {
		$mode = isset($_REQUEST['all']);
		if($mode)
		 return DataObject::get("AdwordsAds");
		
		$url = Director::absoluteURL(Director::urlParam("Page"),true);

		$ads = DataObject::get("AdwordsAds","AdwordsAds.URL = '" . $url . "' OR AdwordsWords.URL = '" . $url . "'","","INNER JOIN AdwordsWords ON AdwordsAds.ID = AdwordsWords.ParentID");
		
		return $ads;
	}
	
	/**
	 * Determines if the user is currently logged into an adwords account,
	 * You're logged in if you still have an entry in the AdwordsAccount table
	 */
	public function LoggedIn() {
		$valid = DB::Query("SELECT COUNT(*) FROM AdwordsAccount"); //DataObject::get("AdwordsAccount");
		$status =  array_pop($valid->nextRecord()) > 0;
		
		if($status) {
			Requirements::css("googleadwords/css/ads.css");
		}
		
		return $status;
	}
	
	/**
	 * Access to the AdwordsAccount information
	 */
	function Account() {
		return DataObject::get_one("AdwordsAccount");
	}
	
	/**
	 * Find the 'most suitable' campaign for new ads
	 */
	function Campaign() {
		$allads = DataObject::get("AdwordsAds");
		if ($allads) foreach($allads as $anAd) {
			if(strstr($anAd->URL, Director::absoluteURL("/")) != false) {
				return $anAd->GoogleCampaign;
			}
		}
		
		if(count($allads)) {
			foreach($allads as $anAd)
				return $anAd->GoogleCampaign;
		}
		
		return -1;
	}
	/**
	 * Process an attempted login action, confirm credentials with google and
	 * populate the database with account information as needed
	 * @param $data the form data sent from the page, containing username & password
	 * @param $form the form that is requesting the action
	 */
	function ProcessLogin($data, $form) {
		// Attempt Login to adwords
		$email = $data['Login']['Username'];
		$passwd = $data['Login']['Passwd'];
		$credentials = call_user_func_array(array(GoogleAdwords::driver(),"AdwordsLogin"),array($email,$passwd));
		if(is_array($credentials)) {
			// Save the account data
			$account = new AdwordsAccount();
			$account->Email = $email;
			$account->Authentication = serialize($credentials);
			$account->write();
		
			// Preload the user's information
			$data= call_user_func_array(array(GoogleAdwords::driver(),"GetData"),array($credentials));
			
			// Clean out the old data:
			DB::query("DELETE FROM AdwordsAds");
			DB::query("DELETE FROM AdwordsWords");
			
			foreach($data as $ad) {
				//Deal with parentage
				$ad->ParentID = $account->ID;				
				$ad->write();
				
				//Now, save all the keywords as well
				foreach($ad->Keywords as $kw) {
					$kw->ParentID = $ad->ID;
					$kw->write();
				}
			}
			
			// Redirect them back to the main interface (now happy)
			Director::redirect("index");
		}
		else if($credentials!==false) {   //Failed To login
			Director::redirect("index?error=".base64_encode($credentials));
		}
		else { //Captcha required
			return $this->renderWith("Captcha_Error");
		}
	}
	
	function LogoutForm() {
		return new Form($this,"LogoutForm",new FieldSet(new FormAction_withoutLabel("logout","Sign out")),new FieldSet());
	}
	function logout() {
		//Remove entries from the accounts table, then re-direct back to index().
		//we save the ad & keyword data
		$account = DataObject::get_one("AdwordsAccount");
		$account->delete();
		
		Director::redirect("index");
		
	}
	
	
}

?>