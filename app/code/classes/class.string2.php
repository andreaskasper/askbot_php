<?php

class string2 {

	public static function vall($value) {
		if (strpos($value, ",") !== FALSE) {
			$value = str_replace(".", "", $value);
			$value = str_replace(",", ".", $value);
		}
		if ($value + 0 != $value) return "";
		return $value + 0;
	}

}