<?php
	/* Config vars */
	$MAX_MESSAGE_LENGTH = 4096;
	
	error_reporting(0);
	
	require_once("../lib/mysql.php");
	require_once("../lib/utilities.php");
	require_once("../lib/notify.php");
	
	authenticate();
	
	$action = $_GET["action"];
	
	switch($action) {
		case "contacts":
			// Standard messages
			$messages = $MYSQL->getrowsq("SELECT * FROM messages WHERE (`sender` = '?' OR `recepient` = '?') ORDER BY `sent` DESC", array(
				$ACCOUNT["id"],
				$ACCOUNT["id"]
			));
			
			// Contacts array
			$contacts = array();
			
			foreach($messages as $message) {
				$person = $message["recepient"] != $ACCOUNT["id"] ? $message["recepient"] : $message["sender"]; // is an id
				$appuser = $MYSQL->getrowq("SELECT * FROM `users` WHERE `id` = '?'", array($person));
				$mpaccount = $MYSQL->getrowq("SELECT * FROM `$WPDB`.`wpaa_users` WHERE `ID` = '?'", array($appuser["id"]));
				
				$muted = $MYSQL->getrowq("SELECT * FROM `muted` WHERE `mpid` = '?' AND `mutedId` = '?'", array(
					$ACCOUNT["id"],
					$person
				));
				$hidden = $MYSQL->getrowq("SELECT * FROM `hidecontacts` WHERE `mpid` = '?' AND `contactId` = '?'", array(
					$ACCOUNT["id"],
					$person
				));
				
				$isMuted = $muted != null;
				
				if(!$appuser || !$mpaccount || $hidden != null)
					continue;
				
				$contact = getAccount($mpaccount, $appuser);
				
				if(!isset($contacts[$person])) {
					$contacts[$person] = array(
						"id" => $contact["id"],
						"fullName" => getDisplayName($contact),
						"photo" => $contact["photo"] ?? "",
						"message" => $message,
						"muted" => $isMuted ? 1 : 0,
						"time" => $message["sent"]
					);
				}
			}
			
			usort($contacts, function($a, $b) {
				return $a['time'] <=> $b['time'];
			});
			
			die(json_encode(array(
				"success" => true,
				"contacts" => $contacts,
				"unreads" => getUnreads($ACCOUNT["id"])
			), JSON_NUMERIC_CHECK));
		break;
		case "messages":
			$contactId = $_GET["contactId"];
			
			// Subscriber only
			//subscriber_check();
			
			if(!$contactId || !is_numeric($contactId)) {
				die(json_encode(array(
					"error" => "Invalid contact ID $contactId"
				)));
			}
			
			$messages = $MYSQL->getrowsq("SELECT * FROM messages WHERE (`sender` = '?' AND `recepient` = '?') OR (`sender` = '?' AND `recepient` = '?') ORDER BY `sent` DESC LIMIT 50", array(
				$contactId,
				$ACCOUNT["id"],
				$ACCOUNT["id"],
				$contactId
			));
			
			$messages = array_reverse($messages);
			
			/*if(count($messages) < 1) {
				die(json_encode(array(
					"error" => "No messages $contactId"
				)));
			}*/
			
			// Set read time
			$MYSQL->query("UPDATE messages SET `read` = '?' WHERE (`sender` = '?' AND `recepient` = '?') AND `read` = 0", array(
				time(),
				$contactId,
				$ACCOUNT["id"]
			));
			
			die(json_encode(array(
				"success" => true,
				"messages" => $messages,
				"unreads" => getUnreads($ACCOUNT["id"])
			), JSON_NUMERIC_CHECK));
		break;
		case "message":
			$contactId = $_POST["contactId"];
			$message = substr($_POST["message"], 0, $MAX_MESSAGE_LENGTH);
			
			if($message{0} == "/") {
				die(json_encode(array(
					"success" => true
				)));
			}
			
			if(!$contactId || !is_numeric($contactId)) {
				die(json_encode(array(
					"error" => "Invalid contact ID $contactId"
				)));
			}
			
			$q = $MYSQL->query("INSERT INTO messages (`recepient`, `sender`, `sent`, `message`) VALUES ('?', '?', '?', '?')", array(
				$contactId,
				$ACCOUNT["id"],
				time(),
				$message
			));
			
			if(!$q) {
				die(json_encode(array(
					"error" => "Error sending message: " . $MYSQL->error()
				)));
			}
			
			die(json_encode(array(
				"success" => true
			)));
		break;
		default:
			die(json_encode(array(
				"error" => "Invalid action"
			)));
		break;
	}
?>