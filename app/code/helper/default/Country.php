<?php

class Country {

	public static $_countries = array( "deu" => array("Germany", "Deutschland"),
										"fra" => array("France", "Français"),
										"usa" => array("U.S.A.", "United Staates of Amerika")
										);
										
	public static $_locales = array( 	"de_DE" => array("German (Germany)", "Deutsch (Deutschland)"),
										"de_AU" => array("German (Austria)", "Deutsch (Österreich)"),
										"fr_FR" => array("", "Français"),
										"en_US" => array("english (U.S.A.)", "english (U.S.A.)"),
										);
										
	public static function getCountryName($id) {
		return self::$_countries[strtolower($id)][0];
	}

}