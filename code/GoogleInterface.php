<?php

define('X_SOURCE', 'SilverStripe-CMS-2');


/**
 * Integration And Communication with Google's services
 * The Unofficial / page-scraping edition
 */
class GoogleInterface {
	/*
	 * Authenticate against the google authentication api.
	 */
	public static function Authenticate($email,$password,$service) {
		$payload = "accountType=GOOGLE&" .
				   "Email=".$email."&" .
				   "Passwd=".$password."&" .
				   "service=".$service."&" .
				   "source=" . X_SOURCE . "&";
		$g = new GoogleInterface();
		$response = $g->Post("https://www.google.com/accounts/ClientLogin",$payload);
		
		if($response["status"]==200) {
			// Process the tokens
			$googleAuth=array_combine(array("sid","lsid","auth",""),explode("\n",$response["response"]));
			$googleAuth["status"]=true;
			$googleAuth["LastError"]="";
			return $googleAuth;
		}
		else {
			$googleAuth=array();
			$googleAuth["status"]=false;
			$googleAuth["LastError"]=false;
			// Handle failed login / captcha challenge
			return $googleAuth;
		}	
	}
	
	/*
	 * Preform Authentication against adwords and get a valid set of authentication tokens
	 */
	public static function AdwordsLogin($email, $pw) {
		$Google_Auth_URL = "https://www.google.com/accounts/ServiceLoginBoxAuth";
		$Google_Cookie_URL = "https://www.google.com/accounts/CheckCookie";
	
		
		$g = new GoogleInterface();
		
		//Initial Authentication Request
		$Google_Auth_URL_Params = array("continue=".urlencode("https://adwords.google.com/select/gaiaauth?apt=None"),
		"followup=".urlencode("https://adwords.google.com/select/gaiaauth?apt=None"),"service=adwords","ifr=true",
		"Email=".$email,"Passwd=".$pw);
		
		$auth_result = $g->Post($Google_Auth_URL, join("&",$Google_Auth_URL_Params));
		$cookies = $g->processCookies($auth_result["headers"]);
		
		//Check if we logged in successfully, we have two return statuses:  a _string_ represents failure with that error code,
		// false represents that the user has been locked out because of too many failed attempts, so we need to send them off
		// to verify against captcha.		
		if(!is_array($cookies)) {
			if(strpos($auth_result['response'],"img")!==false)
				return false;
			return $g->parse($auth_result['response'],array("<div class=\"errormsg\""=>"</div>",">"=>""));
		}
		
		
		$g = new GoogleInterface();
		//Confirm We got the cookie, get 1st Adwords Auth
		$Google_Cookie_Params = array("continue=".urlencode("https://adwords.google.com/select/gaiaauth?apt=None"),
		"followup=".urlencode("https://adwords.google.com/select/gaiaauth?apt=None"),"service=adwords","chtml=LoginDoneHtml");

		$cookie_result = $g->Get($Google_Cookie_URL, join("&",$Google_Cookie_Params),$cookies);
		$cookies = $g->mergeCookies($cookies,$g->processCookies($cookie_result["headers"]));
		
		//Now, we will follow the path until we reach the end;
		$lastresponse=$cookie_result;
		while(true) {
			$next = $g->processsRedirect($lastresponse['headers']);
			if($next==false) break;
			
			if(strpos($next,"http")===false)
				$next = "https://adwords.google.com".$next;
			
			$g = new GoogleInterface();
			$lastresponse = $g->Get($next,"",$cookies);
			$cookies = $g->mergeCookies($cookies,$g->processCookies($lastresponse["headers"]));
		}
				
		return $cookies;
	}
	
	/*
	 * Get all data from an adwords account
	 */
	public static function GetData($cookies) {
		$url = "https://adwords.google.com/select/campaignsummary";

		//First, we'll find active campaigns at /select/campaignsummary
		$g = new GoogleInterface();
		$response = $g->Get($url, "", $cookies);
		//Make sure our authentication is still valid
		if($g->processsRedirect($response['headers'])) return false;

		$AdData = new DataObjectSet();
		
		//For the campaigns, our goal is simply to find a campaign ID, and the link to the campaign page
		$campaignstring = $g->parse($response['response'], array("Sort by total cost" => "<script", "<tbody>" => "</tbody>"));
		$campaigns = explode("<tr", $campaignstring);
		foreach($campaigns as $campaign) {
			$link = $g->parse($campaign, array("<a href=\"" => "#a"));
			if($link == $campaign) continue;
			
			$campaignid = $g->parse($link, array("=" => ""));
			
			$AdData->merge(GoogleInterface::AdwordsAd($cookies, $campaignid));
		}
		return $AdData;
	}
	
	/**
	 * Act as an intermediary for displaying data to the user from the main adwords site.
	 */
	public static function Proxy($url, $payload = "", $post = false, $cookies) {
		$max_recursion = 5;
		
		//After more than 5 re-directs, something's wrong.
		while(--$max_recursion) {
			$g = new GoogleInterface();
			
			//Make the request
			if($post) {
				$response = $g->Post($url, $payload, $cookies);
			} else {
				$response = $g->Get($url, $payload, $cookies);
			}
			
			//Deal with re-directs if needed
			$url = $g->processsRedirect($response['headers']);
			if($url == false) break;
			
			if(strpos($url , "http") === false)
				$url = "https://adwords.google.com" . $url;
			
			$cookies = $g->mergeCookies($cookies, $g->processCookies($response["headers"]));
		}
		
		//Provide the mime type of the response
		foreach($response['headers'] as $header) {
			if(stristr($header,"Content-Type")!==false) {
				$start=strpos($header,":")+1;
				$response['headers']['mime'] = trim(substr($header,$start));
				break;
			}
		}
		
		return $response;
	}
	
	/**
	 * Replace Links on a page so that they can be proxied
	 */
	public static function ScrubLinks($url, $data, $newbase) {
		// Get useful data out of the url
		$urlPieces = parse_url($url);
		$pathParts = pathinfo($urlPieces['path']);
		$urlbase = $pathParts['dirname'];
		
		// First make all relative urls absolute
		while (eregi("(href|src|action)=\"(([^/])[[:alnum:]/+=%&_.~?-]*)\"", $data, $regs)) {
			$input_uri = $regs[2];
			$output_uri = "/" . $urlbase . "/" . $input_uri;

			$out_rewrite =$regs[1]."=\"" . $output_uri . "\"";
			$match=$regs[0];

			$data = str_replace($match, $out_rewrite, $data);
		}
			
		// Deal with ="/(absolute url)...
		$data = ereg_replace("(href|src|action)=\"/", "\\1=\"$newbase/", $data);

		// Deal with href="http://(full url)
		$data = ereg_replace("href=\"([[:alnum:]]*)://([^/]*)/", "href=\"$newbase/", $data);
		
		// Finally, we have to deal with extensions.  Many of the extensions
		// result in not processing through silverstripe to us again
		// We can fix that by adding a ".proxy" to the end of all of the urls
		$data = ereg_replace("\"([[:alnum:]/+=%&_~-]*\.[[:alnum:]]*)(\?[[:alnum:]/+=%&_~-]*)?\"",
					 "\"\\1.proxy\\2\"",$data);
		
		return $data;
	}
	
	/**
	 * Get data about a specific ad, or ads in a campaign
	 *
	 * @param cookies The authentication tokens for adwords
	 * @param campaignID Google's numeric identification of an active campaign
	 * @param adID the ID of a specific ad group, a DataObjectSet of all ad-groups will be returned if not provided.
	 * @return information about specified ad or ads, in AdwordsAds objects
	 */
	public static function AdwordsAd($cookies,$campaignID,$adID=null) {
		$url = "https://adwords.google.com/select/CampaignManagement?campaignid=".$campaignID."&mode=viewAllAdGroups&adGroupTabSelected=keywords";
				
		$g = new GoogleInterface();
		$adresponse = $g->Get($url,"",$cookies);
		
		//Make sure the authentication is still valid
		if($g->processsRedirect($adresponse['headers'])) return false;
		
		//Now we start getting information about the ads on this page
		$FoundAds=new DataObjectSet();
		$ads_html = $g->parse($adresponse['response'],array("View all ad groups"=>"#666666","<table"=>""));
		foreach(explode("statsdisclaimer",$ads_html) as $ad_html) {
			if(strpos($ad_html,"<h4")===false) continue;

			//The data in this section of the page will be represented by one adwordsAd object
			$out = new AdwordsAds();

			
			// The information about the ad can be gotten without too much trouble
			$out->Name = trim($g->parse($ad_html,array("<h4"=>"</h4>","/a>"=>"")));
			$out->Data = substr(trim($g->parse($ad_html,array("</h4>"=>"<span","valign=\"middle\">"=>"<td style"))),0,-5);
			$out->URL = $g->parse($out->Data,array("href=\""=>"\""));
			$out->Bid = (double)str_replace("$","",trim($g->parse($ad_html,array("e8e8cf"=>"","float"=>"","nbsp;"=>"<"))));
			$out->Status = trim($g->parse($ad_html,array("</h4>"=>"</b>","<b>"=>"",">"=>"<")));
			$out->GoogleID = trim($g->parse($ad_html,array("<h4"=>"</a>","name=\""=>"\"")));
			$out->GoogleCampaign = $campaignID;

			// To get the data about keywords, we need to know the layout of the table
			$headers=array();
			$headers_html = explode("<th",$ad_html);
			foreach($headers_html as $header_html) {
				if(strpos($header_html,"</th")===false) continue;
				
				$headers[] = $g->parseText($g->parse($header_html,array(">"=>"</th>")));
			}
			
			// Here, we run through the rows of the table to get all the information about the keyword out
			$out->Keywords = new DataObjectSet();
			$keywords_html = $g->parse($ad_html,array("selcriterionid"=>"Content network total"));
			$keywords_html = explode("<tr",$keywords_html);
			foreach($keywords_html as $keyword_html) {
				//Make the Adwords-word object
				$keyword = new AdwordsWords();
				
				if(strpos($keyword_html,"</tr")===false) continue;
				
				$fields_html = explode("<td",$keyword_html);
				$i=0;
				foreach($fields_html as $field_html) {
					$field=$g->parseText($g->parse($field_html,array(">"=>"")));
					if(strlen($field)) {
						$keyword->LoadInto($headers[$i++],$field,$field_html);
					}
				}
				$out->Keywords->push($keyword);
			}
			
			if(!is_null($adID) && $adID == $out->GoogleID)
				return $out;
			else
				$FoundAds->push($out);
		}
		return $FoundAds;
	}
	
	
	/*
	 * Set keywords for a specific ad
	 */
	public static function AdwordsSetKeyword($cookies, $campaignID, $adID, $basePrice,$keywords) {
		$url = "https://adwords.google.com/select/EditKeywords";
		
		$params = array(
			"campaignid=".$campaignID,
			"adgroupid=".$adID,
			"price=".$basePrice,
			"keywords=".urlencode($keywords),
			"save=Save%20Changes");
		
		$g = new GoogleInterface();
		$response = $g->Get($url,join("&",$params),$cookies);
		
		return strpos($adresponse['response'],"successfully")!==false;
	}
	
	/*
	 * Pause or Resume an ad
	 */
	public static function AdwordsPauseAd($cookies, $campaignID, $adID, $isPaused) {
		$url = "https://adwords.google.com/select/ModifyAdGroup?mode=".($isPaused?"resume":"pause")."adgroup&campaignid=".$campaignID."&adgroupid=".$adID."&url=CampaignManagement";

		$g = new GoogleInterface();
		$adresponse = $g->Get($url,"",$cookies);

		return strpos($adresponse['response'],"Ad groups successfully")!==false;
	}
	
	/*
	 * Private curl handler for the object
	 */
	private $ch;
	function __construct() {
		$this->ch = curl_init();		
	}
	function __destruct() {
		curl_close($this->ch);
	}
	
	/*
	 * Send a curl get request.
	 */
	private function Get($url,$params,$cookies=false) {
		return $this->Request((($params!=false)?$url."?".$params:$url),false,false,$cookies);
	}

	/*
	 * Send a curl post request.
	 */
	private function Post($url,$payload,$cookies=false) {
		return $this->Request($url,$payload,true,$cookies);
	}
	
	/*
	 * Sends a generic request
	 */
	private function Request($url,$payload,$post,$cookies) {
		if(substr($url,-6)==".proxy") {
			$url = substr($url,0,-6);
		}
	    curl_setopt($this->ch, CURLOPT_URL, $url);
	    curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
	    curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, true);
	    curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, 2);

		if($cookies!=false) {
			if(count($cookies)>1)
				$cookies = join("; ",$cookies);

			curl_setopt($this->ch, CURLOPT_COOKIE, $cookies);
		}

		$this->headers=array();
		curl_setopt($this->ch, CURLOPT_HEADERFUNCTION, array(&$this,'readHeader'));

        $header = array();
        $header[] = "Content-Type: application/x-www-form-urlencoded";
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $header);
		
		if($post) {
	    	curl_setopt($this->ch, CURLOPT_POST, 1);
	    	curl_setopt($this->ch, CURLOPT_POSTFIELDS, $payload);
		} else {
			curl_setopt($this->ch, CURLOPT_POST, 0);
		}

	    // Execute the API request.
	    $response = curl_exec($this->ch);

	    /*
	     * Verify that the request executed successfully.
	     */
	
		$info = curl_getinfo($this->ch);
		
	    if (curl_errno($this->ch)) {
			print_r(curl_error($this->ch));
	        throw curl_error($this->ch);
	    }

	    // Return the response to the API request
	    return array("status"=>$info['http_code'],"info"=>$info,"headers"=>$this->headers,"response"=>$response);
	}
	/**
	 * Processes cookies returned from a request into usable format
	 */
	function processCookies($headers) {
		$output="";
		foreach($headers as $header) {
			if(stristr($header,"Set-Cookie")!==false) {
				$start=strpos($header,":")+1;
				$length = strpos($header,";")-$start;
				$cookie = substr($header,$start,$length);
				$break = strpos($cookie,"=");
				$name = substr($cookie,0,$break);
				$value = substr($cookie,$break+1);
				if(stristr($value,"expired")===false)
					$output[] = trim($cookie);
			}
		}
		return $output;
	}
	/**
	 * Find redirect headers, and return the url to redirect to.
	 *
	 */
	function processsRedirect($headers) {
		foreach($headers as $header) {
			if(stristr($header,"Location")!==false) {
				$start=strpos($header,":")+1;
				$url = trim(substr($header,$start));
				return $url;
			}
		}
		return false;
	}
	/**
	 * Merge two sets of cookies
	 */
	function mergeCookies($old, $new) {
		$all=array();
		foreach($old as $cookie) {
			$break = strpos($cookie,"=");
			$name = substr($cookie,0,$break);
			$value = substr($cookie,$break+1);
			$all[$name]=$value;
		}
		if(!$new)
			$new=array();
		foreach($new as $cookie) {
			$break = strpos($cookie,"=");
			$name = substr($cookie,0,$break);
			$value = substr($cookie,$break+1);
			$all[$name]=$value;
		}
		$out=array();
		foreach($all as $n=>$v) {
			$out[]=$n."=".$v;
		}
		return $out;
	}

	/**
	 * Parse the contents of a tag from an html document
	 */
	function parse($document,$limits) {
		$remainder=$document;
		foreach($limits as $front=>$end) {
			$newstart = stripos($remainder,$front) + strlen($front);
			if($newstart <= 0 || $front == "") $newstart =0;
			$remainder = substr($remainder, $newstart);

			$newend = stripos($remainder,$end);
			if($newend <= 0 || $end == "") $newend = strlen($remainder);
			$remainder = substr($remainder, 0, $newend);
		}
		return trim($remainder);
	}
	/**
	 * Find the text content in an html snippit (remove tags)
	 */
	function parseText($html) {
		return trim(ereg_replace("<[^>]*>","",$html));
	}

	/**
	 * This code is needed because curl deals with response headers through a callback interface.
	 * This lets us store all of the returned headers.
	 */

	private $headers=array();
	/**
     * callback to read headers from curl
     * ch - the curl object
     * header - the header to process
     */
	private function readHeader($ch,$header) {
		$this->headers[] = $header;
		return strlen($header);
	}
	

}
?>