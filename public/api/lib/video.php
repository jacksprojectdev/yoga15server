<?php
	require_once("vimeo/autoload.php");
	
	
	$VIMEO_ACCESS_TOKEN = "42c308a32671ace307ea5fac63c33f60";
	$VIMEO_CLIENT_SECRET = "tawzz8U4Nwoa3hCjby8ydnXH0ujLwBjm4mWhNqfEIFpO8OwNk6OFq7f1y6LPXwA/miU1NAG6YNbhqRydxscA/yrI7Xa0eWonLx9HoGzIwxDRC0wGh6Ltt4xCc7yl2A7u";
	$VIMEO_CLIENT_ID = "01cf4bcaff6bf9f4bf966d763f69a140f65f2a3b";
	
	use Vimeo\Vimeo;
	
	function getVideoConfig($url) {
		if(stristr($url, "vimeo.com"))
			return getVimeoConfig($url);
	}
	function getVimeoConfig($url) {
		global $VIMEO_ACCESS_TOKEN;
		global $VIMEO_CLIENT_SECRET;
		global $VIMEO_CLIENT_ID;
		
		// Instantiate the Vimeo client with your access token
		$vimeo = new Vimeo($VIMEO_CLIENT_ID, $VIMEO_CLIENT_SECRET, $VIMEO_ACCESS_TOKEN);
		
		// Get video ID (number)
		$videoId = explode("/", explode("vimeo.com/", $url)[1])[0];
		//$videoId = 845086382;
		
		try {
			// Request the video metadata from Vimeo API
			$response = $vimeo->request("/videos/$videoId");
			
			if($response["body"]["error"]) {
				return array(
					"error" => $response["body"]["developer_message"]
				);
			}
			
			// 720p
			$file = $response["body"]["files"][2];
			
			// 640x360
			$thumb = $response["body"]["pictures"]["sizes"][3];
			
			return array(
				"url" => $file["link"],
				"thumb" => $thumb["link"],
				"width" => $file["width"],
				"height" => $file["height"],
				"duration" => $response["body"]["duration"]
			);
		} catch (VimeoException $e) {
			// Handle exceptions if any
			return array(
				"error" => $e->getMessage()
			);
		}
	}
	function getVideos($videoPosts) {
		$videos = array();
		
		foreach($videoPosts as $videoPost) {
			$video = getVideo($videoPost);
			
			$videos[] = $video;
		}
		
		return $videos;
	}
	function getVideo($videoPost) {
		global $MYSQL;
		global $WPDB;
		
		global $thumbWidth;
		global $thumbHeight;
		
		// Video ID
		$videoId = $videoPost["id"];
		
		// Get post info local
		$postInfo = $MYSQL->getrowq("SELECT * FROM `posts` WHERE `postId` = '?'", array($videoId));
		
		if(!$postInfo || time() - $postInfo["time"] > 86400) {
			// Get the meta rows
			$postMeta = getMetaObject($MYSQL->getrowsq("SELECT * FROM $WPDB.wpaa_postmeta WHERE `post_id` = $videoId"));
			
			// Get the associated category
			$categories = $MYSQL->getrowsq("SELECT `term_id` AS id, `name` FROM $WPDB.wpaa_terms WHERE `term_id` IN (SELECT `term_taxonomy_id` FROM $WPDB.wpaa_term_relationships WHERE `object_id` = $videoId) AND `term_id` IN (SELECT `term_taxonomy_id` FROM $WPDB.wpaa_term_taxonomy WHERE `taxonomy` = 'category') AND `name` != 'post-format-video'");
			
			// Get the associated tags
			$tags = $MYSQL->getrowsq("SELECT `term_id` AS id, `name` FROM $WPDB.wpaa_terms WHERE `term_id` IN (SELECT `term_taxonomy_id` FROM $WPDB.wpaa_term_relationships WHERE `object_id` = $videoId) AND `term_id` IN (SELECT `term_taxonomy_id` FROM $WPDB.wpaa_term_taxonomy WHERE `taxonomy` = 'post_tag')");
			
			// Get the thumbnail attachment post
			$attachment = $MYSQL->getrowq("SELECT `guid` FROM $WPDB.wpaa_posts WHERE `ID` = '?'", array($postMeta["_thumbnail_id"]));
			
			// Get the appropriate description
			$desc = extractExcerpt($videoPost["post_content"], 100);
			
			// Process thumb
			$thumb = getResourceImage(fixAttachment($attachment["guid"]), $thumbWidth, $thumbHeight);
			
			$MYSQL->query("DELETE FROM `posts` WHERE `postId` = '?'", array($videoId));
			$MYSQL->query("INSERT INTO `posts` (`postId`, `name`, `meta`, `thumb`, `desc`, `tags`, `categories`, `time`) VALUES ('?', '?', '?', '?', '?', '?', '?', '?')", array(
				$videoId,
				html_entity_decode($videoPost["post_title"]),
				json_encode($postMeta),
				$thumb,
				$desc,
				json_encode($tags),
				json_encode($categories),
				time()
			));
		}else{
			$postMeta = json_decode($postInfo["meta"], true);
			$categories = json_decode($postInfo["categories"], true);
			$tags = json_decode($postInfo["tags"], true);
			$desc = $postInfo["desc"];
			$thumb = $postInfo["thumb"];
		}
		
		// If the post thumb is null try the vimeo thumb
		if($thumb == null && false) {
			// Get the vimeo video URL embed
			$vimeoURL = getVimeoURL($videoPost["post_content"]);
			
			// Get the config for video playback (incl. HLS URI for example)
			$videoConfig = getVideoConfig($vimeoURL);
			
			$thumb = $videoConfig["thumb"];
		}
		
		$video = array(
			"id" => $videoPost["id"],
			"name" => html_entity_decode($videoPost["post_title"]),
			"desc" => $desc,
			"thumb" => $thumb,
			"categories" => $categories,
			"tags" => $tags
		);
		
		return $video;
	}
?>