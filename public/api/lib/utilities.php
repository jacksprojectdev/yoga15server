<?php
	$GLOBALS["RESOURCE_URL"] = 'https://yoga15.jacksproject.dev';
	$GLOBALS["ABI_ID"] = 1;
	
	$thumbWidth = 100;
	$thumbHeight = 100;
	
	require_once("auth.php");
	
	function generateRandomString($length = 10) {
		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$charactersLength = strlen($characters);
		$randomString = '';
		for ($i = 0; $i < $length; $i++) {
			$randomString .= $characters[random_int(0, $charactersLength - 1)];
		}
		return $randomString;
	}
	function underscoreToCamel($string) {
		$string = str_replace('_', ' ', $string);
		$string = ucwords($string);
		$string = str_replace(' ', '', $string);
		$string = lcfirst($string);
		return $string;
	}
	function getAccount($mp_user, $app_user) {
		$account = $app_user;
		
		foreach($mp_user as $k => $col) {
			$key = str_replace("user_", "", $k);
			$key = underscoreToCamel($key);
			
			$account[$key] = $col;
		}
		
		$account["username"] = $account["login"];
		$account["legacyName"] = $account["displayName"];
		$account["favourites"] = getNumList($account["favourites"]);
		
		unset($account["ID"]);
		unset($account["iD"]);
		unset($account["pass"]);
		unset($account["displayName"]);
		unset($account["alias"]);
		unset($account["nicename"]);
		
		return $account;
	}
	function getNumList($favouritesStr) {
		$favs = array();
		$expl = explode(",", $favouritesStr);
		
		foreach($expl as $id) {
			if(is_numeric($id) && $id) {
				$favs[] = $id;
			}
		}
		
		return $favs;
	}
	function getMetaObject($metaRows) {
		$meta = array();
		
		foreach($metaRows as $metaRow) {
			$meta[$metaRow["meta_key"]] = $metaRow["meta_value"];
		}
		
		return $meta;
	}
	function getTermIds($termRows) {
		$termIds = array();
		
		foreach($termRows as $termRow) {
			$termIds[] = $termRow["term_taxonomy_id"];
		}
		
		return $termIds;
	}
	function getTagNames($tagRows) {
		$tagNames = array();
		
		foreach($tagRows as $tagRow) {
			$tagNames[] = $tagRow["name"];
		}
		
		return $tagNames;
	}
	function getCategoryNames($categoryRows) {
		$categoryNames = array();
		
		foreach($categoryRows as $categoryRow) {
			$categoryNames[] = $categoryRow["name"];
		}
		
		return $categoryNames;
	}
	function extractExcerpt($text, $length = 100) {
		$text = str_replace(extractVimeoVideoUrl($text), "", $text);
		$text = removeComments($text, "wp:embed");
		$text = removeBoxTags($text);
		$text = strip_html_comments($text);
		$text = strip_tags($text);
		$text = html_entity_decode($text);
		$text = str_replace(array("\r", "\n"), '', $text);
		$text = trim($text);
		
		$excerpt = substr($text, 0, $length);
		
		// Trim the excerpt to the last complete word
		$last_space = strrpos($excerpt, ' ');
		if ($last_space !== false) {
			$excerpt = substr($excerpt, 0, $last_space);
		}
		
		// Add ellipsis if the excerpt was trimmed
		if (strlen($text) > strlen($excerpt)) {
			$excerpt .= '...';
		}
	
		return $excerpt;
	}
	function strip_html_comments($html) {
		$pattern = '/<!--(.*?)-->/s'; // Regular expression pattern to match HTML comments
		$stripped_html = preg_replace($pattern, '', $html);
		return $stripped_html;
	}
	function removeComments($html, $tagName) {
		$pattern = '/<!--\s*' . preg_quote($tagName, '/') . '.*?' . preg_quote($tagName, '/') . '\s*-->/s';
		$html = preg_replace($pattern, '', $html);
		return $html;
	}
	function removeBoxTags($text) {
		$pattern = '/\[[^\]]*\]/i';
		$text = preg_replace($pattern, '', $text);
		return $text;
	}
	function getLessonIds($stepsArr) {
		$lessons = array();
		
		foreach($stepsArr as $k => $step) {
			$lessons[] = $k;
		}
		
		return $lessons;
	}
	function getCourseProgressData($rawCprogs) {
		$cprogs = array();
		
		foreach($rawCprogs as $courseId => $progressObj) {
			$lessonsCompleted = array();
			
			foreach($progressObj["lessons"] as $lessonId => $isCompleted) {
				if($isCompleted)
					$lessonsCompleted[] = $lessonId;
			}
			
			$cprogs[$courseId] = $lessonsCompleted;
		}
		
		return $cprogs;
	}
	function extractVimeoVideoUrl($text) {
		$pattern = '/\bhttps?:\/\/(?:www\.)?vimeo\.com\/[a-zA-Z0-9\-]+\b/';
		
		preg_match($pattern, $text, $matches);
		
		if (!empty($matches)) {
			return $matches[0];
		}
	}
	function extract_embed_text($text) {
		$content = extract_embed_text_new($text);
		
		if(!$content)
			$content = extract_embed_text_legacy($text);
		
		return $content;
	}
	function extract_embed_text_new($content) {
		$pattern = '/<!-- wp:embed[^>]+>(.*?)<!-- \/wp:embed -->/s';
		preg_match($pattern, $content, $matches);
		return $matches[1] ?? '';
	}
	function extract_embed_text_legacy($content) {
		$pattern = '/<!-- wp:core-embed\/vimeo([^>]*)>(.*?)<!-- \/wp:core-embed\/vimeo -->/s';
		preg_match($pattern, $content, $matches);
		return $matches[1] ?? '';
	}
	function getVimeoURL($text) {
		return extractVimeoVideoUrl($text);
	}
	function stripEmbedContent($text) {
		$text = str_replace(extract_embed_text($text), "", $text); // Remove embedded section from text
		
		return $text;
	}
	function getLessonsOfCourse($courseId, $postMeta = null) {
		global $MYSQL;
		global $WPDB;
		
		if(!$postMeta) {
			$postMeta = getMetaObject($MYSQL->getrowsq("SELECT * FROM $WPDB.wpaa_postmeta WHERE `post_id` = $courseId"));
		}
		
		$unserializedSteps = unserialize($postMeta["ld_course_steps"]);
		$lessonIds = getLessonIds($unserializedSteps["steps"]["h"]["sfwd-lessons"]);
		$lessonIdsStr = implode(",", $lessonIds);
		$lessons = $MYSQL->getrowsq("SELECT `ID` as `id`,`post_title` AS `name` FROM $WPDB.wpaa_posts WHERE `ID` IN (?) ORDER BY FIELD(`ID`, ?)", array(
			$lessonIdsStr,
			$lessonIdsStr
		));
		
		foreach($lessons as $k => $lesson) {
			$lessons[$k]["name"] = html_entity_decode($lesson["name"]);
		}
		
		return $lessons;
	}
	function getSearchFilterSettings() {
		global $MYSQL;
		global $WPDB;
		
		// Meta row for search filter settings (specific to the yoga video search filter form)
		$metaRow = $MYSQL->getrowq("SELECT * FROM $WPDB.wpaa_postmeta WHERE `meta_id` = 147726");
		
		return unserialize($metaRow["meta_value"]);
	}
	function fixAttachment($url) {
		$url = str_replace("http://146.66.104.62/~yogaform/", "https://", $url);
		
		return $url;
	}
	function prepareBody($text) {
		$text = str_replace(extractVimeoVideoUrl($text), "", $text);
		$text = removeComments($text, "wp:embed");
		$text = removeBoxTags($text);
		$text = strip_html_comments($text);
		$text = html_entity_decode($text);
		$text = str_replace(array("\r", "\n"), '', $text);
		$text = str_replace("<p></p>", '', $text);
		$text = trim($text);
		
		return $text;
	}
	function getPlaylists($playlists) {
		global $MYSQL;
		global $WPDB;
		
		foreach($playlists as $k => $playlist) {
			$videoIds = getNumList($playlists[$k]["videos"]);
			
			/**
				Get first thumbnail of video for playlist
			*/
				// First video id
				$firstPostId = $videoIds[0];
				$thumb = "";
				
				if($firstPostId) {
					// Get the meta rows for the first post ID
					$postMeta = getMetaObject($MYSQL->getrowsq("SELECT * FROM $WPDB.wpaa_postmeta WHERE `post_id` = $firstPostId"));
					
					// Get the thumbnail attachment post
					$attachment = $MYSQL->getrowq("SELECT `guid` FROM $WPDB.wpaa_posts WHERE `ID` = '?'", array($postMeta["_thumbnail_id"]));
					
					// Thumb URL
					$thumb = getResourceImage(fixAttachment($attachment["guid"]), $thumbWidth, $thumbHeight);
				}
				
				if(!$thumb)
					$thumb = "https://i.imgur.com/ksyNsvF.png";
			
			
			$playlists[$k]["videos"] = $videoIds;
			$playlists[$k]["watched"] = getNumList($playlists[$k]["watched"]);
			$playlists[$k]["thumb"] = $thumb;
		}
		
		return $playlists;
	}
	function getResourceImage($guid, $width = 512, $height = 512) {
		global $RESOURCE_URL;
		
		$prefix = "https://yoga15.com/";
		$downloadUrl = $guid;
		
		$filename = sha1('y15v3' . $downloadUrl);
		
		if(file_exists("/var/www/yoga15/resources/$filename.png")) {
			return "$RESOURCE_URL/resources/$filename.png";
		}
		
		require_once('image.php');
		
		$path = downloadResizeImageAndSave($downloadUrl, $width, $height);
		
		//if(substr($guid, 0, strlen($prefix)) == $prefix && !$path)
		//	return "https://yoga15.com/image.php?url=" . urlencode($guid) . "&width=$width&height=$height";
		
		if(!$path)
			return $downloadUrl;
			
		return str_replace("/var/www/yoga15", $RESOURCE_URL, $path);
	}
	function getDisplayName($account) {
		return $account["nickname"] ?? $account["legacyName"] ?? trim($account["firstName"] . " " . $account["lastName"]);
	}
	function getUnreads($id) {
		global $MYSQL;
		
		$unreads = $MYSQL->getrowq("SELECT count(*) as `count` FROM messages WHERE `read` = 0 AND `recepient` = '?'", array($id))["count"];
		$unreads = intval($unreads);
		
		return $unreads;
	}
	function processNewAppUser($account) {
		global $MYSQL;
		global $ABI_ID;
		
		if(!$account["id"]) {
			return;
		}
		
		$name = getDisplayName($account);
		$e = explode(" ", $name);
		$name = $e[0];
		
		$MYSQL->query("INSERT INTO `messages` (`recepient`, `sender`, `sent`, `message`) VALUES ('?', '?', '?', '?')", array(
			$account["id"],
			$ABI_ID,
			time(),
			"Hi $name! I'm Abi, your personal yoga instructor. Please feel free to ask me any questions that you have."
		));
	}
	function getShareInfo($videoPost) {
		return html_entity_decode($videoPost["url"]) . " - " . html_entity_decode($videoPost["post_title"]) . " on Yoga 15 - Become a better athlete in 15 minutes a day!";
	}
?>