<?php

/**
  *
  * Karma-Rechte
  * 15	Positiv bewerten
50	Kommentare hinzufügen
100	Negativ bewerten
50	Akzeptiere eigene Antworten auf eigene Fragen
250	Eigene Fragen öffnen und schließen
500	Retag andere Fragen
750	Community-Wiki Fragen beantworten
2000	Ändere andere Antworten
3000	Lösche andere Kommentare
*/

class Karma {

	public static function add($user, $msgid, $points = 0, $question = null) {
		$db = new SQL(0);
		$w = array();
		$w["user"] = $user;
		$w["msgid"]	= $msgid;
		$w["points"] = $points;
		if ($question != null) $w["question"] = $question;
		$w["created"] = time();
		$db->CreateUpdate(0, "karma_log", $w);
		$db->cmd(0, 'UPDATE user_list SET karma = (SELECT sum(points) FROM karma_log WHERE user = user_list.id) WHERE id={0} LIMIT 1', true, array($user));
		return true;
	}
	
	public static function RuleAction($key, $params = array()) {
		switch ($key) {
			case "CREATE_QUESTION":
				return self::add($params["user"], 1, 1, $params["question"]);
			case "CREATE_ANSWER":
				return self::add($params["user"], 2, 1, $params["question"]);
			case "VOTEUP_QUESTION":
				return self::add($params["user"], 3, 10, $params["question"]);
			case "VOTEDOWN_QUESTION":
				return self::add($params["user"], 4, -2, $params["question"]);
			case "VOTEUP_ANSWER":
				return self::add($params["user"], 5, 10, $params["question"]);
			case "VOTEDOWN_ANSWER":
				return self::add($params["user"], 6, -2, $params["question"]);
			case "ACCEPT_ANSWER":
				return self::add($params["user"], 6, 20, $params["question"]);
			case "UNACCEPT_ANSWER":
				return self::add($params["user"], 6, -20, $params["question"]);
			default:
				die("Unbekannte Kamra-Regel: ".$key);
		}
	}
}