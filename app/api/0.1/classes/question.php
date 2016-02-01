<?php

class API_question {

	public static function voteup($data) {
		$out = array();
		if (!MyUser::isloggedin()) throw new APIException("User ist nicht angemeldet.", 100);
		if (MyUser::getKarmaPoints() < 5) throw new APIException("Du benötigst 5 Karma-Punkte um einen positiven Vote zu geben.", 200);
		if (!isset($data["question"])) throw new APIException("Benötigter Parameter fehlt (question).", 50);
		$db = new SQL(0);
		$row = $db->cmdrow(0, 'SELECT * FROM questions WHERE id={0}', array($data["question"]+0));
		if (!isset($row["id"])) throw new APIException("Diese Frage existiert nicht (mehr)", 300);
		if ($row["author"] == MyUser::id()) throw new APIException("Sie dürfen nicht auf Ihre eigene Frage voten", 301);
		$raw = $db->cmdrow(0, 'SELECT * FROM question_votes WHERE question={0} AND user={1} LIMIT 0,1', array($data["question"]+0, MyUser::id()));
		$w = array();
		$w["question"] = $data["question"]+0;
		$w["user"] = MyUser::id();
		$w["vote"] = 1;
		$db->CreateUpdate(0, "question_votes", $w);
		$db->cmd(0, 'UPDATE questions as T1 SET count_votes = (SELECT sum(vote) FROM question_votes WHERE question=T1.id) WHERE id={0} LIMIT 1', false, array($w["question"]));
		$out["sumvotes"] = self::getVotes(array("question" => $w["question"]));
		if (!isset($raw["id"])) Karma::RuleAction("VOTEUP_QUESTION", array("user" => $row["author"], "question" => $w["question"]));
		
		if ($db->cmdvalue(0,'SELECT count(*) FROM question_votes WHERE vote="1" AND question={0}', array($row["id"])) == 3) Badges::add(24, $row["author"], array("question" => $row["id"])); //Gute Frage (Silber) 3 positive Votes
		return $out;
	}
	
	public static function votedown($data) {
		$out = array();
		if (!MyUser::isloggedin()) throw new APIException("User ist nicht angemeldet.", 100);
		if (MyUser::getKarmaPoints() < 100) throw new APIException("Du benötigst 100 Karma-Punkte um einen negativen Vote zu geben.", 200);
		if (!isset($data["question"])) throw new APIException("Benötigter Parameter fehlt (question).", 50);
		$db = new SQL(0);
		$row = $db->cmdrow(0, 'SELECT * FROM questions WHERE id={0}', array($data["question"]+0));
		if (!isset($row["id"])) throw new APIException("Diese Frage existiert nicht (mehr)", 300);
		if ($row["author"] == MyUser::id()) throw new APIException("Sie dürfen nicht auf Ihre eigene Frage voten", 301);
		$raw = $db->cmdrow(0, 'SELECT * FROM question_votes WHERE question={0} AND user={1} LIMIT 0,1', array($data["question"]+0, MyUser::id()));
		$w = array();
		$w["question"] = $data["question"]+0;
		$w["user"] = MyUser::id();
		$w["vote"] = -1;
		$db->CreateUpdate(0, "question_votes", $w);
		$db->cmd(0, 'UPDATE questions as T1 SET count_votes = (SELECT sum(vote) FROM question_votes WHERE question=T1.id) WHERE id={0} LIMIT 1', false, array($w["question"]));
		$out["sumvotes"] = self::getVotes(array("question" => $w["question"]));
		if (!isset($raw["id"])) Karma::RuleAction("VOTEDOWN_QUESTION", array("user" => $row["author"], "question" => $w["question"]));
		Badge::add(9, MyUser::id(), array("question" => $w["question"])); //Kritiker: für downvote
		return $out;
	}
	
	public static function getVotes($data) {
		if (!isset($data["question"])) throw new APIException("Benötigter Parameter fehlt (question).", 50);
		$db = new SQL(0);
		return $db->cmdvalue(0, 'SELECT count_votes FROM questions WHERE id={0}', array($data["question"]+0));
	}
	
	public static function close($data) {
		if (!isset($data["question"])) throw new APIException("Benötigter Parameter fehlt (question).", 50);
		if (!MyUser::isloggedin()) throw new APIException("User ist nicht angemeldet.", 100);
		if (MyUser::getKarmaPoints() < 250) throw new APIException("Du benötigst 250 Karma-Punkte um eine Frage zu schliessen.", 200);
		$db = new SQL(0);
		$db->cmd(0, 'UPDATE questions SET is_closed="1" WHERE id={0} LIMIT 1', true, array($data["question"]));
		return true;
	}
	
	public static function reopen($data) {
		if (!isset($data["question"])) throw new APIException("Benötigter Parameter fehlt (question).", 50);
		if (!MyUser::isloggedin()) throw new APIException("User ist nicht angemeldet.", 100);
		if (MyUser::getKarmaPoints() < 250) throw new APIException("Du benötigst 250 Karma-Punkte um eine Frage zu öffnen.", 200);
		$db = new SQL(0);
		$db->cmd(0, 'UPDATE questions SET is_closed="0" WHERE id={0} LIMIT 1', true, array($data["question"]));
		return true;
	}
	
	public static function setbounty($data) {
		if (!MyUser::isloggedin()) throw new APIException("User ist nicht angemeldet.", 100);
		$data["karma"] = floor(string2::vall($data["karma"]+0));
		$data["bitcoin"] = string2::vall($data["bitcoin"]+0);
		$data["EUR"] = string2::vall($data["EUR"]+0);
		
		$db = new SQL(0);
		if ($data["karma"] > 0) {
			if (MyUser::getKarmaPoints() < 75) throw new APIException("Du benötigst 75 Karma-Punkte um eine Karma Bounty zu geben.", 200);
			if (MyUser::getKarmaPoints() < $data["karma"]+0) throw new APIException("Du hast nur ".MyUser::getKarmaPoints()." Karma Punkte zu verschenken!", 200);
			$w = array();
			$w["question"] = $data["question"]+0;
			$w["user"] = MyUser::id();
			$w["amount"] = $data["karma"];
			$w["currency"] = "kar";
			$w["dt_created"] = time();
			$db->Create(0, 'question_bounty', $w);
			Karma::add(MyUser::id(), 5, 0-$w["amount"], $w["question"]);
		}
		if ($data["bitcoin"] > 0) {
			throw new APIException("Sie haben nicht genügend Bitcoin Guthaben.", 610);
		}
		if ($data["EUR"] > 0) {
			throw new APIException("Sie haben nicht genügend Euro Guthaben.", 710);
		}
	}
}