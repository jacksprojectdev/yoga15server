<?php
	error_reporting(0);
	
	require_once("../lib/mysql.php");
	require_once("../lib/utilities.php");
	require_once("../lib/wordpress/WordpressToJSX.php");
	
	$lessonId = intval($_GET["lessonId"]);
	$courseId = intval($_GET["courseId"]);
	
	authenticate();
	
	$courseId = intval($_GET["id"]);
	
	if(!$courseId) {
		die(json_encode(array(
			"error" => "Invalid course ID ($courseId)"
		)));
	}
	
	// Get course post
	$coursePost = $MYSQL->getrowq("SELECT *,`ID` as `id` FROM $WPDB.wpaa_posts WHERE `ID` = '?'", array($courseId));
	
	if(!$coursePost) {
		die(json_encode(array(
			"error" => "Course not found"
		)));
	}
	
	switch($_GET["action"]) {
		case "get":
			// Get the meta rows
			$postMeta = getMetaObject($MYSQL->getrowsq("SELECT * FROM $WPDB.wpaa_postmeta WHERE `post_id` = $courseId"));
			
			// Get the associated category
			$category = $MYSQL->getrowq("SELECT `term_id` AS id, `name` FROM $WPDB.wpaa_terms WHERE `term_id` IN (SELECT `term_taxonomy_id` FROM $WPDB.wpaa_term_relationships WHERE `object_id` = $courseId) AND `term_id` IN (SELECT `term_taxonomy_id` FROM $WPDB.wpaa_term_taxonomy WHERE `taxonomy` = 'ld_course_category') LIMIT 1");
			
			// Get the associated tags
			$tags = $MYSQL->getrowsq("SELECT `term_id` AS id, `name` FROM $WPDB.wpaa_terms WHERE `term_id` IN (SELECT `term_taxonomy_id` FROM $WPDB.wpaa_term_relationships WHERE `object_id` = $courseId) AND `term_id` IN (SELECT `term_taxonomy_id` FROM $WPDB.wpaa_term_taxonomy WHERE `taxonomy` = 'post_tag')");
			$tagNames = getTagNames($tags);
			
			// Get the thumbnail attachment post
			$attachment = $MYSQL->getrowq("SELECT `guid` FROM $WPDB.wpaa_posts WHERE `ID` = '?'", array($postMeta["_thumbnail_id"]));
			
			// Get the steps
			$lessons = getLessonsOfCourse($courseId, $postMeta);
			$sections = json_decode($postMeta["course_sections"]);
			
			// Get the appropriate description
			$desc = extractExcerpt($coursePost["post_content"], 120);
			
			die(json_encode(array(
				"success" => true,
				"course" => array(
					"id" => $coursePost["id"],
					"name" => $coursePost["post_title"],
					"desc" => $desc,
					"thumb" => $attachment["guid"],
					"body" => prepareBody($coursePost["post_content"]),
					"tags" => $tags,
					"category" => $category,
					"lessons" => $lessons,
					"sections" => $sections
				)
			), JSON_NUMERIC_CHECK));
		break;
		case "restart":
			$q = $MYSQL->query("DELETE FROM $WPDB.wpaa_learndash_user_activity WHERE `course_id` = '?' AND `user_id` = '?'", array(
				$courseId,
				$ACCOUNT["id"]
			));
			
			if(!$q) {
				die(json_encode(array(
					"error" => "Error restarting course: " . $MYSQL->error()
				)));
			}
			
			die(json_encode(array(
				"success" => true
			)));
		break;
		default:
			die(json_encode(array(
				"error" => "Invalid request"
			)));
		break;
	}
?>