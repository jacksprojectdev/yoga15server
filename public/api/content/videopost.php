<?php
	error_reporting(0);
	
	require_once("../lib/mysql.php");
	require_once("../lib/utilities.php");
	require_once("../lib/video.php");
	
	$videoPostId = intval($_GET["id"]);
	
	authenticate();
	subscriber_check();
	
	if(!$videoPostId) {
		die(json_encode(array(
			"error" => "Invalid video ID ($videoPostId)"
		)));
	}
	
	// Get video post
	$videoPost = $MYSQL->getrowq("SELECT *,`ID` as `id` FROM $WPDB.wpaa_posts WHERE `ID` = '?'", array($videoPostId));
	$video = getVideo($videoPost);
	
	if(!$videoPost) {
		die(json_encode(array(
			"error" => "Video not found"
		)));
	}
	
	switch($_GET["action"]) {
		case "get":
			// Get the vimeo video URL embed
			$vimeoURL = getVimeoURL($videoPost["post_content"]);
			
			// Get the config for video playback (incl. HLS URI for example)
			$videoConfig = getVideoConfig($vimeoURL);
			
			if(!$videoConfig) {
				die(json_encode(array(
					"error" => "Sorry, we could not find the video attached to this post. URL: $vimeoURL"
				)));
			}
			
			// Remove embed content from text
			$body = prepareBody($videoPost["post_content"]);
			
			// Get permalink
			$post = $videoPost;
			
			$permalink = file_get_contents("https://yoga15.com/app-assist/permalink.php?id=" . $post["id"]);
			$post["url"] = $permalink ?? html_entity_decode($post["guid"]);
			
			die(json_encode(array(
				"success" => true,
				"videoPost" => array(
					"id" => $post["id"],
					"name" => $post["post_title"],
					"desc" => $video["desc"],
					"thumb" => $video["thumb"],
					"postURL" => $permalink ?? html_entity_decode($post["guid"]),
					"body" => $body,
					"video" => $videoConfig,
					"shareInfo" => getShareInfo($post)
				)
			), JSON_NUMERIC_CHECK));
		break;
		case "favourite":
			// Check if the video is already favourited
			if(in_array($videoPost["id"], $ACCOUNT["favourites"])) {
				die(json_encode(array(
					"error" => "This video is already in your favourites"
				)));
			}
			
			// Add to array
			$ACCOUNT["favourites"][] = $videoPost["id"];
			
			// Sync to database
			$q = $MYSQL->query("UPDATE users SET `favourites` = '?' WHERE `id` = '?'", array(
				implode(",", $ACCOUNT["favourites"]),
				$ACCOUNT["id"]
			));
			
			// Return result
			if($q) {
				die(json_encode(array(
					"success" => true
				)));
			}else{
				die(json_encode(array(
					"error" => "Something went wrong favouriting this video, please report this bug: " . $MYSQL->error()
				)));
			}
		break;
		case "unfavourite":
			// Check if the video is already not favourited
			if(!in_array($videoPost["id"], $ACCOUNT["favourites"])) {
				die(json_encode(array(
					"error" => "This video is not favourited already"
				)));
			}
			
			// Remove from array
			foreach($ACCOUNT["favourites"] as $k => $videoId) {
				if($videoId == $videoPost["id"]) {
					unset($ACCOUNT["favourites"][$k]);
					break;
				}
			}
			
			// Sync to database
			$q = $MYSQL->query("UPDATE users SET `favourites` = '?' WHERE `id` = '?'", array(
				implode(",", $ACCOUNT["favourites"]),
				$ACCOUNT["id"]
			));
			
			// Return result
			if($q) {
				die(json_encode(array(
					"success" => true
				)));
			}else{
				die(json_encode(array(
					"error" => "Something went wrong unfavouriting this video, please report this bug: " . $MYSQL->error()
				)));
			}
		break;
		default:
			die(json_encode(array(
				"error" => "Invalid request"
			)));
		break;
	}
?>