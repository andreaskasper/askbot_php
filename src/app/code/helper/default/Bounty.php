<?php

class Bounty {

	public static function Release($question = 0, $Receiver_User = 0) {
		$db = new SQL(0);
		$rows = $db->cmdrows(0, 'SELECT * FROM question_bounty WHERE question={0} GROUP BY currency', array($question+0));
		foreach ($rows as $row) {
			switch ($row["currency"]) {
				case "kar":
					Karma::add($Receiver_User, 6, $row["amount"],$row["question"]);
					break;
				case "BTC":
					break;
				case "EUR":
					break;
				default:
			}
		}
		
		
		$db->cmd(0, 'UPDATE questions SET is_bounty =0 WHERE question={0} LIMIT 1', true, array($question+0));
		$db->cmd(0, 'DELETE FROM question_bounty WHERE question={0}', true, array($question+0));
	
	
	
	}

}



?>