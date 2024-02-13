<?php
	error_reporting(0);
	
	require_once("../lib/mysql.php");
	require_once("../lib/utilities.php");
	
	authenticate();
	
	$playlists = getPlaylists($MYSQL->getrowsq("SELECT * FROM playlists WHERE `mpid` = '?' ORDER BY `updatedAt` DESC", array($ACCOUNT["id"])));
	
	die(json_encode(array(
		"success" => true,
		"playlists" => $playlists
	), JSON_NUMERIC_CHECK));
?>