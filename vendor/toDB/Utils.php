<?php 
CONST start = 'start';
CONST end = 'end';
/**
 * This is a generic Utilities Class used for general
 * purpose functionality to be located in one place
 * and included in a Static Class that is lazy loaded
 * (on demand only).
 * This is a swiss army knife class for multi-purposes
 * 
 * Intent is to be a starting template for useful functions
 * 
 * @original-author: T. Paul Quidera
 *
 */
class Utils {
	static private 
		$_level=0,
		$_cntr=0,
		$_startTime=false;
	
	/**
	 * Filter a variable to allow integer only results
	 * @param INTEGER $int
	 */
	static public function filterInt(&$int){
		$int = filter_var($int, FILTER_SANITIZE_NUMBER_INT);
	}
	/**
	 * Filter a variable to allow Float only results
	 * @param FLOAT $float
	 */
	static public function filterFloat(&$float){
		$float = filter_var($float, FILTER_SANITIZE_NUMBER_FLOAT);
	}
	/**
	 * Filter a variable to allow EMAIL only results
	 * @param STRING $email
	 */
	static public function filterEmail(&$email){
		$email = filter_var($email, FILTER_SANITIZE_EMAIL);
	}
	/**
	 * Filter a variable to allow STRING only results, remove html and special characters
	 * @param STRING $text
	 */
	static public function filterText(&$text, $asciiOnly=false){
		if ($asciiOnly){
			self::convert_to_ascii($text);
		}
		else {
			$localcopy = filter_var($text, FILTER_SANITIZE_STRING); // unreliable fails randomly
			if (empty($localcopy))
				$text = strip_tags($text);
			else 
				$text = $localcopy;
		}
	}
	/**
	 * Filter a variable to add slashes to quotes
	 * @param STRING $text
	 */
	static public function filterQuotes(&$text){
		$email = filter_var($email, FILTER_SANITIZE_MAGIC_QUOTES);
	}
	
	/**
	 * Sanatize and array from all possible injected html or javascript
	 * This will perform recursion on all nested arrays.
	 * This is not an absolute shield, only common text injections
	 * 
	 * @param mixed array $varray 
	 */
	static public function filterArray(&$varray, $asciiOnly=false){
		if (!empty($varray))
		foreach ($varray as $key => &$value){
			if (!is_array($value)){
				self::filterText($value, $asciiOnly);
			}
			else { // handle array
				self::filterArray($value, $asciiOnly);
			}
		}
		return;
	}
	
	/**
	 * Filter all external variable inputs using the
	 * FILTER_SANATIZE_STRING criteria
	 * This will NOT prevent all injection attempts
	 * only the most obvious and "traditional" injections
	 */
	static public function filterInputs(){
		self::filterArray($_POST);
		self::filterArray($_GET);
		self::filterArray($_REQUEST);
		self::filterArray($_COOKIE);
	}
	/**
	 * get the array info of the current directory
	 * @return Directory
	 */
	static public function getDirInfo(){
		return pathinfo($_SERVER["SCRIPT_NAME"]);
	} 
	/**
	 * This is kinda old now, but is here for future replacement if one can get around  much of the 
	 * limiting factors NATs, Proxies, Replication tools, etc shielding the outside world IP's
	 * @return string|boolean|unknown
	 */
	static public function getIPAddress() {
		$ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');
		foreach ($ip_keys as $key) {
			if (array_key_exists($key, $_SERVER) === true) {
				foreach (explode(',', $_SERVER[$key]) as $ip) {
					// trim for safety measures
					$ip = trim($ip);
					// attempt to validate IP
					if (self::_validate_ip($ip)) {
						return $ip;
					}
				}
			}
		}
		return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : false;
	}
	/**
	 * Ensures an ip address is both a valid IP and does not fall within
	 * a private network range.
	 */
	static private function _validate_ip($ip)
	{
		if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
			return false;
		}
		return true;
	}
	/**
	 * Report if the current call is an Ajax Call
	 */
	static function isAjax(){
		if (isset($_SERVER["HTTP_X_REQUESTED_WITH"]) && $_SERVER["HTTP_X_REQUESTED_WITH"]=="XMLHttpRequest")
			return true;
		else 
			return false;
	}
	
	/**
	 * Used to place at beginning and end of functions and generates a stack trace in the 
	 * error log file when the $GLOBALS["debug"] value is set to true.
	 * 
	 * @param string $Txt of info to log
	 * @param const start|end
	 */
	static function debugTrace($Txt, $startFunc=true){
		
		if (isset($GLOBALS["debug"]) && !empty($GLOBALS["debug"])){
			if (!self::$_startTime){
				if (!empty($_SERVER["REQUEST_TIME_FLOAT"]))
					self::$_startTime = (float)$_SERVER["REQUEST_TIME_FLOAT"];
				else
					self::$_startTime = (float)microtime(true);
			}
			self::$_cntr++;
			if ($startFunc=='start'){
				$state="[Entry]";
				self::$_level++;
			}
			else {
				self::$_level--;
				$state="[Exit]";
			}
			$now = microtime(true);
			if (empty(intval(self::$_level)) || self::$_level<0)
				self::$_level=0;
			// ------------------------------------------------------
			// To make it easier to read, remove the doc root prefix
			// ------------------------------------------------------
			$rootDir = str_replace('/','\\', $_SERVER["DOCUMENT_ROOT"]);
			$Txt = str_replace($rootDir, "", $Txt);
			$debugTXT = "DebugTrace[".self::$_cntr."][".self::$_level."][".
				((!empty($_SESSION["UserName"]))?@$_SESSION["UserName"]:session_id())."][".$_SERVER["HTTP_HOST"].
				"][".round($now - self::$_startTime,4).
				"]".str_repeat("-", self::$_level).">[".$Txt."]".$state;
			error_log($debugTXT);
			
		}
	}
	
	/**
	 * Used for interum logging while the debugTrace is on.
	 * @param unknown $Txt
	 */
	static function debugLog($Txt){
		if (isset($GLOBALS["debug"]) && !empty($GLOBALS["debug"])){
			$debugTXT = "DebugTrace[".self::$_cntr."][".self::$_level."][".
				((!empty($_SESSION["UserName"]))?@$_SESSION["UserName"]:session_id()).
				"][".$_SERVER["HTTP_HOST"].
				"][".round(microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"],4).
				"][".str_repeat("-", self::$_level).">[".$Txt."]";
			error_log($debugTXT);
		}
	}
	
	/**
	 * Re-order the digital index of an array
	 * @param array $anArray
	 */
	static public function reOrderArray(&$anArray){
		$snapShot = array();
		$i=0;
		foreach ($anArray as $value){
			$snapShot[$i++] = $value;
		}
		$anArray = $snapShot;
	}
	/**
	 * This function exists for removing non-printing characters
	 * primarily for a json_encode, to prevent it failure.
	 * 
	 * @param string $string
	 * @return string
	 */
	static public function convert_to_ascii(&$string) 
	{ 
		$string = filter_var($string, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH); 
	}

	/**
	 * This function is for the restore of the parent page variables 
	 * (super globals i.e. _GET _POST _REQUEST) to be exposed to all 
	 * subsequent ajax calls via the superglobals, so that they are context
	 * aware of the current page.
	 * 
	 * This function is part 2 of a two phase transaction, phase 1 is the 
	 * Utils::saveRequestToClientStore() which writes the current var on 
	 * the page load to a client cookie (currVars), and to BlackBox.currVars
	 * as an object with the variables to expose them to all javascsript.
	 * These variables are replaced with each page call. But one is free
	 * to alter this code for persistence.
	 * 
	 * CAUTION: This will replace the current variables in the super globals,
	 * but if you are using an ajax cal that has already been digested, and  
	 * you are sure that all subsequent calls don't need them, then this is
	 * a nice way to re-instantiate a lost state for the current ajax request.
	 */
	static public function restoreVars(){
		
		$restoredVars = $_COOKIE["currVars"];
		$restoredVars = json_decode($restoredVars,true);
		if (!empty($restoredVars) && is_array($restoredVars)){
			$_REQUEST = array_merge($restoredVars, $_REQUEST);
			$_POST = array_merge($restoredVars, $_POST);
			$_GET = array_merge($restoredVars, $_GET);
		}
	}
	
	/**
	 * This is part 1 of the 2 phase transaction to expose all the 
	 * displayed pages variables to all javascript and ajax calls.
	 * It serves to auto-populate superglobals on the server for ajax 
	 * calls to know the context of their request, if needed.
	 * There is a javascript object BlackBox which contains methods and
	 * objects for access. The variables are attached to this object as
	 * the member object "currVars"
	 */
	static public function saveRequestToClientStore(){
		
		$jsonVars = json_encode($_REQUEST);
		echo ("
<script>
$(document).ready(function(){
if (!window.BlackBox) window.BlackBox={};  
window.BlackBox.currVars = ".$jsonVars."; 
$.cookie('currVars', JSON.stringify(BlackBox.currVars), { path: '/' });
});
</script>");
		
	}
	/**
	 * Traverse recursively to utf8 encode the text of array for json encoding
	 * to prevent the Malformed UTF8 error in json
	 * @param unknown $array
	 * @return NULL[]|unknown[]
	 */
	static public function utf8_encode_recursive ($array)
	{
		$result = array();
		foreach ($array as $key => $value)
		{
			if (is_array($value))
			{
				$result[$key] = utf8_encode_recursive($value);
			}
			else if (is_string($value))
			{
				$result[$key] = utf8_encode($value);
			}
			else
			{
				$result[$key] = $value;
			}
		}
		return $result;
	}
	
}
