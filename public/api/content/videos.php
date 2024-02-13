<?php
	$VIDEOS_MAX = 10;
	
	error_reporting(0);
	
	require_once("../lib/mysql.php");
	require_once("../lib/utilities.php");
	require_once("../lib/video.php");
	
	authenticate();
	
	$filter_categories = array();
	$filter_tags = array();
	
	$filter_start = intval($_GET["start"]) ?? 0;
	$listMode = $_GET["mode"];
	
	$listModeQ = "";
	
	switch($listMode) {
		case "all":
			$listModeQ = "";
		break;
		case "favourites": 
			$favouritesStr = implode(",", $ACCOUNT["favourites"]);
			
			if(!$favouritesStr) {
				$favouritesStr = "0";
			}
			
			$listModeQ = "AND p.ID IN ($favouritesStr)";
		break;
		default:
			die(json_encode(array("error" => "Invalid list mode")));
	}
	
	if(isset($_GET["categories"]) && strlen($_GET["categories"]) > 0)
		$filter_categories = explode(",", $_GET["categories"]);
	
	if(isset($_GET["tags"]) && strlen($_GET["tags"]) > 0)
		$filter_tags = explode(",", $_GET["tags"]);
	
	// Search filter settings
	//$searchFilterSettings = getSearchFilterSettings();
	
	// Get category ids from associative array
	$categoryIdsStr = "56,85,89,78,86,2848,242,241,243"; //$searchFilterSettings["taxonomies_settings"]["category"]["ids"];
	$tagIdsStr = "4336,58,4302,4303,60,68,65,81,83,70,12,64,19,4327,74,66,20,42,76,4280,75,41,4,4272,4326,4325,4324"; //$searchFilterSettings["taxonomies_settings"]["post_tag"]["ids"];
	
	$categoryIds = explode(",", $categoryIdsStr);
	$tagIds = explode(",", $tagIdsStr);
	
	// Security check (to avoid edge case injection)
	if(!is_numeric(str_replace(",", "", $categoryIdsStr)) || !is_numeric(str_replace(",", "", $tagIdsStr))) {
		die(json_encode(array(
			"error" => "Insecure request"
		)));
	}
	
	// Get master term filter array
	$termFilter = array_merge($filter_categories, $filter_tags);
	
	// Get the video category ids
	$videoCategories = $MYSQL->getrowsq("SELECT `term_id` AS id, `name` FROM $WPDB.wpaa_terms WHERE `term_id` IN ($categoryIdsStr)");
	$videoTags = $MYSQL->getrowsq("SELECT `term_id` AS id, `name` FROM $WPDB.wpaa_terms WHERE `term_id` IN ($tagIdsStr)");
	
	// Sort them according to their specific order
	usort($videoTags, function($a, $b) {
		global $tagIds;
		
		$aKey = -1;
		$bKey = -1;
		
		foreach($tagIds as $k => $tagId) {
			if(intval($a["id"]) == intval($tagId)) {
				$aKey = $k;
			}
			if(intval($b["id"]) == intval($tagId)) {
				$bKey = $k;
			}
		}
		
		return $aKey <=> $bKey;
	});
	usort($videoCategories, function($a, $b) {
		global $categoryIds;
		
		$aKey = -1;
		$bKey = -1;
		
		foreach($categoryIds as $k => $catId) {
			if(intval($a["id"]) == intval($catId)) {
				$aKey = $k;
			}
			if(intval($b["id"]) == intval($catId)) {
				$bKey = $k;
			}
		}
		
		return $aKey <=> $bKey;
	});
	
	$qs = "";
	$qc = "";
	
	if(count($termFilter) > 0) {
		$qbody = "FROM $WPDB.wpaa_posts p
		JOIN $WPDB.wpaa_term_relationships tr ON p.ID = tr.object_id
		JOIN $WPDB.wpaa_terms t ON tr.term_taxonomy_id = t.term_id
		WHERE t.name IN (" . $MYSQL->createEscapedElementList($termFilter) . ") 
		$listModeQ 
		AND p.post_status = 'publish' 
		GROUP BY p.ID, p.post_title
		HAVING COUNT(DISTINCT t.name) = " . count($termFilter);
		$qbody .= " ORDER BY `ID` DESC";
		
		$qs = "SELECT DISTINCT p.ID as `id`, p.post_title, p.post_content $qbody";
		$qc = "SELECT DISTINCT p.ID as `id` $qbody";
	}else{
		$qbody = "FROM $WPDB.wpaa_posts p
		JOIN $WPDB.wpaa_term_relationships tr ON p.ID = tr.object_id
		JOIN $WPDB.wpaa_terms t ON tr.term_taxonomy_id = t.term_id
		WHERE t.term_id IN ($categoryIdsStr) $listModeQ 
		AND p.post_status = 'publish' 
		ORDER BY `ID` DESC";
		
		$qs = "SELECT DISTINCT p.ID as `id`, p.post_title, p.post_content $qbody";
		$qc = "SELECT DISTINCT p.ID as `id` $qbody";
	}
	
	// Get the video posts
	$videoPosts = $MYSQL->getrowsq($qs . " LIMIT $filter_start,$VIDEOS_MAX");
	
	$databaseError = $MYSQL->error(); 
	if($databaseError) {
		die(json_encode(array("error" => $databaseError)));
	}
	
	// Get the total count
	$videoTotalResults = $MYSQL->num_rows($MYSQL->query($qc));
	
	// Main videos array
	$videos = getVideos($videoPosts);
	
	die(json_encode(array(
		"videos" => $videos,
		"categories" => $videoCategories,
		"tags" => $videoTags,
		"success" => true,
		"total" => $videoTotalResults,
		"max" => $VIDEOS_MAX
	), JSON_NUMERIC_CHECK));
?>