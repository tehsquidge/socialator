<?php
	
/* ==========================================================================
   Parameters and Variables
   ========================================================================== */

    /* General */
    $outerClass = $modx->getOption('$outerClass', $scriptProperties, "socialPosts");
    $postCount = $modx->getOption('$postCount', $scriptProperties, 25);
    $count = $postCount;
	$timeZone = $modx->getOption('timeZone', $scriptProperties, 'Europe/London');
    $toPlaceholder = $modx->getOption('toPlaceholder', $scriptProperties, '');

    /* Twitter */
	$twitterHandle = $modx->getOption('twitterHandle', $scriptProperties, '');
    $twitterAccessToken = $modx->getOption('twitterAccessToken', $scriptProperties, '');
    $twitterAccessTokenSecret = $modx->getOption('twitterAccessTokenSecret', $scriptProperties, '');
    $twitterConsumerKey = $modx->getOption('twitterConsumerKey', $scriptProperties, '');
    $twitterConsumerSecret = $modx->getOption('twitterConsumerSecret', $scriptProperties, '');
    $twitterRetweets = $modx->getOption('twitterRetweets', $scriptProperties, 0);

    if($twitterRetweets == true){
        $twitterRetweets = 1;
    } else if ($twitterRetweets == false){
        $twitterRetweets = 0;
    }

    /* Facebook */
    $facebookPageId  = $modx->getOption('facebookPageId', $scriptProperties, '');
	$facebookAppId = $modx->getOption('facebookAppId', $scriptProperties, '');
	$facebookAppSecret = $modx->getOption('facebookAppSecret', $scriptProperties, '');

    $posts = array(); //Empty Array to Store All Combined Posts

	$socialIcons = array(
		"twitter" => "<img src=\"assets/img/social/twitter.svg\" onerror=\"this.src=\'/assets/img/logos/brand/twitter.png\'\">",
		"facebook" => "<img src=\"assets/img/social/facebook.svg\" onerror=\"this.src=\'/assets/img/logos/brand/facebook.png\'\">"
	);
 
    
/* ==========================================================================
   General Functions
   ========================================================================== */

    /* For Twitter */
	function getPageOAuth($url)
	{
		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_HTTPHEADER => array('Authorization: OAuth '.implode(', ', $arr), 'Expect:'),
			CURLOPT_HEADER => false,
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYPEER => false,
		));
		$curl = curl_exec($curl);
		$contents = curl_exec($curl);
		curl_close($curl);
		return $contents;
	}
	
	/* For Facebook */
	function getPage($url)
	{
		$curl = curl_init();
		
		curl_setopt($curl,CURLOPT_URL,$url); 
		curl_setopt($curl,CURLOPT_RETURNTRANSFER,TRUE);
		curl_setopt($curl,CURLOPT_CONNECTTIMEOUT,5);
		
		curl_setopt($curl, CURLOPT_FAILONERROR, TRUE);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, TRUE);
		curl_setopt($curl, CURLOPT_AUTOREFERER, TRUE);
		curl_setopt($curl, CURLOPT_TIMEOUT, 10);

		$contents = curl_exec($curl);
		curl_close($curl);
		return $contents;
	}

    /* Change Links in Post to <a> Tags */
	function linkify($text, $platform) {
        // Change Hashtags to links
		$hastagPrefix = '';
		if($platform == 'facebook'){
			$hashtagPrefix = "https://www.facebook.com/hashtag/";
		} else {
			$hashtagPrefix = "https://twitter.com/search?src=typd&q=%23";
		}
		
        // Catch all other links
		$text = ' ' . $text;
		$text = preg_replace(
			'`([^"=\'>])((http|https|ftp)://[^\s<]+[^\s<\.)])`i',
			'$1<a href="$2">$2</a>',
			$text
		);
		$text = substr($text, 1);
		$text = preg_replace('/#(\w+)/', ' <a href="'.$hashtagPrefix.'$1">#$1</a>', $text);
		
		return $text;
	}
		

/* ==========================================================================
   Twitter
   ========================================================================== */

    // Set Twitter Options Array
    $options = array(
		'screen_name' => $twitterHandle,
		'count' => $count,
		'include_rts' => $twitterRetweets
	);
 
    // Set Oath Perameters for the API
    $oauth = array(
        'oauth_consumer_key' => $twitterConsumerKey,
        'oauth_nonce' => time(),
        'oauth_signature_method' => 'HMAC-SHA1',
        'oauth_timestamp' => time(),
        'oauth_token' => $twitterAccessToken,
        'oauth_version' => '1.0'
    );
 

	$oauth = array_merge($oauth, $options);
    ksort($oauth);

    $arr = array();
    foreach($oauth as $key => $val)
        $arr[] = $key.'='.rawurlencode($val);
 
    // Encrypt Hash of Values to Ensure Validity During Transfer
    $oauth['oauth_signature'] = base64_encode(hash_hmac('sha1', 'GET&'.rawurlencode('https://api.twitter.com/1.1/statuses/user_timeline.json').'&'.rawurlencode(implode('&', $arr)), rawurlencode($twitterConsumerSecret).'&'.rawurlencode($twitterAccessTokenSecret), true));
 
    $arr = array();
    foreach($oauth as $key => $val)
        $arr[] = $key.'="'.rawurlencode($val).'"';
 
    // CURL call to API
    $tweets = curl_init();
    curl_setopt_array($tweets, array(
        CURLOPT_HTTPHEADER => array('Authorization: OAuth '.implode(', ', $arr), 'Expect:'),
        CURLOPT_HEADER => false,
        CURLOPT_URL => 'https://api.twitter.com/1.1/statuses/user_timeline.json?'.http_build_query($options),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
    ));
    $tweetsjson = curl_exec($tweets);
    curl_close($tweets);
	
	$json ='';	
	
	$tweetsDecoded = json_decode($tweetsjson, true);
	
	foreach($tweetsDecoded as $tweet){
			$array = array(
				"socialPlatform" => "twitter",
				"timestamp" => new DateTime($tweet['created_at'], new DateTimeZone($timeZone)),
				"text" => $tweet['text'],
				"url" => "https://twitter.com/".$tweet['user']['screen_name']."/status/".$tweet['id']
			);
			
			array_push ($posts, $array);
	}


/* ==========================================================================
   Facebook
   ========================================================================== */
 
	//$response = file_get_contents('https://graph.facebook.com/oauth/access_token?grant_type=client_credentials&client_id='.$facebookAppId.'&client_secret='.$facebookAppSecret);
	$response = getPage('https://graph.facebook.com/oauth/access_token?grant_type=client_credentials&client_id='.$facebookAppId.'&client_secret='.$facebookAppSecret);
	$accessToken = str_replace('access_token=', '', $response); 
 
	//get the page accounts
	$accounts = getPage('https://graph.facebook.com/v2.3/'.$facebookAppId.'/accounts?access_token='.$accessToken);
	
	$response =  get_object_vars(  json_decode($accounts) );
	$pageToken = get_object_vars($response['data'][0]);
	$pageToken = $pageToken['access_token']; 
	
	$statuses = getPage('https://graph.facebook.com/v2.3/'.$facebookPageId.'/statuses?access_token='.$pageToken);
	$statuses = json_decode($statuses, true);
	$statuses = $statuses["data"];

	foreach($statuses as $status){
			
			$array = array(
				"socialPlatform" => "facebook",
				"timestamp" => new DateTime($status['updated_time'], new DateTimeZone($timeZone)),
				"text" => $status['message'],
				"url" => "https://www.facebook.com/photo.php?fbid=".$status['id']
			);
			
			array_push ($posts, $array);
	}
	
	function date_compare($a, $b)
	{
		$first = strtotime($a['timestamp']->format('Y-m-d H:i:s'));
		$second = strtotime($b['timestamp']->format('Y-m-d H:i:s'));
		return $second - $first;
	}    
	usort($posts, 'date_compare');
 
	//$data  = file_get_contents('https://graph.facebook.com/'.$facebookPageId.'/posts?access_token='.$token);
	//$data = json_decode($data, true);
	//echo 'https://graph.facebook.com/'.$facebookPageId.'/feed?access_token='.$token;
 
 
 
  /*$url='https://graph.facebook.com/v2.3/'.$facebookAppId.'/accounts?access_token='.$token;
  
  echo $url.'<br/>';
  
  $ch=curl_init();
  CURL_SETOPT($ch,CURLOPT_URL,$url);
  CURL_SETOPT($ch,CURLOPT_RETURNTRANSFER, 1);
  $json=json_decode(curl_exec($ch));
  var_dump($json);
 
*/
 
	/*$output ='
 		<script src="https://connect.facebook.net/en_US/all.js" type="text/javascript"></script>
		<script>
			$(document).ready(function(){
				$.ajaxSetup({ cache: true });
	  			$.getScript("//connect.facebook.net/en_US/sdk.js", function(){
					var accessToken = "'.$token.'";	
					var facebookPageId = "";
					var facebookAppId = "";

					FB.init({ 
						facebookAppId: facebookAppId, 
						version: "v2.3",
						xfbml: true,
						status: true
					});
					//get the app accounts(pages)
					FB.api("/"+facebookAppId+"/accounts", {access_token: accessToken},
						function (response) {
							if (response && !response.error) {
								//we have the information about the app pages. (for-loop here?)
								var page_token = response.data[0].access_token;
								//get the page
								FB.api("/"+facebookPageId+"/statuses", {access_token: page_token},
								    function (response) {
								      if (response && !response.error) {
								        console.log(response);
								      }else{ console.log(response.error); }
								    }
								);
							}else{
								console.log(response.error);
							}
						}
					);
				});
			});
		</script>
		';*/

	
	
	

/* ==========================================================================
   Output Loop
   ========================================================================== */
 
	$output = '<div class="socialator '.$outerClass.'">';
	
	for ($i = 0; $i < $postCount; $i++) {
		$output = $output.'
			<div class="post '.$posts[$i]["socialPlatform"].'">
				<div class="upper clearfix">
					<div class="icon">'.$socialIcons[$posts[$i]["socialPlatform"]].'</div>
					<a href="'.$posts[$i]["url"].'" target="_blank"><time datetime="'.$posts[$i]["timestamp"]->format('Y-m-d H:i:s').'">'.$posts[$i]["timestamp"]->format('d/m/Y').'</time></a>
				</div>
				<div class="content">'.linkify($posts[$i]["text"], $posts[$i]["socialPlatform"]).'</div>
			</div>
		';
	}
	
	$output = $output.'</div>';
 
    return $output;