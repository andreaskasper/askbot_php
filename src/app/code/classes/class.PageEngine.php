<?php

class PageEngine {

	private static $_layout_type = "frontend";
	private static $_skinpriolist = array(10 => "default");
	public static $messages = array();
	public static $_debuglog = array();

	public static function html($key, $params = array(), $cacheID = null, $cachetime = -1) {
		if ($cacheID != null) {
			if ($cacheID == -1) $cacheID = md5($key.serialize($params));
			$a = fcache::read($cacheID, $cachetime);
			if ($a != null) { echo($a); return; }
			ob_start();
		}
		$file  = self::html_find($key);
		if ($file != null) {
			if (defined("debug")) self::$_debuglog["html"][] = array("page" => $key, "file" => $file, "timestamp" => microtime(true));
				include($file); 
				if ($cacheID != null) fcache::write( $cacheID, ob_get_flush());
				return;
		}
		if (defined("debug")) trigger_error("Seite ".$key." kann nicht gefunden werden.", E_USER_WARNING);
	}
	
	
	public static function html_find($key, $extension = ".php") {
		foreach (self::$_skinpriolist as $skin) {
			$local = $_ENV["basepath"]."/app/design/".$skin."/".self::$_layout_type."/".$key.$extension;
			if (file_exists($local)) return $local;
		}
		return null;
	}
	
	
	public static function runController($key, $params = array()) {
		foreach (self::$_skinpriolist as $skin) {
			$local = $_ENV["basepath"]."/app/code/controller/".$skin."/".self::$_layout_type."/".$key.".php";
			if (file_exists($local)) { include($local); return; }
		}
	}
	
	public static function AddMessage($id, $message, $key = null) {
		if ($key == null) self::$messages[$id][] = $message;
		else self::$messages[$id][$key] = $message;
	}
	
	public static function AddErrorMessage($id, $message, $key = null) {
		if ($key == null) self::$messages[$id]["error"][] = $message;
		else self::$messages[$id]["error"][$key] = $message;
	}
	
	public static function AddSuccessMessage($id, $message, $key = null) {
		if ($key == null) self::$messages[$id]["success"][] = $message;
		else self::$messages[$id]["success"][$key] = $message;
	}
	
	
	public static function AddSkin($skinname, $priority = 10) {
		self::$_skinpriolist[$priority] = $skinname;
	}
}