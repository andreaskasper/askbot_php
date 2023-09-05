<?php

class API_answer {

	public static function voteup($data) {
		$out = array();
		if (!MyUser::isloggedin()) throw new APIException("User ist nicht angemeldet.", 100);
		if (MyUser::getKarmaPoints() < 5) throw new APIException("Du benötigst 5 Karma-Punkte um einen positiven Vote zu geben.", 200);
		if (!isset($data["answer"])) throw new APIException("Benötigter Parameter fehlt (answer).", 50);
		$db = new SQL(0);
		$row = $db->cmdrow(0, 'SELECT * FROM answers WHERE id={0} LIMIT 0,1', array($data["answer"]+0));
		$question = $db->cmdrow(0, 'SELECT * FROM questions WHERE id={0} LIMIT 0,1', array($row["question"]+0));
		if (!isset($row["id"])) throw new APIException("Diese Antwort existiert nicht (mehr)", 300);
		if ($row["author"] == MyUser::id()) throw new APIException("Sie dürfen nicht auf Ihre eigene Antwort voten", 301);
		$raw = $db->cmdrow(0, 'SELECT * FROM answer_votes WHERE answer={0} AND user={1} LIMIT 0,1', array($data["answer"]+0, MyUser::id()));
		
		$w = array();
		$w["answer"] = $data["answer"]+0;
		$w["user"] = MyUser::id();
		$w["vote"] = 1;
		$db->CreateUpdate(0, "answer_votes", $w);
		$db->cmd(0, 'UPDATE answers as T1 SET count_votes = (SELECT sum(vote) FROM answer_votes WHERE answer=T1.id) WHERE id={0} LIMIT 1', false, array($w["answer"]));
		$out["sumvotes"] = self::getVotes(array("answer" => $w["answer"]));
		if (!isset($raw["id"])) Karma::RuleAction("VOTEUP_ANSWER", array("user" => $row["author"], "question" => $row["question"], "answer" => $row["id"]));

		$posV = $db->cmdvalue(0,'SELECT count(*) FROM answer_votes WHERE vote="1" AND answer={0}', array($row["id"]));
		if ($posV == 3) Badges::add(51, $row["author"], array("question" => $row["question"],"answer" => $w["answer"])); //Gute Antwort (Silber) 3 positive Votes
		elseif ($posV == 10) Badges::add(52, $row["author"], array("question" => $row["question"],"answer" => $w["answer"])); //Gute Antwort (Silber) 3 positive Votes
		elseif ($posV == 25) Badges::add(53, $row["author"], array("question" => $row["question"],"answer" => $w["answer"])); //Gute Antwort (Silber) 3 positive Votes
		if ($posV >= 5 AND $question["has_bounty"] == "1" AND $question["author"] != $ow["author"] AND $question["date_created"]+7*86400 < time()) Bounty::Release($question["id"],$row["author"]);
		return $out;
	}
	
	public static function votedown($data) {
		$out = array();
		if (!MyUser::isloggedin()) throw new APIException("User ist nicht angemeldet.", 100);
		if (MyUser::getKarmaPoints() < 100) throw new APIException("Du benötigst 100 Karma-Punkte um einen negativen Vote zu geben.", 200);
		if (!isset($data["answer"])) throw new APIException("Benötigter Parameter fehlt (answer).", 50);
		$db = new SQL(0);
		$row = $db->cmdrow(0, 'SELECT * FROM answers WHERE id={0} LIMIT 0,1', array($data["answer"]+0));
		if (!isset($row["id"])) throw new APIException("Diese Antwort existiert nicht (mehr)", 300);
		if ($row["author"] == MyUser::id()) throw new APIException("Sie dürfen nicht auf Ihre eigene Antwort voten", 301);
		$raw = $db->cmdrow(0, 'SELECT * FROM answer_votes WHERE answer={0} AND user={1} LIMIT 0,1', array($data["answer"]+0, MyUser::id()));
		
		$w = array();
		$w["answer"] = $data["answer"]+0;
		$w["user"] = MyUser::id();
		$w["vote"] = -1;
		$db->CreateUpdate(0, "answer_votes", $w);
		$db->cmd(0, 'UPDATE answers as T1 SET count_votes = (SELECT sum(vote) FROM answer_votes WHERE answer=T1.id) WHERE id={0} LIMIT 1', false, array($w["answer"]));
		if (!isset($raw["id"])) Karma::RuleAction("VOTEDOWN_ANSWER", array("user" => $row["author"], "question" => $row["question"], "answer" => $row["id"]));
		$out["sumvotes"] = self::getVotes(array("answer" => $w["answer"]));
		Badge::add(9, MyUser::id(), array("answer" => $w["answer"])); //Kritiker: für downvote
		return $out;
	}
	
	public static function spam($data) {
		if (!MyUser::isloggedin()) throw new APIException("User ist nicht angemeldet.", 100);
		if (!MyUser::hasAdminRight()) throw new APIException("Sie benötigen Admin-Rechte für diese Funktion", 101);
		if (!isset($data["answer"])) throw new APIException("Benötigter Parameter fehlt (answer).", 50);
		$db = new SQL(0);
		$db->cmd(0,'UPDATE answers SET isSPAM=1 WHERE id={0} LIMIT 1', true, array($data["answer"]+0));
		return true;
	}
	
	public static function nospam($data) {
		if (!MyUser::isloggedin()) throw new APIException("User ist nicht angemeldet.", 100);
		if (!MyUser::hasAdminRight()) throw new APIException("Sie benötigen Admin-Rechte für diese Funktion", 101);
		if (!isset($data["answer"])) throw new APIException("Benötigter Parameter fehlt (answer).", 50);
		$db = new SQL(0);
		$db->cmd(0,'UPDATE answers SET isSPAM=-1 WHERE id={0} LIMIT 1', true, array($data["answer"]+0));
		return true;
	}
	
	public static function accept($data) {
		$out = array();
		if (!MyUser::isloggedin()) throw new APIException("User ist nicht angemeldet.", 100);
		if (!isset($data["answer"])) throw new APIException("Benötigter Parameter fehlt (answer).", 50);
		$db = new SQL(0);
		$info = $db->cmdrow(0, 'SELECT * FROM answers WHERE id={0} LIMIT 0,1', array($data["answer"]+0));
		if (!isset($info["id"])) throw new APIException("Diese Antwort existiert nicht (mehr)", 300);
		if ($info["right_answer"] == "1") throw new APIException("Dies ist bereits die beste Antwort", 330);
		$qinfo = $db->cmdrow(0, 'SELECT * FROM questions WHERE id={0} LIMIT 0,1', array($info["question"]+0));
		if (!isset($qinfo["id"])) throw new APIException("Diese Frage existiert nicht (mehr)", 300);
		if ($qinfo["is_closed"] == "1") throw new APIException("Diese Frage ist bereits geschlossen", 310);
		if ($qinfo["author"] != MyUser::id()) throw new APIException("Dies ist nicht ihre Frage", 320);
		if ($info["author"] == MyUser::id() AND (MyUser::getKarmaPoints() < 50)) throw new APIException("Deine eigene Antwort darf erst ab 50 Karma Punkten die beste Antwort sein", 210);
		
		$db->cmd(0, 'UPDATE answers SET right_answer = "1" WHERE id={0} LIMIT 1', true, array($info["id"]));
		$db->cmd(0, 'UPDATE questions SET is_answered = "1" WHERE id={0} LIMIT 1', true, array($info["question"]));
		if (MyUser::id() != $info["author"]) Karma::RuleAction("ACCEPT_ANSWER", array("user" => $info["author"], "question" => $info["question"], "answer" => $info["id"]));
		if (MyUser::id() != $info["author"] && $info["is_bounty"] == "1") Bounty::Release($info["question"], $info["author"]); //Gib dem Autor die Bounty
		return true;
	}
	
	public static function getVotes($data) {
		if (!isset($data["answer"])) throw new APIException("Benötigter Parameter fehlt (answer).", 50);
		$db = new SQL(0);
		return $db->cmdvalue(0, 'SELECT count_votes FROM answers WHERE id={0}', array($data["answer"]+0));
	}


}