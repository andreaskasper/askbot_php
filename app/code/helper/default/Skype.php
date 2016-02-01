<?php
/**
  *
  *  0 - unknown
  *  1 - offline
  *  2 - online
  *  3 - away
  *  4 - not available
  *  5 - do not disturb
  *  6 - invisible
  *  7 - skype me
  **/

class Skype {

	public static function getStatus($name == "") {
		echo("Hallo");
		if ($name == null OR $name."" == "") return 0;
		$web = WebCache::get("http://mystatus.skype.com/".$name.".num", 300);
		return $web+0;
	}



}