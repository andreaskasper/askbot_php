<?php

//Infos via http://code.google.com/intl/de-DE/apis/urlshortener/v1/getting_started.html

class API_urlshortener {
	
	public static function add($data) {
		if (is_string($data)) $data = array("url" => $data);
		$ch = curl_init('https://www.googleapis.com/urlshortener/v1/url');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
		
		curl_setopt($ch, CURLOPT_POSTFIELDS, '{"longUrl": "'.$data["url"].'"}');
		
		$response = curl_exec($ch);
		$v = json_decode($response);
		return $v->id;
	}
	
	public static function info($data) {
		if (is_string($data)) $data = array("id" => $data);
		$d = json_decode(file_get_contents("https://www.googleapis.com/urlshortener/v1/url?projection=FULL&shortUrl=http://goo.gl/".$data["id"]));
		return self::object2array($d);
	}
	
	public static function getlongurl($data) {
		$d = self::info($data);$d = self::info($data);
		return $d["longUrl"];
	}
	
	public static function getclicks($data) {
		$d = self::info($data);
		return $d["analytics"]["allTime"]["shortUrlClicks"]+0;
	}
	
	public static function ismalware($data) {
		$d = self::info($data);
		return ($d["status"] == "MALWARE");
	}
	
	public static function object2array($obj) { 
    $_arr = is_object($obj) ? get_object_vars($obj) : $obj; 
    foreach ($_arr as $key => $val) { 
        $val = (is_array($val) || is_object($val)) ? self::object2array($val) : $val; 
        $arr[$key] = $val; 
    } 
    return $arr; 
	}
	


}


