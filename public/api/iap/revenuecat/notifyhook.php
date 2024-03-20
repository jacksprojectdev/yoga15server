<?php
	require_once("../../lib/mysql.php");
	require_once("../../lib/utilities.php");
	require_once("../../lib/subscriber.php");
	
	$requestBody = file_get_contents('php://input');
	$request = json_decode($requestBody, true);
	
	$event = $request["event"];
	
	$appUserId = $event["app_user_id"];
	//$appUserId = "8114eaa5bac111ee9aee00163e14"; // test
	
	$price = $event["price"];
	//$price = 12.99; // test
	
	$productId = $event["product_id"];
	//$productId = "subscription_annual"; // test
	
	$type = $event["type"];
	
	$app_user = $MYSQL->getrowq("SELECT * FROM users WHERE `iapKey` = '?'", array($appUserId));
	
	if(!$app_user) {
		http_response_code(400);
		die(json_encode(array(
			"error" => "Invalid user"
		)));
	}
	
	$res = false;
	
	switch($productId) {
		case "subscription_monthly":
			
			/**
				They're expired or unsubscribed
			 */
			if($type == "EXPIRATION" || $type == "CANCELLATION") {
				$res = unmake_user_subscriber($app_user["id"], 807);
			}else
			if($type == "INITIAL_PURCHASE" || $type == "RENEWAL" || $type == "UNCANCELLATION" || $type == "NON_RENEWING_PURCHASE") {
				// Positive
				$res = make_user_subscriber($app_user["id"], 807, "1 MONTH", $price);
			}
		break;
		case "subscription_annual":
			
			/**
				They're expired or unsubscribed
			 */
			if($type == "EXPIRATION" || $type == "CANCELLATION") {
				$res = unmake_user_subscriber($app_user["id"], 808);
			}else
			if($type == "INITIAL_PURCHASE" || $type == "RENEWAL" || $type == "UNCANCELLATION" || $type == "NON_RENEWING_PURCHASE") {
				// Positive
				$res = make_user_subscriber($app_user["id"], 808, "1 YEAR", $price);
			}
		break;
	}
	
	if(!$res) {
		http_response_code(400);
		die(json_encode(array(
			"error" => "Subscription failed",
			"res" => $res,
			"type" => $type
		)));
	}
	
	http_response_code(200);
	die(json_encode(array(
		"success" => true,
		"is_subscriber" => get_user_subscriber($app_user["id"])
	)));
?>