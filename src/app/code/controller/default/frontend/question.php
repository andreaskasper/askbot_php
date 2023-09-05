<?php
if (isset($_GET["share"]) AND $_GET["share"] == "twitter") {
	@header("Location: ".SocialShare::TwitterPermaLink()); exit(1);
}
if (isset($_GET["share"]) AND $_GET["share"] == "facebook") {
	@header("Location: ".SocialShare::FacebookPermaLink()); exit(1);
}


if (isset($_POST["act"]) AND $_POST["act"] == "AnswerNew") {
	$j = true;
	if (trim($_POST["text"]) == "") { $j = false; PageEngine::AddErrorMessage("AnswerNew","Deine Antwort ist leer."); }
	if (!MyUser::isloggedin()) {
		if (!isset($_POST["antispam"]) OR $_POST["antispam"]."" == "") { PageEngine::AddErrorMessage("AnswerNew","Bitte lesen Sie die Buchstaben unten im Antispam!"); $j = false; }
		if (!isset($_SESSION["antispam0"]) OR !isset($_POST["antispam"]) OR $_SESSION["antispam0"] != $_POST["antispam"]) { PageEngine::AddErrorMessage("AnswerNew", "Ungültiger Antispam. Bitte lies nochmal genau!"); $j = false; }
	}
	
	if ($j) {
		$db = new SQL(0);
		$w = array();
		$w["txt"] = $_POST["text"];
		$w["question"] = $params["id"]+0;
		$w["author"] = (MyUser::isloggedin()?MyUser::id()+0:0-rand(10,999999));
		$w["authorIP"] = $_SERVER["REMOTE_ADDR"];
		$w["date_created"] = time();
		$w["date_edited"] = time();
		if (!MyUser::isloggedin() && SiteConfig::val("akismet/key")."" != "") {
			$akismet = new Akismet(SiteConfig::val("akismet/host"), SiteConfig::val("akismet/key"));
			$akismet->setCommentContent($w["txt"]);
			$akismet->setPermalink(Question::PermalinkByData($w["question"],"Frage"));
			$akismet->setUserIP($_SERVER["REMOTE_ADDR"]);
			try {
				if($akismet->isCommentSpam()) $w["isSPAM"] = 2; else $w["isSPAM"] = -2;
			} catch (Exception $ex) {}
		}
		$db->CreateUpdate(0, 'answers', $w);
		$answerID = $db->LastInsertKey();
		$db->cmd(0, 'UPDATE questions SET date_action={1},user_action="{2}", count_answers = (SELECT count(*) FROM answers WHERE question=questions.id) WHERE id={0} LIMIT 1', true, array($w["question"], time(), MyUser::id()+0));
		$_SESSION["myuser"]["lastwritten"]["answers"][$answerID] = true;
		Karma::RuleAction("CREATE_ANSWER", array("user" => MyUser::id(), "question" => $w["question"], "answer" => $answerID));
		Badges::add(4, MyUser::id(), array("question" => $w["question"])); //Erste Antwort geschrieben
	}
}

if (isset($_POST["act"]) AND $_POST["act"] == "addComment") {
	if (strlen($_POST["comment"]) >= 10 AND MyUser::isloggedin()) {
		$w = array();
		$db = new SQL(0);
		$w["question"] = $_POST["question"]+0;
		$w["answer"] = $_POST["answer"]+0;
		$w["text"] = $_POST["comment"];
		$w["created"] = time();
		$w["user"] = MyUser::id();
		$db->CreateUpdate(0, 'comments', $w);
		$a = $db->LastInsertKey();
		Badges::add(5, MyUser::id(), array("question" => $w["question"])); //Erster Kommentar geschrieben
		@header("Location: #comment-".$a);
	}
}

