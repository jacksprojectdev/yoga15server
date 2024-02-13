<?php
	error_reporting(0);
	
	require_once("../lib/mysql.php");
	require_once("../lib/utilities.php");
	require_once("../lib/video.php");
	
	function _getVideoPosts($videoIds) {
		global $MYSQL;
		global $WPDB;
		
		if(empty($videoIds))
			return array();
		
		return $MYSQL->getrowsq("SELECT *, `ID` AS `id` FROM $WPDB.wpaa_posts WHERE `id` IN (?) ORDER BY FIELD(`id`, ?)", array(
			implode(",", $videoIds),
			implode(",", $videoIds)
		));
	}
	
	authenticate();
	
	$playlistId = $_GET["id"];
	$action = $_GET["action"];
	
	$playlist = $MYSQL->getrowq("SELECT * FROM playlists WHERE `id` = '?'", array($playlistId));
	
	if(!$playlist) {
		die(json_encode(array(
			"error" => "No such playlist $playlistId $action"
		)));
	}
	if($playlist["mpid"] != $ACCOUNT["id"]) {
		die(json_encode(array(
			"error" => "You do not own this playlist"
		)));
	}
	
	$videoIds = getNumList($playlist["videos"]);
	
	if(empty($videoIds)) {
		$videoPosts = array();
	}else{
		$videoPosts = _getVideoPosts($videoIds);
		
		if(!$videoPosts) {
			die(json_encode(array("error" => "Video post load error: " . $MYSQL->error())));
		}
	}
	
	$playlist["videos"] = getVideos($videoPosts);
	$playlist["watched"] = getNumList($playlist["watched"]);
	
	switch($_GET["action"]) {
		case "get":
			die(json_encode(array(
				"success" => true,
				"playlist" => $playlist
			), JSON_NUMERIC_CHECK));
		break;
		case "save":
			$videoIds = getNumList($_POST["videos"]);
			$videoPosts = _getVideoPosts($videoIds);
			
			if($videoPosts === null) {
				die(json_encode(array("error" => "Video post load error 2: " . $MYSQL->error())));
			}
			
			$playlist["videos"] = getVideos($videoPosts);
			$playlist["watched"] = getNumList($_POST["watched"]);
			
			$q = $MYSQL->query("UPDATE playlists SET `videos` = '?', `watched` = '?', `updatedAt` = '?' WHERE id = '?'", array(
				implode(",", $videoIds),
				implode(",", $playlist["watched"]),
				time(),
				$playlist["id"]
			));
			
			if(!$q) {
				die(json_encode(array(
					"error" => "Saving error: " . $MYSQL->error()
				)));
			}
			
			die(json_encode(array(
				"success" => true,
				"playlist" => $playlist
			), JSON_NUMERIC_CHECK));
		break;
		case "rename":
			$videoId = $_GET["videoId"];
			$newName = $_POST["name"];
			
			if(!$newName) {
				die(json_encode(array(
					"error" => "Invalid name"
				)));
			}
			
			$q = $MYSQL->query("UPDATE playlists SET `name` = '?' WHERE id = '?'", array(
				$newName,
				$playlist["id"]
			));
			
			if(!$q) {
				die(json_encode(array(
					"error" => "Renaming error: " . $MYSQL->error()
				)));
			}
			
			$playlist["name"] = $newName;
			
			die(json_encode(array(
				"success" => true,
				"playlist" => $playlist
			), JSON_NUMERIC_CHECK));
		break;
		case "add":
			$videoId = $_GET["videoId"];
			
			if(in_array($videoId, $videoIds)) {
				die(json_encode(array(
					"error" => "Video already in playlist"
				)));
			}
			if(count($videoIds) >= 50) {
				die(json_encode(array(
					"error" => "Playlist is full"
				)));
			}
			
			$videoIds[] = $videoId;
			
			$q = $MYSQL->query("UPDATE playlists SET `videos` = '?', `updatedAt` = '?' WHERE id = '?'", array(
				implode(",", $videoIds),
				time(),
				$playlist["id"]
			));
			
			if(!$q) {
				die(json_encode(array(
					"error" => "Saving error 2: " . $MYSQL->error()
				)));
			}
			
			die(json_encode(array(
				"success" => true,
				"playlist" => $playlist
			), JSON_NUMERIC_CHECK));
		break;
		case "delete":
			$q = $MYSQL->query("DELETE FROM playlists WHERE id = '?'", array(
				$playlist["id"]
			));
			
			if(!$q) {
				die(json_encode(array(
					"error" => "Deleting error: " . $MYSQL->error()
				)));
			}
			
			die(json_encode(array(
				"success" => true
			), JSON_NUMERIC_CHECK));
		break;
		case "mark":
			$videoId = $_GET["videoId"];
			
			if(!in_array($videoId, $videoIds)) {
				die(json_encode(array(
					"error" => "Video is not in playlist"
				)));
			}
			
			if(!in_array($videoId, $playlist["watched"])) {
				$playlist["watched"][] = $videoId;
				
				$q = $MYSQL->query("UPDATE playlists SET `watched` = '?', `updatedAt` = '?' WHERE id = '?'", array(
					implode(",", $playlist["watched"]),
					time(),
					$playlist["id"]
				));
				
				if(!$q) {
					die(json_encode(array(
						"error" => "Saving error 3: " . $MYSQL->error()
					)));
				}
			}
			
			die(json_encode(array(
				"success" => true,
				"playlist" => $playlist
			), JSON_NUMERIC_CHECK));
		break;
		default:
			die(json_encode(array(
				"error" => "Invalid request"
			)));
		break;
	}
?>