<?php
	require_once("utilities.php");
	
	function notify($mpid, $title, $body, $badge, $data = array()) {
		global $MYSQL;
		global $WPDB;
		
		$app_user = $MYSQL->getrowq("SELECT `pushtoken` FROM users WHERE `id` = '?'", array($mpid));
		$pushtoken = $app_user["pushtoken"];
		
		if(!$pushtoken)
			return false;
		
		return sendExpoNotification($pushtoken, $title, $body, $badge, $data);
	}
	function sendExpoNotification($deviceId, $title, $body, $badge, $data = array()) {
		// Replace with your Expo push notification server key
		$expoPushNotificationServerKey = '13yck50rvcB-wvTJaI86wZSzFWNIwpbv9UXh2V6D';
		
		// Prepare the notification payload
		$notification = [
			'to' => $deviceId,
			'title' => $title,
			'body' => $body,
			'data' => $data,
			'sound' => 'default',
			'badge' => $badge,
			'channelId' => 'default',
			'mutableContent' => true
		];
	
		// Create cURL headers
		$headers = [
			'Content-Type: application/json',
			'Accept: application/json',
			'Authorization: Bearer ' . $expoPushNotificationServerKey,
		];
	
		// Initialize cURL session
		$ch = curl_init('https://exp.host/--/api/v2/push/send');
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($notification));
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		
		// Execute cURL session
		$response = curl_exec($ch);
		
		// Check for cURL errors
		if (curl_errno($ch)) {
			error_log('Expo Notification cURL Error: ' . curl_error($ch));
			curl_close($ch);
			return false;
		}
		
		// Close cURL session
		curl_close($ch);
		
		// Log the request and response for troubleshooting
		error_log('Expo Notification Request: ' . json_encode($notification));
		error_log('Expo Notification Response: ' . $response);
		
		// Process the response
		$responseData = json_decode($response, true);
	
		// Check if the notification was successfully sent
		if (isset($responseData['data']) && is_array($responseData['data'])) {
			foreach ($responseData['data'] as $item) {
				if (isset($item['status']) && $item['status'] === 'error') {
					error_log('Expo Notification Error: ' . $item['message']);
					return false;
				}
			}
			return true;
		} else {
			error_log('Expo Notification Error: Invalid response format');
			return false;
		}
	}
?>