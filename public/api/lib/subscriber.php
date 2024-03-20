<?php
	function subscriber_check() {
		global $ACCOUNT;
		
		$userId = $ACCOUNT["id"];
		
		$is_subscriber = get_user_subscriber($userId);
		
		if(!$is_subscriber) {
			die(json_encode(array(
				"error" => "Subscriber error"
			)));
		}
		
		return true;
	}
	function get_user_subscriber($userId) {
		$userId = urlencode($userId);
		
		$url = "https://yoga15.com/app-assist/subscriber.php?id=$userId&action=check&auth=iloveyogalol";
		
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/99.0.9999.999 Safari/537.36');
		
		$response = curl_exec($ch);
		
		if(curl_errno($ch)) {
			echo 'Curl error: ' . curl_error($ch);
		}
		
		curl_close($ch);
		
		$res = json_decode($response);
		
		return $res->subscriber == true;
	}
	function get_user_subscriber_old($userId) {
		global $MYSQL;
		global $WPDB;
		global $ACCOUNT;
		
		$rows = $MYSQL->getrowsq("
			SELECT 
				u.ID AS `ID`, 
				u.user_login AS `username`, 
				u.user_email AS `email`, 
				CONCAT(pm_last_name.meta_value, ', ', pm_first_name.meta_value) AS `name`, 
				pm_first_name.meta_value AS `first_name`, 
				pm_last_name.meta_value AS `last_name`, 
				IFNULL(m.txn_count,0) AS `txn_count`, 
				IFNULL(m.active_txn_count,0) AS `active_txn_count`, 
				IFNULL(m.expired_txn_count,0) AS `expired_txn_count`, 
				IFNULL(m.trial_txn_count,0) AS `trial_txn_count`, 
				IFNULL(m.sub_count,0) AS `sub_count`, 
				IFNULL(m.active_sub_count,0) AS `active_sub_count`, 
				IFNULL(m.pending_sub_count,0) AS `pending_sub_count`, 
				IFNULL(m.suspended_sub_count,0) AS `suspended_sub_count`, 
				IFNULL(m.cancelled_sub_count,0) AS `cancelled_sub_count`, 
				IFNULL(latest_txn.created_at,NULL) AS `latest_txn_date`, 
				IFNULL(first_txn.created_at,NULL) AS `first_txn_date`, 
				CASE 
					WHEN active_txn_count>0 THEN 'active' 
					WHEN trial_txn_count>0 THEN 'active' 
					WHEN expired_txn_count>0 THEN 'expired' 
					ELSE 'none' 
				END AS `status`, 
				IFNULL(m.memberships,'') AS `memberships`, 
				IFNULL(m.inactive_memberships,'') AS `inactive_memberships`, 
				IFNULL(last_login.created_at, NULL) AS `last_login_date`, 
				IFNULL(m.login_count,0) AS `login_count`, 
				IFNULL(m.total_spent,0.00) AS `total_spent`, 
				u.user_registered AS `registered` 
			FROM 
				$WPDB.wpaa_users AS u 
				LEFT JOIN $WPDB.wpaa_usermeta AS pm_first_name ON pm_first_name.user_id = u.ID AND pm_first_name.meta_key='first_name' 
				LEFT JOIN $WPDB.wpaa_usermeta AS pm_last_name ON pm_last_name.user_id = u.ID AND pm_last_name.meta_key='last_name' 
				/* IMPORTANT */ 
				JOIN $WPDB.wpaa_mepr_members AS m ON m.user_id=u.ID 
				LEFT JOIN $WPDB.wpaa_mepr_transactions AS first_txn ON m.first_txn_id=first_txn.id 
				LEFT JOIN $WPDB.wpaa_mepr_transactions AS latest_txn ON m.latest_txn_id=latest_txn.id 
				LEFT JOIN $WPDB.wpaa_mepr_events AS last_login ON m.last_login_id=last_login.id 
			WHERE 
				(m.active_txn_count > 0 OR m.trial_txn_count > 0) 
				AND latest_txn.expires_at > NOW()  -- Add this condition to filter out expired transactions
				AND (u.`ID` = $userId) 
			ORDER BY 
				`registered` DESC;
		");
		
		$is_subscriber = count($rows) > 0;
		
		if(count($rows) < 1) {
			$apiCheck = json_decode(file_get_contents("https://yoga15.com/app-assist/subscriber.php?id=$userId&action=check&auth=iloveyogalol"), true);
			$is_subscriber = $apiCheck["subscriber"] == true;
			
			//$rows = $MYSQL->getrowsq("SELECT u.ID AS `ID`, u.user_login AS `username`, u.user_email AS `email`, CONCAT(pm_last_name.meta_value, ', ', pm_first_name.meta_value) AS `name`, pm_first_name.meta_value AS `first_name`, pm_last_name.meta_value AS `last_name`, IFNULL(m.txn_count,0) AS `txn_count`, IFNULL(m.active_txn_count,0) AS `active_txn_count`, IFNULL(m.expired_txn_count,0) AS `expired_txn_count`, IFNULL(m.trial_txn_count,0) AS `trial_txn_count`, IFNULL(m.sub_count,0) AS `sub_count`, IFNULL(m.active_sub_count,0) AS `active_sub_count`, IFNULL(m.pending_sub_count,0) AS `pending_sub_count`, IFNULL(m.suspended_sub_count,0) AS `suspended_sub_count`, IFNULL(m.cancelled_sub_count,0) AS `cancelled_sub_count`, IFNULL(latest_txn.created_at,NULL) AS `latest_txn_date`, IFNULL(first_txn.created_at,NULL) AS `first_txn_date`, CASE WHEN active_txn_count>0 THEN 'active' WHEN trial_txn_count>0 THEN 'active' WHEN expired_txn_count>0 THEN 'expired' ELSE 'none' END AS `status`, IFNULL(m.memberships,'') AS `memberships`, IFNULL(m.inactive_memberships,'') AS `inactive_memberships`, IFNULL(last_login.created_at, NULL) AS `last_login_date`, IFNULL(m.login_count,0) AS `login_count`, IFNULL(m.total_spent,0.00) AS `total_spent`, u.user_registered AS `registered` FROM $WPDB.wpaa_users AS u LEFT JOIN $WPDB.wpaa_usermeta AS pm_first_name ON pm_first_name.user_id = u.ID AND pm_first_name.meta_key='first_name' LEFT JOIN $WPDB.wpaa_usermeta AS pm_last_name ON pm_last_name.user_id = u.ID AND pm_last_name.meta_key='last_name' JOIN $WPDB.wpaa_mepr_members AS m ON m.user_id=u.ID LEFT JOIN $WPDB.wpaa_mepr_transactions AS first_txn ON m.first_txn_id=first_txn.id LEFT JOIN $WPDB.wpaa_mepr_transactions AS latest_txn ON m.latest_txn_id=latest_txn.id LEFT JOIN $WPDB.wpaa_mepr_events AS last_login ON m.last_login_id=last_login.id WHERE (m.active_txn_count > 0 OR m.trial_txn_count > 0) AND (u.`ID` = $userId) ORDER BY `registered` DESC;");
		}
		
		return $is_subscriber;
	}
	function make_user_subscriber($userId, $productId, $interval, $amount) {
		if($interval == "1 YEAR") {
			$period = "years";
		}else 
		if($interval == "1 MONTH") {
			$period = "months";
		}else{
			exit;
		}
		
	//	$res = json_decode(file_get_contents("https://yoga15.com/app-assist/subscriber.php?id=$userId&action=create&auth=iloveyogalol&productId=$productId&price=$amount&period=$period"));
		
		$userId = urlencode($userId);
		$productId = urlencode($productId);
		$amount = urlencode($amount);
		$period = urlencode($period);
		
		$url = "https://yoga15.com/app-assist/subscriber.php?id=$userId&action=create&auth=iloveyogalol&productId=$productId&price=$amount&period=$period";
		
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/99.0.9999.999 Safari/537.36');
		
		$response = curl_exec($ch);
		
		if(curl_errno($ch)) {
			echo 'Curl error: ' . curl_error($ch);
		}
		
		curl_close($ch);
		
		$res = json_decode($response);
		
		return $res->success;
	}
	function unmake_user_subscriber($userId, $productId) {
		$userId = urlencode($userId);
		$productId = urlencode($productId);
		
		$url = "https://yoga15.com/app-assist/subscriber.php?id=$userId&action=remove&auth=iloveyogalol&productId=$productId";
		
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/99.0.9999.999 Safari/537.36');
		
		$response = curl_exec($ch);
		
		if(curl_errno($ch)) {
			echo 'Curl error: ' . curl_error($ch);
		}
		
		curl_close($ch);
		
		$res = json_decode($response);
		
		return $res->success;
	}
	function make_user_subscriber_old($userId, $productId, $interval, $amount) {
		global $MYSQL;
		global $WPDB;
	
		// Check if the user is already a subscriber
		$is_subscriber = get_user_subscriber($userId);
		
		if ($is_subscriber) {
			return;
		}
		
		// Execute the transaction query
		$MYSQL->query("
				INSERT INTO $WPDB.wpaa_mepr_transactions 
				(`user_id`, `product_id`, `amount`, `status`, `txn_type`, `created_at`, `expires_at`) 
				VALUES 
				('?', '?', '?', 'complete', 'payment', NOW(), DATE_ADD(NOW(), INTERVAL ?))
		", array(
			$userId,
			$productId,
			$amount,
			$interval
		));
		
		// Get the ID of the last inserted transaction
		$transaction_id = $MYSQL->getlastrowid();
		
		$mepr_member = $MYSQL->getrowq("SELECT * FROM $WPDB.wpaa_mepr_members WHERE `user_id` = '?'", array(
			$userId
		));
		
		$qf = null;
		
		// Execute the final query
		
		if(!$mepr_member) {
			$qf = $MYSQL->query("
					INSERT INTO $WPDB.wpaa_mepr_members 
					(`user_id`, `memberships`, `active_txn_count`, `sub_count`, `active_sub_count`, `latest_txn_id`, `first_txn_id`, `last_login_id`, `created_at`, `updated_at`) 
					VALUES 
					('?', '?', 1, 1, 1, '?', '?', '?', NOW(), NOW())
			", array(
				$userId,
				$productId,
				$transaction_id,
				$transaction_id,
				$transaction_id
			));
		}else{
			$qf = $MYSQL->query("
					UPDATE $WPDB.wpaa_mepr_members 
					SET `memberships` = '?', `active_txn_count` = 1, `sub_count` = `sub_count` + 1, `active_sub_count` = 1, `latest_txn_id` = '?', `first_txn_id` = '?', `created_at` = NOW(), `updated_at` = NOW(), `total_spent` = `total_spent` + ? WHERE `user_id` = '?'
			", array(
				$productId,
				$transaction_id,
				!$mepr_member["first_txn_id"] ? $transaction_id : $mepr_member["first_txn_id"],
				$amount,
				$userId
			));
		}
	
		return $qf;
	}
?>