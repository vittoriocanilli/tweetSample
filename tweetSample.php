<?php

/*
 * Global varibles used for both classes
 */
$START_TIME;
$MAX_TIME;
$MAX_TWEETS;

/*
 * TweetSample class
 * 
 * @param 		string 		"--n=x"			 n parameter with value = x (number of max tweets)
 * @param 		string 		"--t=y"			 t parameter with value = y (number of max seconds)
 * @return 		void 		tweets are printed on the standard output
 * @example		php tweetSample.php --t=4 --n=3
 * @author 		Vittorio Canilli
 * @version 	1.0
 */
class TweetSample {
	
	protected $arguments;
	
	protected $http_method = "GET";
	protected $base_url = "https://stream.twitter.com/1.1/statuses/sample.json";
	
	protected $consumer_key = "*****************************";
	protected $consumer_secret = "*****************************";
	protected $access_token = "*****************************";
	protected $access_token_secret = "*****************************"; // you get your ones after registration on https://dev.twitter.com
	
	// Contructor, which just initializes the global variables and reads the input parameters
	public function __construct($arguments = null) {
		
		$this->arguments = $arguments;
		global $START_TIME, $MAX_TIME, $MAX_TWEETS;
		$START_TIME = time();
		$MAX_TWEETS = $this->getIntValue("n");
		$MAX_TIME = $this->getIntValue("t");
	}
	
	// Registers the TweetBuffer as stream buffer and calls the Twitter Stream URL
	public function start() {
		
		stream_wrapper_register("TweetBuffer","TweetBuffer") or die("Failed to register protocol");
		$fp = fopen("TweetBuffer://tweetStream","r+");
		
		$headers = array();
		$headers[] = "Content-Type: application/x-www-form-urlencoded";
		$headers[] = "Authorization: " . $this->getOAuth();
		$ch = curl_init($this->base_url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_USERAGENT, "OAuth gem v0.4.4");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FILE, $fp);
		curl_setopt($ch, CURLOPT_TIMEOUT, 99999999);
		$result = curl_exec($ch);
		curl_close($ch);
		fclose($fp);
	}
	
	// Gets the OAuth header as specified in the Twitter documentation
	private function getOAuth() {
		
		$oAuthArray = array(
				"oauth_consumer_key"		=> $this->consumer_key,
				"oauth_nonce"				=> md5(microtime() . mt_rand()),
				"oauth_signature_method"	=> "HMAC-SHA1",
				"oauth_timestamp"			=> time(),
				"oauth_token"				=> $this->access_token,
				"oauth_version"				=> "1.0"
		);
		
		$oAuth  = "OAuth ";
		$oAuth .= "oauth_consumer_key=\"".rawurlencode($oAuthArray["oauth_consumer_key"])."\", "; 
		$oAuth .= "oauth_nonce=\"".rawurlencode($oAuthArray["oauth_nonce"])."\", "; 
		$oAuth .= "oauth_signature=\"".rawurlencode($this->getSignature($oAuthArray))."\", "; 
		$oAuth .= "oauth_signature_method=\"".rawurlencode($oAuthArray["oauth_signature_method"])."\", "; 
		$oAuth .= "oauth_timestamp=\"".rawurlencode($oAuthArray["oauth_timestamp"])."\", "; 
		$oAuth .= "oauth_token=\"".rawurlencode($oAuthArray["oauth_token"])."\", "; 
		$oAuth .= "oauth_version=\"".rawurlencode($oAuthArray["oauth_version"])."\""; 
		
		return $oAuth;
	}
	
	// Gets the OAuth signature as specified in the Twitter documentation
	private function getSignature($reqData) {
		
		$sigData = array();
		
		foreach($reqData as $reqIndex => $reqValue) {
			$sigData[rawurlencode($reqIndex)] = rawurlencode($reqValue);
		}
		
		$paramString = http_build_query($sigData);
		
		$sigBaseString = strtoupper($this->http_method) . "&" . rawurlencode($this->base_url) . "&" . rawurlencode($paramString);
		
		$signingKey = rawurlencode($this->consumer_secret) . "&" . rawurlencode($this->access_token_secret);
		
		$signature = base64_encode(hash_hmac('sha1', $sigBaseString, $signingKey, true));
		
		return $signature;
	}
	
	// Gets the integer value of the input parameters
	private function getIntValue($key) {
		
		foreach($this->arguments as $argument) {
			$pos = strpos($argument, "--$key=");
			if ($pos !== false)
				return intval(substr($argument, $pos+4));
		}
		return 0;
	}
	
}

/*
 * TweetBuffer class is an accessory class used by TweetSample to print out the tweets
 */
class TweetBuffer {
	
	protected $buffer;
	protected $current_tweets = 0;
	
	// Opens the stream
	function stream_open($path, $mode, $options, &$opened_path) {
		return true;
	}
	
	// Writes the stream on the standard output
	public function stream_write($data) {
		// Extract the lines ; on y tests, data was 8192 bytes long ; never more
		$lines = explode("\n", $data);
	
		// The buffer contains the end of the last line from previous time
		// => Is goes at the beginning of the first line we are getting this time
		$lines[0] = $this->buffer . $lines[0];
	
		// And the last line os only partial
		// => save it for next time, and remove it from the list this time
		$nb_lines = count($lines);
		$this->buffer = $lines[$nb_lines-1];
		unset($lines[$nb_lines-1]);
	
		for ($i=0; $i<count($lines); $i++) {
			$curDecodedTweet = json_decode($lines[$i]);
			if (property_exists($curDecodedTweet,"text")) {
				if($this->continueToTweet()) {
					$tweetUserName = $curDecodedTweet->user->name;
					$tweetText = $curDecodedTweet->text;
					echo "USER-NAME: $tweetUserName | TEXT = $tweetText\n";
					$this->current_tweets ++;
				}
				else
					return;
			}
		}
	
		return strlen($data);
	}
	
	// Checks whether it can continues to write the tweets on the standard output according to the 2 input paramters (n and t)
	private function continueToTweet() {
		
		global $START_TIME, $MAX_TIME, $MAX_TWEETS;
		
		$tweetsCondition = ($MAX_TWEETS == 0) || ($MAX_TWEETS > $this->current_tweets);
		
		$now = time();
		$timeCondition = ($MAX_TIME == 0) || ( ($now - $START_TIME) < $MAX_TIME );
		
		return $tweetsCondition && $timeCondition;
		
	}
}

/*
 * Initialize an instance of the TweetSample class with the input parameters and then start the connection with the Twitter Stream URL
 */
$testTweet = new TweetSample($argv);
$testTweet->start();

?>