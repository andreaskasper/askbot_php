<?php/**   * Grundfunktionen um die API zu durchsuchen und Informationen über Namespaces und Methoden zu erfahren.  * @author Andreas Kasper (Andreas.Kasper@hastuschon.de)  * @version 0.91.110602  */class API_core {	/**	  * Liefert eine Liste aller Namespaces. Diese können dann mit core.methods weiter durchsucht werden.	  * @return array(string) Liste aller Namespaces.	  */	public static function namespaces() {		$handle = opendir(dirname(__FILE__));		while (false !== ($file = readdir($handle))) {			if (substr($file,-4) != ".php") continue;			$o[] = substr($file,0,strlen($file)-4);		}		closedir($handle);		return $o;	}		/**	  * Liefert die Methoden eines Namespaces. Diese können dann z.B. mit core.methodinfo weiter aufgeschlüsselt werden.	  * @param string namespace Der Namespace der durchsucht werden soll.	  * @return array(string) Liste der Methoden innerhalb des Namespaces	  */	public static function methods($data) {		if ($data["namespace"]."" == "") throw new APIException("Parameterfehler (namespace)", 100);		$data["namespace"] = strtolower($data["namespace"]);		if (!file_exists(dirname(__FILE__)."/".$data["namespace"].".php")) throw new APIException("Unbekannter Namespace", 101);		require_once(dirname(__FILE__)."/".$data["namespace"].".php");		$rows = get_class_methods($data["namespace"]);		return $rows;	}		/**	 * Liefert Informationen über einen Namespace.	 * @param string namespace Name des Namespaces	 * @return array(string) Informationen	 */	public static function namespaceinfo($data) {		if ($data["namespace"]."" == "") throw new APIException("Parameterfehler (namespace)", 100);		$data["namespace"] = strtolower($data["namespace"]);		if (!file_exists(dirname(__FILE__)."/".$data["namespace"].".php")) throw new APIException("Unbekannter Namespace", 101);		require_once(dirname(__FILE__)."/".$data["namespace"].".php");		$rc = new ReflectionClass($data["namespace"]);		return self::InfoByComment($rc->getDocComment());	}		/**	 * 	 * Liefert Informationen über eine Methode, diese kann dazu verwendet werden, die Methode zu verstehen.	 * @param string namespace Name des Namespace	 * @param string method Name der zu prüfenden Methode	 * @return array(string) Informationen über die Methode	 */	public static function methodinfo($data) {		if ($data["namespace"]."" == "") throw new APIException("Parameterfehler (namespace)", 100);		if ($data["method"]."" == "") throw new APIException("Parameterfehler (method)", 101);		$data["namespace"] = strtolower($data["namespace"]);		if (!file_exists(dirname(__FILE__)."/".$data["namespace"].".php")) throw new APIException("Unbekannter Namespace", 105);		require_once(dirname(__FILE__)."/".$data["namespace"].".php");		$rc = new ReflectionClass($data["namespace"]);		foreach($rc->getMethods() as $m) {			if ($m->name == strtolower($data["method"])) return self::InfoByComment($m->getDocComment());		} 		throw new APIException("Unbekannte Methode", 106);	}		public static function SapiCall($action, $data) {		$data["action"] = $action;		$data2 = http_build_query($data,'','&');				$req .= "POST ".$_SERVER["SCRIPT_NAME"]." HTTP/1.1\r\n";		$req .= "Host: ".$_SERVER["HTTP_HOST"]."\r\n";		$req .= "User-Agent: Mozilla/4.0\r\n";		$req .= "Accept: image/gif, image/jpeg, *"."/"."*\r\n";		$req .= "Content-type: application/x-www-form-urlencoded\r\n";		$req .= "Content-length: ".strlen($data2)."\r\n";		$req .= "Connection: Close\r\n";		$req .= "\r\n";		$req .= $data2;				$fp = fsockopen($_SERVER["HTTP_HOST"], 80, $errno, $errstr);		if (!$fp) throw new APIException("Fehler bei Multithreading", 50);		fputs($fp, $req);		//TODO: Hier fehlt noch ein wenig!		print_r($req);		//exec("start wget ".);		die($url);		print_r($_SERVER); exit(1);	}		/**	 * 	 * Gibt die aktuelle Version der OpenAPI zurück	 * @return number Versionsnummer	 */	public static function version() {		return $_ENV["API"]["version"]+0;	}	/**	 * 	 * Gibt den Lifecycle-Status der OpenAPI-Version zurück (alpha, beta, stable, deprecated)	 * @return string Versionsstatus	 */	public static function versionstate() {		return $_ENV["API"]["state"];	}		/**	 * 	 * Ermittelt aus einem Kommentar die relevanten Informationen und gibt diese als Array zurück	 * @param string $txt Der zu analysierende Text	 * @return array(string) Informationen	 */	public static function InfoByComment($txt) {		//error_reporting(10);		$o = array("desc" => "", "param" => array(), "return" => array());		$rows = explode(chr(13), str_replace(array(chr(10)), chr(13),$txt));		foreach ($rows as $row) {			$row = trim($row);			if (preg_match("/^[\/]?[\*]+ ([\@][\S]+[\s])?(.*?)$/i", $row, $treffer)) {				if ($treffer[1]."" == "") {					$o["desc"] .= $treffer[2].chr(13);				} else switch (trim(strtolower($treffer[1]))) {					case "@return":						$w = explode(" ",$treffer[2]);						$o["return"]["type"] = $w[0];						unset($w[0]);						$o["return"]["desc"] = implode(" ", $w);						break;					case "@param":						$w = explode(" ",$treffer[2]);						$w2 = $w; unset($w2[0],$w2[1]);						$o["param"][$w[1]]["type"] = str_replace("$","",$w[0]);						$o["param"][$w[1]]["desc"] = implode(" ", $w2);						break;					case "@author":						$o["author"][] = trim($treffer[2]);						break;					case "@deprecated":						$o["deprecated"] = true;						break;					case "@link":						$o["links"][] = trim($treffer[2]);						break;					case "@version":						$o["version"] = trim($treffer[2]);						break;				}			}		}		//print_r($rows);		//for ($i = 0; $i < strlen($txt); $i++) {$z = substr($txt,$i,1); echo($z." ".ord($z)."<br/>".chr(13)); }		return $o;	}}