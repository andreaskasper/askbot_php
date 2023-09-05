<?php

/*
 * Klasse zur direkten Realisation von Observern
 * @author Andreas Kasper (djassi@users.sourceforge.net)
 */
 
 class Observer {
 
	private static $_filters = array();
 
	public static function add($tag = "", $function_name = null, $priority = 10) {
		self::$_filters[$tag][$priority][] = $function_name;
	}
	
	public static function remove ($tag = null, $function_name = null, $priority = null) {
		if ($tag == null) throw new Exception("Tag fehlt");
		if ($priority != null) {
			$k = array_search(self::$_filters[$tag][$priority], $function_name);
			unset(self::$_filters[$tag][$priority][$k]);
			self::$_filters[$tag][$priority] = array_values(self::$_filters[$tag][$priority]);
		} else {
			$keys = array_keys(self::$_filters[$tag]);
			foreach ($keys as $a) self::remove($tag, $function_name, $a);
			return true;
		}
	}
	
	public static function clear ($tag = null, $priority = null) {
		if ($priority != null)  { unset(self::$_filters[$tag][$priority]); return; }
		unset(self::$_filters[$tag]);
		return;
	}
	
	public static function Raise($tag, $data = array()) {
		if (!isset(self::$_filters[$tag])) return true;
		foreach (self::$_filters[$tag] as $prios) {
			foreach ($prios as $events) call_user_func($events, $data);
		}
		return true;
	}
 
 
 
 
 }