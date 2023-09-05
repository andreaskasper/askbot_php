<?php

class URL {

	public static function addVar($arr = array(), $value = null) {
		if (!is_array($arr)) $arr = array((string)$arr => $value);
		$b = array_merge($_GET, $arr);
		return "?".http_build_query($b);
	}

}