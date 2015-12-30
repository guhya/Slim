<?php
namespace app\util;

class Util
{
	public function isProbablyMobile()
	{
		$arrMobile = array("iPhone", "iPod", "iPad", "BlackBerry", "Android", "Windows CE", "LG", "MOT", "SAMSUNG", "SonyEricsson");
		$isMobile  = false;
		foreach($arrMobile as $v){
			if(strpos($_SERVER["HTTP_USER_AGENT"], $v) !== false){
				$isMobile	= true;
				break;
			}
		}
		
		return $isMobile;
	}	
	
	/**
	 * Perform parallel cURL request.
	 *
	 * @param array $urls Array of URLs to make request.
	 * @param array $options (Optional) Array of additional cURL options.
	 * @return mixed Results from the request (if any).
	 */
	public function curlMultiRequest($urls, $options = array()) {
		$ch = array();
		$results = array();
		$mh = curl_multi_init();
		foreach($urls as $key => $val) {
			$ch[$key] = curl_init();
			if ($options) {
				curl_setopt_array($ch[$key], $options);
			}
			
			curl_setopt($ch[$key], CURLOPT_URL, $val);
			curl_setopt($ch[$key], CURLOPT_RETURNTRANSFER, true);
			
			curl_multi_add_handle($mh, $ch[$key]);
		}
		$running = null;
		do {
			curl_multi_exec($mh, $running);
		}
		while ($running > 0);
		
		// Get content and remove handles.
		foreach ($ch as $key => $val) {
			$results[$key] = curl_multi_getcontent($val);
			curl_multi_remove_handle($mh, $val);
		}
		
		curl_multi_close($mh);
		return $results;
	}	

	public function slugify($text)
	{
		$text = preg_replace('~[^\\pL\d]+~u', '-', $text);
		$text = trim($text, '-');
		$text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
		$text = strtolower($text);
		$text = preg_replace('~[^-\w]+~', '', $text);
		if (empty($text))
		{
			return 'n-a';
		}
		return $text;
	}
	
	public function arrayOrderBy()
	{
	    $args = func_get_args();
	    
	    if(!is_array($args)) return array();
	     
	    $data = array_shift($args);
	    foreach ($args as $n => $field) {
	        if (is_string($field)) {
	            $tmp = array();
	            foreach ($data as $key => $row)
	                $tmp[$key] = $row[$field];
	            $args[$n] = $tmp;
	            }
	    }
	    $args[] = &$data;
	    call_user_func_array('array_multisort', $args);
	    return array_pop($args);
	}
	
	function makePostRequest($url, $data){
		$data_string = "";
		$datacount = 0;
		foreach($data as $key=>$value){
			$data_string  .= $key.'='.urlencode($value).'&';
			$datacount++;
		}
		rtrim($data_string , '&');	
		
		$ch = curl_init();
		
	
		curl_setopt($ch, CURLOPT_HEADER, false);
		//curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		//curl_setopt($ch, CURLOPT_HTTPGET, 1);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, $datacount);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
		
		$output = curl_exec($ch);		
		curl_close($ch);			
	
		return $output;
	}
	
	function shuffle_assoc($list) {
		if (!is_array($list)) return $list;
	
		$keys = array_keys($list);
		shuffle($keys);
		$random = array();
		foreach ($keys as $key)
			$random[$key] = $list[$key];
	
		return $random;
	}	
}