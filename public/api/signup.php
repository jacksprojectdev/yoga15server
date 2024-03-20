<?php
	error_reporting(E_ALL);
	
	require_once("lib/mysql.php");
	require_once("lib/utilities.php");
	require_once("lib/wordpress/WordpressPasswordVerifier.php");
	
	$username = $_POST["username"];
	$password = $_POST["password"];
	
	$firstName = $_POST["firstName"];
	$lastName = $_POST["lastName"];
	$email = $_POST["email"];
	$preferredExperience = $_POST["preferredExperience"];
	$sports = $_POST["sports"];
	$experience = $_POST["experience"];
	
	$namecheck = $_POST["namecheck"];
	
	$ip = $_SERVER["REMOTE_ADDR"];
		
	if(!$username || (!$password && !$namecheck)) {
		die(json_encode(array(
			"error" => "No username or password provided"
		)));
	}
	if(!ctype_alnum($username)) {
		die(json_encode(array(
			"error" => "Usernames can not contain special characters"
		)));
	}
	if(strlen($password) < 6 && !$namecheck) {
		die(json_encode(array(
			"error" => "Passwords must be at least 6 characters long"
		)));
	}
	
	$mp_user = $MYSQL->getrowq("SELECT `ID` as id FROM $WPDB.wpaa_users WHERE user_login = '?'", array($username));
	
	if($mp_user) {
		if($namecheck) {
			die(json_encode(array(
				"error" => "Username is already taken. If you own this account, please go back and login as an existing user."
			)));
		}else{
			die(json_encode(array(
				"success" => true
			)));
		}
	}
	
	if($namecheck) {
		die(json_encode(array(
			"available" => true
		)));
	}
	
	// Sign them up!
	
	$user_login = $username;
	$user_pass = WordPressPasswordVerifier::generateLegacyHash($password); //password_hash($password, PASSWORD_BCRYPT);
	$user_nicename = $username;
	$user_email = $email;
	$user_url = "";
	$user_registered = date("Y-m-d H:i:s");
	$user_activation_key = "";
	$user_status = 0;
	$display_name = $firstName . " " . $lastName;
	
	$q1 = $MYSQL->query("INSERT INTO $WPDB.wpaa_users (`user_login`, `user_pass`, `user_nicename`, `user_email`, `user_url`, `user_registered`, `user_activation_key`, `user_status`, `display_name`) VALUES ('?', '?', '?', '?', '?', '?', '?', '?', '?')", array(
		$user_login,
		$user_pass,
		$user_nicename,
		$user_email,
		$user_url,
		$user_registered,
		$user_activation_key,
		$user_status,
		$display_name
	));
	
	if(!$q1) {
		die(json_encode(array(
			"error" => "Sign up error: Q1. Please report this bug."
		)));
	}
	
	$lri = $MYSQL->getlastrowid();
	$mp_user = $MYSQL->getrowq("SELECT *, `ID` as `id` FROM $WPDB.wpaa_users WHERE `ID` = ?", array($lri));
	
	if(!$mp_user["id"]) {
		die(json_encode(array(
			"error" => "MP USER ID IS ZERO: A ($lri)"
		)));
	}
	
	$q2 = $MYSQL->query("INSERT INTO `users` (`id`, `firstName`, `lastName`, `sports`, `experience`, `preferredExperience`, `introCompleted`, `joinDate`, `iapKey`) VALUES ('?', '?', '?', '?', '?', '?', '?', '?', '?')", array(
		$mp_user["id"],
		$firstName,
		$lastName,
		$sports,
		$experience,
		$preferredExperience,
		1,
		time(),
		generateRandomString(16)
	));
	
	if(!$q2) {
		die(json_encode(array(
			"error" => "Sign up error: Q2. Please report this bug."
		)));
	}
	
	$app_user = $MYSQL->getrowq("SELECT * FROM users WHERE id = '?'", array($mp_user["id"]));
	
	processNewAppUser(getAccount($mp_user, $app_user));
	
	die(json_encode(array(
		"success" => true
	)));
?>