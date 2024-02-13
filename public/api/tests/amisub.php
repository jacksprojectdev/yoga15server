<?php
	require_once("../lib/mysql.php");
	require_once("../lib/utilities.php");
	require_once("../lib/subscriber.php");
	
	$user_id = $_GET["id"];
	
	var_dump(get_user_subscriber($user_id));
?>