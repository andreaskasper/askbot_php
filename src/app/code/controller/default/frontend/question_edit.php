<?php
if (isset($_POST["action"]) AND $_POST["action"] == "save") {
	$db = new SQL(0);
	$info = $db->cmdrow(0, 'SELECT * FROM questions WHERE id={0} LIMIT 0,1', array($params["id"]+0));
	if ($info["author"] != MyUser::id()) die("Dies ist nicht Ihre Frage.");
	$w = array();
	$w["keyid"] = $info["id"];
	$w["type"] = "q";
	$w["title"] = $info["title"];
	$w["text"] = $info["question"];
	$w["user"] = MyUser::id();
	$w["dt_created"] = time();
	$db->CreateUpdate(0, 'qatext_versions', $w);
	
	$w2 = array();
	$w2["id"] = $info["id"];
	$w2["title"] = $_POST["title"];
	$w2["question"] = $_POST["text"];
	$w2["author"] = MyUser::id();
	$w2["tags"] = implode(",", tags2array($_POST["tags"]));
	$w2["date_edited"] = time();
	$w2["date_action"] = time();
	$w2["user_action"] = MyUser::id()+0;	
	$db->CreateUpdate(0, 'questions', $w2);
	
	$db->cmd(0, 'DELETE FROM `question_tags` WHERE question={0}', true, array($info["id"]));
	foreach (tags2array($_POST["tags"]) as $a) {
		$w3 =array();
		$w3["question"] = $info["id"];
		$w3["tag"] = $a;
		$db->CreateUpdate(0, "question_tags", $w3);
	}
	Badges::add(10, MyUser::id()); //Erfolg Redakteur: Editiere einen Beitrag
	header("Location: ".Question::PermalinkByData($info["id"], $info["title"]));
	exit(1);
	

}

function tags2array($text) {
	$g = explode("," ,$text);
	$out = array();
	for ($i = 0; $i < min(5, count($g)); $i++) $out[] = trim(strtolower($g[$i]));
	return $out;
}