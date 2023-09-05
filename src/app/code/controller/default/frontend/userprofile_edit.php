<?php

if (isset($_POST["act"]) AND $_POST["act"] == "save") {
	$w["id"] = $params["user_id"];
	if (!preg_match("`^[A-Za-z0-9\_\.]{2,}$`", $_POST["username"])) PageEngine::AddErrorMessage("save", "Ungültiges Format für den Usernamen");
	elseif (UsernameAlreadyInUse($_POST["username"], MyUser::id())) PageEngine::AddErrorMessage("save", "Username bereits vergeben");
	else $w["username"] = $_POST["username"];
	$w["prename"] = $_POST["prename"];
	$w["familyname"] = $_POST["familyname"];
	$w["website"] = $_POST["website"];
	$w["location"] = $_POST["location"];
	$w["country"] = $_POST["country"];
	$w["language"] = $_POST["language"];
	$w["FlattrUID"] = trim($_POST["FlattrUID"]);
	$w["SkypeID"] = trim($_POST["SkypeID"]);
	$w["GooglePlus"] = trim($_POST["GooglePlus"]);
	$w["PayPal_email"] = $_POST["PayPal_email"];
	$w["show_country"] = (isset($_POST["show_country"]) AND $_POST["show_country"] == "1"?1:0);
	$d = $_POST["birthday_year"]."-".$_POST["birthday_month"]."-".$_POST["birthday_day"];
	if (!preg_match("`^[0-9\?]{4}-[0-9\?]{2}-[0-9\?]{2}$`", $d)) PageEngine::AddErrorMessage("save", "Ungültiges Geburtsdatum");
	else $w["birthday"] = $d;
	$w["biography"] = $_POST["text"];
	$db = new SQL(0);
	$db->CreateUpdate(0, "user_list", $w);
	if ($w["username"] != "" AND $w["prename"] != "" AND $w["familyname"] != "" AND $w["location"] != "" AND $w["country"] != "" AND $w["language"] != "" AND $w["birthday"] != "" AND $w["biography"] != "") Badges::add(1, $w["id"]);
	if ($w["SkypeID"]."" != "") Badges::add(6, $w["id"], array("skype" => $w["SkypeID"]));
	PageEngine::AddSuccessMessage("save", "Profil gespeichert");
}

function UsernameAlreadyInUse($name, $myuserid = 0) {
	$db = new SQL(0);
	$row = $db->cmdrow(0, 'SELECT id FROM user_list WHERE username = "{0}" AND id != {1} LIMIT 0,1', array($name, $myuserid+0));
	return isset($row["id"]);
}