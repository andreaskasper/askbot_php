<?php

if (isset($_POST["act"]) AND $_POST["act"] == "save") {
	$db = new SQL(0);
	$db->cmd(0, 'DELETE FROM user_rights WHERE user={0}',true, array($params["user_id"]+0));
	foreach ($_POST as $key => $value) {
		if (substr($key, 0, 6) == "right_" AND $value == "1") {
			$right = substr($key,6);
			$w = array();
			$w["user"] = $params["user_id"]+0;
			$w["right"] = $right;
			$db->CreateUpdate(0, "user_rights", $w);
		}
	
	
	
	}
}