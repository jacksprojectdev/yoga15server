<?php
	error_reporting(0);
	
	require_once("../lib/mysql.php");
	require_once("../lib/utilities.php");
	require_once("../lib/video.php");
	
	authenticate();
	
	$name = substr($_POST["name"], 0, 80);
	
	if(!$name) {
		die(json_encode(array(
			"error" => "Invalid playlist name"
		)));
	}
	
	$myPlaylists = $MYSQL->getrowsq("SELECT * FROM playlists WHERE `mpid` = '?'", array($ACCOUNT["id"]));
	
	if(count($myPlaylists) >= 100) {
		die(json_encode(array(
			"error" => "You have reached the limit of playlists for this account"
		)));
	}
	
	$q = $MYSQL->query("INSERT INTO playlists (`mpid`, `name`, `createdAt`, `updatedAt`) VALUES ('?', '?', '?', '?')", array(
		$ACCOUNT["id"],
		$name,
		time(), 
		time()
	));
	
	if(!$q) {
		die(json_encode(array(
			"error" => "Error creating playlist: " . $MYSQL->error()
		)));
	}
	
	die(json_encode(array(
		"success" => true
	)));
?>