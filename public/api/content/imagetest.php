<?php
	error_reporting(E_ALL);
	
	require_once("../lib/utilities.php");
	
	$thumbWidth = 100;
	$thumbHeight = 80;
	$thumb = getResourceImage("https://yoga15.com/wp-content/uploads/bb-plugin/cache/yoga-15-firefighter-mike-bakke-225x300-circle-6910d790b49e01f21b191c40135ac3ab-.jpg", $thumbWidth, $thumbHeight);
	
	die(var_dump($thumb));
?>