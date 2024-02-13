<?php
	error_reporting(0);
	
	require_once("../lib/mysql.php");
	require_once("../lib/utilities.php");
	
	$ids = array();
	$filter_tags = array();
	$filter_category = null;
	
	$pref_tags = array();
	
	authenticate();
	
	if(isset($_GET["ids"]))
		$ids = explode(",", $_GET["ids"]);
	
	if(isset($_GET["tags"]))
		$filter_tags = explode(",", $_GET["tags"]);
	
	if(isset($_GET["ptags"]))
		$pref_tags = explode(",", $_GET["ptags"]);
	
	if(isset($_GET["category"]))
		$filter_category = $_GET["category"];
	
	$courses = array();
	$courseCategories = array();
	
	// Add recommended category
	$recommendedCategory = array("id" => -1, "name" => "Recommended for you");
	$courseCategories[] = $recommendedCategory;
	
	// Get the course category ids
	$courseCategories = array_merge($courseCategories, $MYSQL->getrowsq("SELECT `term_id` AS id, `name` FROM $WPDB.wpaa_terms WHERE `term_id` IN (SELECT `term_taxonomy_id` FROM $WPDB.wpaa_term_taxonomy WHERE `taxonomy` = 'ld_course_category') ORDER BY `id` ASC"));
	
	// If no ids provided, get all courses
	if(empty($ids)) {
		// Posts that are courses
		$coursePosts = $MYSQL->getrowsq("SELECT * ,`ID` as id FROM $WPDB.wpaa_posts WHERE `ID` IN (SELECT `object_id` FROM $WPDB.wpaa_term_relationships WHERE `term_taxonomy_id` IN (SELECT `term_taxonomy_id` FROM $WPDB.wpaa_term_taxonomy WHERE `taxonomy` = 'ld_course_category')) ORDER BY id DESC");
		
		foreach($coursePosts as $coursePost) {
			$ids[] = $coursePost["id"];
		}
	}
	
	foreach($ids as $courseId) {
		// Get course post
		$coursePost = $MYSQL->getrowq("SELECT *,`ID` as `id` FROM $WPDB.wpaa_posts WHERE `ID` = $courseId");
		
		if(!$coursePost)
			continue;
		
		// Get the meta rows
		$postMeta = getMetaObject($MYSQL->getrowsq("SELECT * FROM $WPDB.wpaa_postmeta WHERE `post_id` = $courseId"));
		
		// Get the associated category
		$category = $MYSQL->getrowq("SELECT `term_id` AS id, `name` FROM $WPDB.wpaa_terms WHERE `term_id` IN (SELECT `term_taxonomy_id` FROM $WPDB.wpaa_term_relationships WHERE `object_id` = $courseId) AND `term_id` IN (SELECT `term_taxonomy_id` FROM $WPDB.wpaa_term_taxonomy WHERE `taxonomy` = 'ld_course_category') LIMIT 1");
		
		// Skip if not in filter
		if($category["name"] != $filter_category && $filter_category)
			continue;
		
		// Get the associated tags
		$tags = $MYSQL->getrowsq("SELECT `term_id` AS id, `name` FROM $WPDB.wpaa_terms WHERE `term_id` IN (SELECT `term_taxonomy_id` FROM $WPDB.wpaa_term_relationships WHERE `object_id` = $courseId) AND `term_id` IN (SELECT `term_taxonomy_id` FROM $WPDB.wpaa_term_taxonomy WHERE `taxonomy` = 'post_tag')");
		$tagNames = getTagNames($tags);
		
		// Skip if not in filter
		if(count(array_intersect($filter_tags, $tagNames)) < 1 && count($filter_tags) > 0)
			continue;
		
		// Get the thumbnail attachment post
		$attachment = $MYSQL->getrowq("SELECT `guid` FROM $WPDB.wpaa_posts WHERE `ID` = '?'", array($postMeta["_thumbnail_id"]));
		
		// Get the appropriate description
		$desc = extractExcerpt($coursePost["post_content"], 120);
		
		// Process thumb
		$thumb = getResourceImage(fixAttachment($attachment["guid"]), $thumbWidth, $thumbHeight);
		
		$course = array(
			"id" => $coursePost["id"],
			"name" => html_entity_decode($coursePost["post_title"]),
			"desc" => $desc,
			"thumb" => $thumb,
			"category" => $category,
			"tags" => $tags
		);
		
		$courses[] = $course;
		
		$matchesName = false;
		
		/*foreach($pref_tags as $pref_tag) {
			if(stristr($course["name"], $pref_tag)) {
				$matchesName = true;
				break;
			}
		}*/
		
		$secondaryCourses = [];
		
		// If in preference filter, duplicate to the dummy recommended category
		if((count(array_intersect($pref_tags, $tagNames)) > 0 || $matchesName) && count($pref_tags) > 0) {
			$course2 = json_decode(json_encode($course), true);
			
			$course2["category"] = $recommendedCategory;
			
			$alreadyIn = false;
			
			foreach($secondaryCourses as $c) {
				if($c["id"] == $course2["id"]) {
					$alreadyIn = true;
					break;
				}
			}
			
			if(!$alreadyIn)
				$secondaryCourses[] = $course2;
		}
		
		$courses = array_merge($secondaryCourses, $courses);
	}
	
	die(json_encode(array(
		"courses" => $courses,
		"categories" => $courseCategories,
		"success" => true
	), JSON_NUMERIC_CHECK));
?>