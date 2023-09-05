<?php

//Infos via http://code.google.com/intl/de-DE/apis/urlshortener/v1/getting_started.html

class API_facebook {
	
	public static function statistic($data) {
		if (is_string($data)) $data = array("url" => $data);
		$str = WebCache::get("http://api.facebook.com/method/fql.query?format=json&query=select%20%20url,like_count,%20total_count,%20share_count,%20click_count%20from%20link_stat%20where%20url%20=%20%22".urlencode($data["url"])."%22",40000, "}");
		$d = json_decode($str, true);
		return $d[0];
	}
	


}


