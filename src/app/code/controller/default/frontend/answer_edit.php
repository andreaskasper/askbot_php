<?php
if (isset($_POST["action"]) AND $_POST["action"] == "save") {
	$db = new SQL(0);
	$info = $db->cmdrow(0, 'SELECT T1.*, T2.title FROM answers as T1 LEFT JOIN questions as T2 ON T1.question=T2.id WHERE T1.id={0} LIMIT 0,1', array($params["id"]+0));
	if ($info["author"] != MyUser::id() && !isset($_SESSION["myuser"]["lastwritten"]["answers"][$info["id"]])) die("Dies ist nicht Ihre Frage.");
	$w = array();
	$w["keyid"] = $info["id"];
	$w["type"] = "a";
	$w["title"] = "";
	$w["text"] = $info["txt"];
	$w["user"] = MyUser::id();
	$w["dt_created"] = time();
	$db->CreateUpdate(0, 'qatext_versions', $w);
	
	$w2 = array();
	$w2["id"] = $info["id"];
	$w2["txt"] = $_POST["text"];
	$w2["author"] = MyUser::id();
	$w2["date_edited"] = time();
	$db->CreateUpdate(0, 'answers', $w2);
	
	$w3 = array();
	$w3["id"] = $info["question"]+0;
	$w3["date_action"] = time();
	$w3["user_action"] = MyUser::id();
	$db->CreateUpdate(0, 'questions', $w3);
	
	
	Badges::add(10, MyUser::id()); //Erfolg Redakteur: Editiere einen Beitrag
	header("Location: ".Question::PermalinkByData($info["question"], $info["title"])."#answer-".$w2["id"]);
	exit(1);
}