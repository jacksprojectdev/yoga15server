<?php
	error_reporting(0);
	
	require_once("../lib/mysql.php");
	require_once("../lib/utilities.php");
	require_once("../lib/video.php");
	
	$lessonId = intval($_GET["lessonId"]);
	$courseId = intval($_GET["courseId"]);
	
	authenticate();
	subscriber_check();
	
	if(!$lessonId) {
		die(json_encode(array(
			"error" => "Invalid lesson ID ($lessonId)"
		)));
	}
	if(!$courseId) {
		die(json_encode(array(
			"error" => "Invalid course ID ($courseId)"
		)));
	}
	
	// Get course post
	$coursePost = $MYSQL->getrowq("SELECT *,`ID` as `id` FROM $WPDB.wpaa_posts WHERE `ID` = '?'", array($courseId));
	
	// Get lesson post
	$lessonPost = $MYSQL->getrowq("SELECT *,`ID` as `id` FROM $WPDB.wpaa_posts WHERE `ID` = '?'", array($lessonId));
	
	if(!$coursePost) {
		die(json_encode(array(
			"error" => "Course not found"
		)));
	}
	if(!$lessonPost) {
		die(json_encode(array(
			"error" => "Lesson not found"
		)));
	}
	
	// Get the meta rows
	$postMeta = getMetaObject($MYSQL->getrowsq("SELECT * FROM $WPDB.wpaa_postmeta WHERE `post_id` = $lessonId"));
	
	// Activity for course on user
	$courseActivity = $MYSQL->getrowq("SELECT * FROM $WPDB.wpaa_learndash_user_activity WHERE `course_id` = '?' AND `user_id` = '?' AND `activity_type` = 'course'", array(
		$courseId,
		$ACCOUNT["id"]
	));
	
	// Activity for lesson on user
	$activity = $MYSQL->getrowq("SELECT * FROM $WPDB.wpaa_learndash_user_activity WHERE `post_id` = '?' AND `course_id` = '?' AND `user_id` = '?'", array(
		$lessonId,
		$courseId,
		$ACCOUNT["id"]
	));
	
	if(!$courseActivity) {
		// If no course activity row, create it
		$q = $MYSQL->query("INSERT INTO $WPDB.wpaa_learndash_user_activity (`user_id`, `post_id`, `course_id`, `activity_type`, `activity_status`, `activity_started`, `activity_updated`) VALUES ('?', '?', '?', '?', '?', '?', '?')", array(
			$ACCOUNT["id"],
			$courseId,
			$courseId,
			"course",
			0,
			time(),
			time()
		));
		
		if(!$q) {
			die(json_encode(array(
				"error" => "Could not update course. " . $MYSQL->error()
			)));
		}
	}
	if(!$activity) {
		// If no activity row, create it
		$q = $MYSQL->query("INSERT INTO $WPDB.wpaa_learndash_user_activity (`user_id`, `post_id`, `course_id`, `activity_type`, `activity_status`, `activity_started`, `activity_updated`) VALUES ('?', '?', '?', '?', '?', '?', '?')", array(
			$ACCOUNT["id"],
			$lessonId,
			$courseId,
			"lesson",
			0,
			time(),
			time()
		));
		
		if(!$q) {
			die(json_encode(array(
				"error" => "Could not update activity. " . $MYSQL->error()
			)));
		}
	}
	
	switch($_GET["action"]) {
		case "get":
			// Get the thumbnail attachment post
			$attachment = $MYSQL->getrowq("SELECT `guid` FROM $WPDB.wpaa_posts WHERE `ID` = '?'", array($postMeta["_thumbnail_id"]));
			
			// Get the appropriate description
			$desc = extractExcerpt($lessonPost["post_content"], 120);
			
			// Get the vimeo video URL embed
			$vimeoURL = getVimeoURL($lessonPost["post_content"]);
			
			// Get the config for video playback (incl. HLS URI for example)
			$videoConfig = getVideoConfig($vimeoURL);
			
			// Remove embed content from text
			$body = prepareBody($lessonPost["post_content"]);
			
			// Get permalink
			$post = $lessonPost;
			
			$permalink = file_get_contents("https://yoga15.com/app-assist/permalink.php?id=" . $post["id"]);
			$post["url"] = $permalink ?? html_entity_decode($post["guid"]);
			
			die(json_encode(array(
				"success" => true,
				"lesson" => array(
					"id" => $post["id"],
					"name" => $post["post_title"],
					"desc" => $desc,
					"thumb" => $attachment["guid"],
					"postURL" => $post["url"],
					"body" => $body,
					"video" => $videoConfig,
					"shareInfo" => getShareInfo($post)
				)
			), JSON_NUMERIC_CHECK));
		break;
		case "finish":
			// Set activity completed locally
			$activity["activity_completed"] = time();
			
			// Get lessons of course
			$lessons = getLessonsOfCourse($courseId);
			
			// Get the amount of completed lessons by this user
			$res = $MYSQL->getrowq("SELECT count(*) as `completed` FROM $WPDB.wpaa_learndash_user_activity WHERE `activity_type` = 'lesson' AND `activity_status` = 1 AND `course_id` = '?' AND `user_id` = '?'", array(
				$courseId,
				$ACCOUNT["id"]
			));
			$completedLessons = intval($res["completed"]);
			
			// IF fully completed 
			if($completedLessons+1 == count($lessons)) {
				$MYSQL->query("INSERT INTO coursesfinished (`mpid`, `courseId`, `time`) VALUES ('?', '?', '?')", array(
					$ACCOUNT["id"],
					$courseId,
					time()
				));
			}
			
			// Update course activity
			$MYSQL->query("UPDATE $WPDB.wpaa_learndash_user_activity SET `activity_completed` = '?', `activity_updated` = '?', `activity_status` = '?' WHERE `activity_id` = '?'", array(
				time(),
				time(),
				count($lessons) == ($completedLessons + 1) ? 1 : 0,
				$courseActivity["activity_id"]
			));
			
			// Update lesson activity
			$MYSQL->query("UPDATE $WPDB.wpaa_learndash_user_activity SET `activity_completed` = '?', `activity_updated` = '?', `activity_status` = 1 WHERE `activity_id` = '?'", array(
				time(),
				time(),
				$activity["activity_id"]
			));
			
			die(json_encode(array(
				"success" => true,
				"lprog" => array(
					"lessonId" => $activity["post_id"],
					"courseId" => $activity["course_id"],
					"updatedAt" => $activity["activity_updated"],
					"completedAt" => $activity["activity_completed"]
				)
			), JSON_NUMERIC_CHECK));
		break;
		default:
			die(json_encode(array(
				"error" => "Invalid request"
			)));
		break;
	}
?>