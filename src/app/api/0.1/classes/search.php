<?php

class API_search {

	public static function tnquestion($data) {
		$db = new SQL(0);
		$out = array();
		$rows = $db->cmdrows(0, 'SELECT *,MATCH (title,question,tags) AGAINST ("{0}") as Score FROM questions WHERE MATCH (title,question,tags) AGAINST ("{0}") ORDER BY Score DESC LIMIT 0,10', array($data["term"]));
		foreach ($rows as $row) {
			$b = array();
			$b["label"] = $row["title"];
			$b["value"] = $row["title"];
			$b["type"] = "question";
			$b["score"] = $row["Score"]+0;
			$out[] = $b;
		}
		return $out;
	}


}