<?php

ini_set('session.use_only_cookies', '1'); 

class Session {

	public static function init($reload = false) {
	
		//session_set_save_handler("self::_open",'self::_close','self::_read','self::_write','self::_destroy','self::_clean');
	
		ini_set("session.gc_maxlifetime", 3*3600);
		session_name("jSID");
		session_cache_limiter("private");
		session_cache_expire(180);
		session_start();
		
		if(!isset($_SESSION['nonce']) || $reload) $_SESSION['nonce'] = md5(microtime(true));
		if(!isset($_SESSION['IP']) || $reload) $_SESSION['IP'] = $_SERVER['REMOTE_ADDR'];
		if(!isset($_SESSION['userAgent']) || $reload) $_SESSION['userAgent'] = $_SERVER['HTTP_USER_AGENT'];
		
		if ($_SESSION['IP'] != $_SERVER['REMOTE_ADDR'] OR $_SESSION['userAgent'] != $_SERVER['HTTP_USER_AGENT']) session_regenerate_id(false);
	}
	
	public static function _open() { 
		$db = new SQL(0);
		$conn = $db->Verbindungsnr(0);
        if ( $id = session_id() ) { 
            $id = mysql_real_escape_string($id, $conn);
            $ipagent = ''; 
             
            if ( mysql_num_rows( $query = mysql_query("SELECT `ipagent` FROM `user_sessions` WHERE `id` = '{$id}'", $conn)) )
                $ipagent = mysql_result($query,0);
				
			//if ( $ipagent != md5($_ENV["PwdSalt"].$_SERVER['HTTP_USER_AGENT']) ) echo("Regenerate");

            if ( $ipagent != md5($_ENV["PwdSalt"].$_SERVER['HTTP_USER_AGENT']) )
                session_regenerate_id(); 
        }
} 

	public static function _close() { 
		echo("close");
		return true;
	} 

	public static function _read($id) { 
		$db = new SQL(0);
		$conn = $db->Verbindungsnr(0); 

		$id = mysql_real_escape_string($id, $conn); 

		if ( mysql_num_rows( $query = mysql_query("SELECT `data` FROM `user_sessions` WHERE `id` = '{$id}'", $conn)) )
			return mysql_result($query, 0); 
		return ''; 
	} 

	public static function _write($id, $data) { 
		echo("Hallo1");
		$db = new SQL(0);
		$conn = $db->Verbindungsnr(0);
     
		$access = mysql_real_escape_string(time(), $conn);
		$id = mysql_real_escape_string($id, $conn); 
		$data = mysql_real_escape_string($data, $conn);
		$ipagent = mysql_real_escape_string(md5($_ENV["PwdSalt"].$_SERVER['HTTP_USER_AGENT']), $conn);
		
		print_r($conn);
		print_r($data);

		return mysql_query("REPLACE INTO `user_sessions` (`id`,`access`,`data`,`ipagent`) VALUES ('{$id}','{$access}','{$data}','{$ipagent}')", $conn);
	} 

	public static function _destroy($id) { 
		$db = new SQL(0);
		$conn = $db->Verbindungsnr(0); 
		$id = mysql_real_escape_string($id, $conn); 
		return mysql_query("DELETE FROM `user_sessions` WHERE `id` = '{$id}'", $conn);
	} 

	public static function _clean($max) { 
		$db = new SQL(0);
		$conn = $db->Verbindungsnr(0);

		$max = mysql_real_escape_string(time() - $max, $conn);

		return mysql_query("DELETE FROM `user_sessions` WHERE `access` < '{$max}'", $conn);
	} 
	
	
	
}