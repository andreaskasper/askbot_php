<?php

class i18n {

	private static $_locale = null;
	private static $_data = array();
	private static $_plural_func = null;
	
	public static function init($lang, $domain = "frontend") {
		/*self::$_locale = setlocale( LC_MESSAGES, 	$lang.".utf8",
                $lang.".UTF8",
                $lang.".utf-8",
                $lang.".UTF-8",
                $lang);
		putenv("LANG=".$lang."");
		bindtextdomain( $domain, $_ENV["basepath"].'/locales/');
		bind_textdomain_codeset($domain, "UTF-8");
		return true;*/
		$cacheID = "SprachLocale_".$lang."_".$domain;
		self::$_data = fcache::read($cacheID, 86400*30);
		if (self::$_data == null) {
			self::$_data = self::convertPOFile($lang, $domain);
			fcache::write($cacheID, self::$_data);
		}
		return true;
	}
	
	public static function gettext($txt) {
		return (string)(isset(self::$_data["trans"][$txt]) ? self::$_data["trans"][$txt] : $txt);
	}
	
	public static function ngettext($txt, $number = 1) {
		if (self::$_plural_func == null) {
			if (!isset(self::$_data["plural"]["func"])) return $txt;
			self::$_plural_func = "return (".self::$_data["plural"]["func"].");";
		}
		$a = create_function("\$n", self::$_plural_func);
		$b = $a($number)+0;
		return (isset(self::$_data["trans"][$txt][$b]) ? self::$_data["trans"][$txt][$b] : $txt);
	}
	
	private static function convertPOFile($lang = "de_DE", $layout = "frontend") {
		$handle = @fopen($_ENV["basepath"]."/locales/".$lang."/LC_MESSAGES/".$layout.".po", "r");
		if (!$handle) return false;
		$data = array();
		while (($buffer = fgets($handle, 4096)) !== false) {
			if (preg_match('`msgid "(.*?)"`', $buffer, $treffer)) { $key = $treffer[1]; }
			if (preg_match('`msgstr "(.+?)"`', $buffer, $treffer)) { $data["trans"][$key] = $treffer[1]; }
			if (preg_match('`msgstr\[([0-9]+)\] "(.+?)"`', $buffer, $treffer)) { $data["trans"][$key][$treffer[1]] = $treffer[2]; }
			if (preg_match('`Plural\-Forms\: nplurals\=([0-9]+)\; plural=(.*?)\;`', $buffer, $treffer)) {
				$data["plural"]["num"] = $treffer[1]+0;
				$data["plural"]["func"] = str_replace('n', "\$n", $treffer[2]);
			}
		}
		return $data;
	}
	
	public static function number_format($value = 0, $decimals = 2) {
		return number_format($value, $decimals, ",", ".");
	}
	
	public static function money_format($value = 0, $currency = "EUR") {
		return sprintf(_h("%01.2f\"{currency}\"", array("currency" => $currency)), $value+0);
	}
	


}


//default functions
function _e($txt, $array = array()) {
	return htmlentities(_h($txt, $array),3,"UTF-8");
}

function _h($txt, $array = array()) {
	if (strpos( $txt, "{n}") !== FALSE) $m = i18n::ngettext( $txt, $array["n"]);
	else $m = i18n::gettext($txt);
	foreach ($array as $key => $value) $m = str_replace("{".$key."}", $value, $m);
	$m = preg_replace("`\{\#.*?\}`","", $m);
	return $m;
}