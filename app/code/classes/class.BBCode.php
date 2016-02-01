<?php

class BBCode {

	public static $ignoreColor = false;

	private static $_simple_tags = array("b" => array("@\[b\](.*?)\[\/b\]@", "<b>$1</b>"),
								         "i" => array("@\[i\](.*?)\[\/i\]@", "<i>$1</i>"));
										 
	private static $CacheCodeString = array();

	public static function render($text, $echo = false) {
		$text = preg_replace_callback( "`\[code(\=([A-Za-z]+))?\](.*?)\[\/code\]`s", array("self", "renderCode"), $text);
		$out = htmlentities($text, 3, "UTF-8");
		
		$out = str_replace(array(chr(10),chr(13).chr(13)), array("", '</p><p>'), $out);
		$out = str_replace(array(chr(10),chr(13)), array("", '<br/>'), $out);
		
		
		
		$out = preg_replace_callback( "`\[url\=([^\"\]]+)\](.*?)\[\/url\]|(http[s]?\:\/\/.*?)([\s]|$)`s", array("self", "renderURLSpecial"), $out);
		
		$out = preg_replace_callback( "`\[url\=([^\"\]]+)\](.*?)\[\/url\]`s", array("self", "renderURL"), $out);
		$out = preg_replace_callback( "`\[youtube\=([A-Za-z0-9\-\_]{6,})[\/]?\]`", array("self", "renderYouTube"), $out);
		$out = preg_replace_callback( "`\[code(\=([A-Za-z]+))?\](.*?)\[\/code\]`", array("self", "renderCode"), $out);
		$out = preg_replace_callback( "`\[list(=1)?\](.*?)\[\/list\]`s", array("self", "renderAufzaehlung"), $out);
		$out = preg_replace_callback( "`\[img\](.*?)\[\/img\]`", array("self", "renderImage"), $out);
		$out = preg_replace_callback( "`\[color\=(\#[A-Fa-f0-9]{6}|[A-Za-z]+)\](.*?)\[\/color\]`", array("self", "renderColor"), $out);
		$out = preg_replace_callback( "`\[tweet\]([0-9]+)\[\/tweet\]`", array("self", "renderTweet"), $out);
		//$out = preg_replace_callback( "`\[imgur=([a-zA-Z0-9]+)\]`", array("self", "renderImgurPicture"), $out);
		
		foreach (self::$_simple_tags as $a) $out = preg_replace( $a[0], $a[1], $out);
		
		$out = preg_replace_callback( "`\[code\=([0-9]+)\]`", array("self", "renderCode2"), $out);
		
		if ($echo) {echo('<p>'.$out.'</p>'); return; } else return '<p>'.$out.'</p>';
	}
	
	private static function renderYouTube($matches) {
		return '<iframe width="400" height="275" src="http://www.youtube.com/embed/'.$matches[1].'?rel=0" frameborder="0" allowfullscreen style="display:block; margin: 5px auto;"></iframe>';
	}
	
	private static function renderURL($matches) {
		$matches[1] = str_replace('"', '', $matches[1]);
		return '<a class="outlink" href="'.$matches[1].'" target="_blank" rel="nofollow">'.$matches[2].'</a>';
	}
	
	private static function renderURLSpecial($matches) {
		if ($matches[1] > "") $url = $matches[1];
		if (isset($matches[3]) && $matches[3] > "") $url = $matches[3];
		
		if (preg_match("`http://vimeo.com/([0-9]+)`", $url, $treffer)) return '<iframe src="http://player.vimeo.com/video/9379302?title=0&amp;byline=0&amp;portrait=0&amp;color=ab671f" width="500" height="281" frameborder="0" webkitAllowFullScreen mozallowfullscreen allowFullScreen></iframe>';
		return $matches[0];
	}
	
	private static function renderAufzaehlung($matches) {
		//$matches[1] = preg_replace("`\[\*\](.*?)$`s", '<li>$1</li>', $matches[1]);
		$g = explode("[*]", $matches[2]);
		$out = "";
		for ($i = 1; $i < count($g); $i++) {
			$out .= '<li>'.$g[$i].'</li>';
		}
		if ($matches[1] == "=1") return '<ol>'.$out.'</ol>';
		return '<ul>'.$out.'</ul>';
	}
	
	private static function renderImage($matches) {
		return '<span class="embedded image" href="'.html($matches[1]).'"><img  src="'.html($matches[1]).'" alt="Forums Bild"/></span>';
	}
	
	private static function renderTweet($matches) {
		$a = WebCache::get("http://api.twitter.com/1/statuses/oembed.json?align=none&id=".$matches[1],86400,array("}"));
		$b = json_decode($a, true);
		return $b["html"];
	}
	
	/*private static function renderImgurPicture($matches) {
		return '<span class="embedded image" href="'.html($matches[1]).'"><img  src="'.html($matches[1]).'" alt="Forums Bild"/></span>';
		//http://api.imgur.com/2/image/:HASH
	}*/

	
	public static function strip($text) {
		return preg_replace("`\[.*?\]`", "", $text);
	}
	
	private static function renderCode($matches) {
		while (substr($matches[3],0,1) == chr(13) OR substr($matches[3],0,1) == chr(10)) $matches[3] = substr($matches[3],1);
		while (substr($matches[3],-1) == chr(13) OR substr($matches[3],-1) == chr(10)) $matches[3] = substr($matches[3],0, strlen($matches[3])-1);
		require_once(dirname(__FILE__)."/geshi/geshi.php");
		$matches[3] = str_replace("</code>", "<|code>", $matches[3]);
		$a = count(self::$CacheCodeString);
		switch (strtolower($matches[2])) {
			case "php":
			case "xml":
			case "java":
			case "javascript":
			case "ada":
			case "lua":
			case "yaml":
				$geshi = new GeSHi($matches[3], $matches[2]);
				$geshi->enable_line_numbers(GESHI_FANCY_LINE_NUMBERS);
				self::$CacheCodeString[] = '<div class="code GeSHi '.$matches[2].'">'.$geshi->parse_code().'</div>';
				break;
			default:
				self::$CacheCodeString[] = '<div class="code">'.nl2br(html($matches[3])).'</div>';
		}
		return '[code='.$a.']';
	}
	
	private static function renderColor($matches) {
		print_r($matches); exit(1);
		if (self::$ignoreColor) return $matches[1];
		return '<span style="color:'.$matches[1].';">'.$matches[2].'</span>';
	}
	
	private static function renderCode2($matches) {
		return self::$CacheCodeString[$matches[1]+0];
	}
	
}