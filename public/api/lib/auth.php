<?php
	require_once("subscriber.php");
	
	function authenticate() {
		global $MYSQL;
		global $WPDB;
		global $GLOBALS;
		
		$token = $_SERVER["HTTP_Y15_AUTH_TOKEN"];
		
		/* 
			For Testing
		*/
		//$token = "8eviq3i4pGCMRxX9JpLrniegntL2dKXvvdFgtZcm";
		
		$session = $MYSQL->getrowq("SELECT * FROM sessions WHERE `token` = '?' LIMIT 1", array($token));
		
		if(!$session) {
			die(json_encode(array(
				"error" => "Authentication error"
			)));
		}
		
		$GLOBALS["MP_USER"] = $MYSQL->getrowq("SELECT * FROM $WPDB.wpaa_users WHERE `ID` = '?'", array($session["mpid"]));
		$GLOBALS["APP_USER"] = $MYSQL->getrowq("SELECT * FROM users WHERE `id` = '?'", array($session["mpid"]));
		$GLOBALS["ACCOUNT"] = getAccount($GLOBALS["MP_USER"], $GLOBALS["APP_USER"]);
		
		return true;
	}
?>