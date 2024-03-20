<?php
	require_once("../lib/mysql.php");
	require_once("../lib/utilities.php");
	require_once("../lib/subscriber.php");
	
	$user_id = $_GET["id"];
	
	var_dump(get_user_subscriber($user_id));
	//var_dump(make_user_subscriber($user_id, 807, "1 MONTH", 19.99));
	//var_dump(unmake_user_subscriber($user_id, 807));
	//var_dump(get_user_subscriber($user_id));
?>