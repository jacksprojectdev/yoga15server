<?php
	error_reporting(0);
	
	require_once("../lib/mysql.php");
	require_once("../lib/utilities.php");
	require_once("../lib/subscriber.php");
	
	authenticate();
	
	switch($_GET["action"]) {
		case "get":
			$courseIds = array();
			
			$myCourseActivity = $MYSQL->getrowsq("SELECT * FROM $WPDB.wpaa_learndash_user_activity WHERE `user_id` = '?' AND `activity_type` = 'course' ORDER BY `activity_updated` DESC LIMIT 5", array(
				$ACCOUNT["id"]
			));
			
			foreach($myCourseActivity as $courseActivity) {
				$courseIds[] = intval($courseActivity["course_id"]);
			}
			
			$courseIdsStr = implode(",", $courseIds);
			
			$coursePosts = $MYSQL->getrowsq("SELECT * ,`ID` as id FROM $WPDB.wpaa_posts WHERE `ID` IN (?) ORDER BY FIELD(`ID`, ?)", array(
				$courseIdsStr,
				$courseIdsStr
			));
			
			$courses = array();
			
			foreach($coursePosts as $coursePost) {
				// Get course id
				$courseId = $coursePost["id"];
				
				// Get the meta rows
				$postMeta = getMetaObject($MYSQL->getrowsq("SELECT * FROM $WPDB.wpaa_postmeta WHERE `post_id` = $courseId"));
				
				// Get the associated category
				$category = $MYSQL->getrowq("SELECT `term_id` AS id, `name` FROM $WPDB.wpaa_terms WHERE `term_id` IN (SELECT `term_taxonomy_id` FROM $WPDB.wpaa_term_relationships WHERE `object_id` = $courseId) AND `term_id` IN (SELECT `term_taxonomy_id` FROM $WPDB.wpaa_term_taxonomy WHERE `taxonomy` = 'ld_course_category') LIMIT 1");
				
				// Get the associated tags
				$tags = $MYSQL->getrowsq("SELECT `term_id` AS id, `name` FROM $WPDB.wpaa_terms WHERE `term_id` IN (SELECT `term_taxonomy_id` FROM $WPDB.wpaa_term_relationships WHERE `object_id` = $courseId) AND `term_id` IN (SELECT `term_taxonomy_id` FROM $WPDB.wpaa_term_taxonomy WHERE `taxonomy` = 'post_tag')");
				$tagNames = getTagNames($tags);
				
				// Get the thumbnail attachment post
				$attachment = $MYSQL->getrowq("SELECT `guid` FROM $WPDB.wpaa_posts WHERE `ID` = '?'", array($postMeta["_thumbnail_id"]));
				
				// Get the appropriate description
				$desc = extractExcerpt($coursePost["post_content"], 120);
				
				// Get the lessons
				$lessons = getLessonsOfCourse($courseId, $postMeta);
				
				// Process thumb
				$thumb = getResourceImage(fixAttachment($attachment["guid"]), $thumbWidth, $thumbHeight);
				
				$courses[] = array(
					"id" => $coursePost["id"],
					"name" => $coursePost["post_title"],
					"desc" => $desc,
					"thumb" => $thumb,
					"category" => $category,
					"tags" => $tags,
					"lessons" => $lessons
				);
			}
			
			$playlists = getPlaylists($MYSQL->getrowsq("SELECT * FROM playlists WHERE `mpid` = '?' ORDER BY `updatedAt` DESC LIMIT 5", array($ACCOUNT["id"])));
			
			die(json_encode(array(
				"success" => true,
				"recentCourses" => $courses,
				"recentPlaylists" => $playlists,
				"unreads" => getUnreads($ACCOUNT["id"]),
				"subscriber" => get_user_subscriber($ACCOUNT["id"])
			), JSON_NUMERIC_CHECK));
		break;
		default:
			die(json_encode(array(
				"error" => "Invalid request"
			)));
		break;
	}
?>