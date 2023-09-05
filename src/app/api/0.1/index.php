<?php
/* Offene anprogrammierbare Schnittstelle für die HASTUschon API.
 * Diese kann im Vergelich zur früheren Version nun auch direkt andere Methoden innerhalb von php aufrufen, ohne dass ein Umweg über die IP's gegangen werden muss.
 * 
 * TODO:
 * o Idee für Caching überlegen.
 * 
 * @Version 0.01.11062808
 */

error_reporting(E_ERROR); //Nur Fehler ausgeben, keine Notices

$_ENV["API"]["version"] = 0.1;
$_ENV["API"]["state"] = "alpha";

function __autoload($class_name) {
	if (substr($class_name,0,4) == "API_") {
		require_once($_ENV["basepath"]."/api/".$_ENV["API"]["version"]."/classes/".substr($class_name,4,999).".php");
		return true;
	}
	$prio[] = $_ENV["basepath"]."/api/".$_ENV["API"]["version"]."/classes/".$class_name.".php";
	$prio[] = $_ENV["basepath"]."/classes/class.".$class_name.".php";

	foreach ($prio as $file) {
		if (file_exists($file)) {
			require_once($file);
			return true;
		}
	}
	//die("fuck Class.".$class_name);
	return false;
}

$pgmstart = microtime(true);
$_ENV["basepath"] = dirname(dirname(dirname(__FILE__)));
$_REQUEST = array_merge($_GET, $_POST); //Damit wir das $_COOKIE-Monster erledigt.
include($_ENV["basepath"]."/config.settings.php");
include($_ENV["basepath"]."/inc.global.functions.php");
require_once($_ENV["basepath"]."/classes/class.SQL.php");
error_reporting(E_ERROR); //Nur Fehler ausgeben, keine Notices

session_set_cookie_params( 3600, "/", ".hastuschon.de", false, false);
session_cache_limiter("public");
session_name("ui");
session_start();
//require_once($_ENV["asicms"]["path"].'modules/mod.standard.php');
$db = new SQL(0);

if (isset($_REQUEST["apikey"]) && $_REQUEST["apikey"] != "") {
	//Ist der APIKey korrekt?
}

$g = explode("|", str_replace(array(".","/"),"|",isset($_REQUEST["action"]) ? $_REQUEST["action"] : ""));
if (!file_exists("classes/".strtolower($g[0]).".php")) Send(array("err" => array("id" => 100, "msg" => "Unbekannte Bibliothek")));
require_once("classes/".strtolower($g[0]).".php");
if (substr($g[0],-1) == "0") Send(array("err" => array("id" => 101, "msg" => "Unzulässige Bibliothek")));
//if (!method_exists(strtolower($g[0]), strtolower($g[1]))) Send(array("err" => array("id" => 102, "msg" => "Unbekannte Methode"))); //Weg sonst geht die Sicherung nicht.


if (class_exists("API_".$g[0])) {
	if (!in_array(strtolower($g[1]), get_class_methods("API_".strtolower($g[0])))) Send(array("err" => array("id" => 102, "msg" => "Unbekannte Methode")));
	try {
		$result = call_user_func(array("API_".strtolower($g[0]), strtolower($g[1])), $_REQUEST);
	} catch (APIException $ex) {
		unset($_ENV["APIExpires"]);
		$o = array();
		$o["err"]["id"] = $ex->getCode();
		$o["err"]["msg"] = $ex->getMessage();
		Send($o); exit(1);
	}
} else {
	if (!in_array(strtolower($g[1]), get_class_methods(strtolower($g[0])))) Send(array("err" => array("id" => 102, "msg" => "Unbekannte Methode")));
	try {
		$result = call_user_func(array(strtolower($g[0]), strtolower($g[1])), $_REQUEST);
	} catch (APIException $ex) {
		unset($_ENV["APIExpires"]);
		$o = array();
		$o["err"]["id"] = $ex->getCode();
		$o["err"]["msg"] = $ex->getMessage();
		Send($o); exit(1);
	}
}
Send(array("result" => $result));

function Send($data) {
	global $pgmstart,$wgXMLRoot;
	if (!is_array($data)) $data = array("result" => $data);
	if (!isset($data["err"])) { $data["err"]["id"] = 0; $data["err"]["msg"] = ""; }
	$data["runtime"] = microtime(true)-$pgmstart;
	if (isset($_ENV["APIExpires"])) {
				header("Pragma: public");
				header("Cache-Control: maxage=".($_ENV["APIExpires"]+0));
				header('Expires: ' . gmdate('D, d M Y H:i:s', time()+$_ENV["APIExpires"]) . ' GMT');
			}
	switch(strtolower(isset($_REQUEST["format"])? $_REQUEST["format"] : "" )) {
		case "successcode": 
			header("Content-Type: text/plain");
			if ($data["err"]["id"]+0 == 0) @header($_SERVER["SERVER_PROTOCOL"]." 200 Ok"); else header($_SERVER["SERVER_PROTOCOL"]." 400 ".$data["err"]["msg"]);
			echo($data["err"]["id"]+0); break;
		case "json": 
			header("Content-Type: application/json");
			$data["Request"] = $_REQUEST;
			echo(json_encode($data)); break;
		case "jsonac": 
			header("Content-Type: application/json");
			echo(json_encode($data["result"])); break;
		case "json-in-script":
			header("Content-Type: text/javascript");
			$data["Request"] = $_REQUEST;
			if ($_REQUEST["callback"]."" == "") $_REQUEST["callback"] = str_replace(array("/"), "_", $_REQUEST["action"]);
			echo(strip_tags($_REQUEST["callback"])."(".json_encode($data).");"); break;
		case "php":
			$data["Request"] = $_REQUEST;
			echo(serialize($data)); break;			
		case "html":
			$data["Request"] = $_REQUEST;
			echo('<table><tr><td>'.Array2HTML($data).'</td></tr></table>'); break;
		case "plain":
			header("Content-Type: text/plain; charset=utf-8");
			if ($data["err"]["id"]+0 == 0) @header($_SERVER["SERVER_PROTOCOL"]." 200 Ok"); else header($_SERVER["SERVER_PROTOCOL"]." 400 ".$data["err"]["msg"]);
			if ($data["err"]["id"] != 0) die("ERR:".$data["err"]["id"].";".$data["err"]["msg"]);
			if (is_array($data["result"])) { foreach($data["result"] as $a) echo((string)$a."\r\n"); break;}
			echo((string)$data["result"]); break;
		case "xml":
		default: 
			header("Content-Type: text/xml");
			$data["Request"] = $_REQUEST;
			echo(ArrayToXML::toXml($data, $wgXMLRoot));
//			echo(Array2XML($data, true));
			break;
	}
	exit(1);
}

class ArrayToXML {
	/**
	 * The main function for converting to an XML document.
	 * Pass in a multi dimensional array and this recrusively loops through and builds up an XML document.
	 *
	 * @param array $data
	 * @param string $rootNodeName - what you want the root node to be - defaultsto data.
	 * @param SimpleXMLElement $xml - should only be used recursively
	 * @return string XML
	 */
	public static function toXml( $data, $rootNodeName = "data", SimpleXMLElement $xml = null) {
		if ($xml == null) $xml = simplexml_load_string("<?xml version='1.0' encoding='utf-8'?><data />");
		foreach($data as $key => $value) {
			// no numeric keys in our xml please!
			if (is_numeric($key)) $key = "item". (string) $key;
			//$key = preg_replace('/[^a-z]/i', '', $key);
			if (is_array($value)) {
				$node = $xml->addChild($key);
				self::toXml($value, (string)$rootNodeName, $node);
			} else {
				if (is_bool($value)) {
					if ($value) $value="true"; else $value="false";
				}
				$value = htmlentities($value);
				$xml->addChild($key, $value);
			}
		}
		return $xml->asXML();
	}
}

function Array2HTML( array $array) {
	$out = '<table border="1" cellspacing="0" width="100%">'.chr(13);
	foreach ($array as $key=>$value) {
		$out .= '<tr><th>'.$key.'</th><td>';
		if (is_array($value)) $out .= Array2HTML($value); else $out .= htmlentities($value, 3, "UTF-8");
		$out .= '</td></tr>'.chr(13);
	}
	$out .= '</table>'.chr(13);
	return $out;
}

function xmlstring($txt) {
	$a = array("<",">","&");
	$b = array("&lt;","&gt;", "&amp;");
	return str_replace($a,$b,$txt);
}

class APIException extends Exception {
}


?>
