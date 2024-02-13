<?php
	error_reporting(E_ALL);
	
	require_once("../lib/mysql.php");
	require_once("../lib/utilities.php");
	
	if($_GET["auth"]) {
		$_SERVER["HTTP_Y15_AUTH_TOKEN"] = $_GET["auth"];
	}
	
	authenticate();
	
	$action = $_GET["action"];
	
	// Strava config
	$client_id = 113225;
	$client_secret = 'e1c2d827585be03dec3a6bc3bb730148916404f3';
	
	// Define the token endpoint URL
	$token_endpoint = 'https://www.strava.com/oauth/token';
	
	function authenticateToStrava($authorization_code) {
		global $client_id;
		global $client_secret;
		global $token_endpoint;
		
		// Create cURL request
		$ch = curl_init($token_endpoint);
		
		// Set cURL options
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, [
			'client_id' => $client_id,
			'client_secret' => $client_secret,
			'code' => $authorization_code,
			'grant_type' => 'authorization_code',
		]);
		
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		
		// Execute cURL request and get the response
		$response = curl_exec($ch);
		
		// Check for cURL errors
		if (curl_errno($ch)) {
			echo 'cURL Error: ' . curl_error($ch);
		}
		
		// Close cURL session
		curl_close($ch);
		
		return $response;
	}
	function refreshStrava($refresh_token) {
		global $client_id;
		global $client_secret;
		global $token_endpoint;
		
		// Create cURL request
		$ch = curl_init($token_endpoint);
		
		// Set cURL options
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, [
			'client_id' => $client_id,
			'client_secret' => $client_secret,
			'refresh_token' => $refresh_token,
			'grant_type' => 'refresh_token',
		]);
		
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		
		// Execute cURL request and get the response
		$response = curl_exec($ch);
		
		// Check for cURL errors
		if (curl_errno($ch)) {
			echo 'cURL Error: ' . curl_error($ch);
		}
		
		// Close cURL session
		curl_close($ch);
		
		return $response;
	}
	
	switch($action) {
		case "refresh":
			// Make the request for the refresh token, access token and access token expiration date
			$response = json_decode(refreshStrava($ACCOUNT["stravarefresh"]), true);
			
			if(!$response["access_token"]) {
				die(json_encode(array(
					"success" => false,
					"error" => "Y15 refresh strava error: " . json_encode($response)
				), JSON_NUMERIC_CHECK));
			}
			
			$q = $MYSQL->query("UPDATE users SET `stravatoken` = '?', `stravarefresh` = '?', `stravaexpire` = '?' WHERE `id` = '?'", array(
				$response["access_token"],
				$response["refresh_token"],
				$response["expires_at"],
				$ACCOUNT["id"]
			));
			
			if(!$q) {
				die(json_encode(array(
					"success" => false,
					"error" => "Error updating strava tokens on refresh "  . $MYSQL->error()
				)));
			}
			
			die(json_encode(array(
				"success" => true,
				"access_token" => $response["access_token"],
				"refresh_token" => $response["refresh_token"],
				"expires_at" => $response["expires_at"]
			), JSON_NUMERIC_CHECK));
		break;
		default:
			$code = $_GET["code"];
			
			if(!$code)
				return;
			
			// Make the request for the refresh token, access token and access token expiration date
			$response = json_decode(authenticateToStrava($code), true);
			
			if(!$response["access_token"])
				return;
			
			$q = $MYSQL->query("UPDATE users SET `stravatoken` = '?', `stravarefresh` = '?', `stravaexpire` = '?', `stravauser` = '?' WHERE `id` = '?'", array(
				$response["access_token"],
				$response["refresh_token"],
				$response["expires_at"],
				$response["athlete"]["username"],
				$ACCOUNT["id"]
			));
			
			if(!$q) {
				die(json_encode(array(
					"success" => false,
					"error" => "Error updating strava tokens on authenticate " . $MYSQL->error()
				)));
			}
			
			?>
				<html>
					<head>
						<title>Yoga 15</title>
						<meta name="viewport" content="width=500, initial-scale=1" />
					</head>
					<style>
						body {
							font-family: sans-serif;
						}
					</style>
					<body>
						<h2>You're all set, <?=$response["athlete"]["firstname"]?></h2>
						Head back to the Yoga 15 app to finish sharing your activity to Strava.
					</body>
				</html>
			<?php
		break;
	}
?>