<?php
	$GLOBALS["WPDB"] = "yogaform_wp670";
	
	if(!class_exists('MySQLIExtension')) {
		class MySQLIExtension {
			private $mysqli;
			public $debugQueries = array();
			function __construct($db = "dbn1gl2htibvj5") {
				$h = "yoga15.com";
				$u = "yogaform_wp670";
				$p = "p2.87SRm0]";
				$d = $db;
				$this->mysqli = new mysqli($h, $u, $p, $d);
				if(mysqli_connect_errno()) {
					die(json_encode(array(
						"error" => "Please report this error. Error in database: " . mysqli_connect_errno()
					)));
				}else{
					$this->mysqli->set_charset("utf8mb4");
				}
			}
			function query($q, $params = array(), $debug = false) {
				$parts = explode("?", $q);
				$rebuild = "";
				foreach($parts as $k => $part) {
					$rebuild .= $part;
					if(isset($params[$k])) {
						$rebuild .= $this->escape($params[$k]);
					}
				}
				if($debug) {
					var_dump($rebuild);
					return;
				}
				return $this->mysqli->query($rebuild);
			}
			function miltime() {
				return (microtime(true) * 1000);
			}
			function assoc($q) {
				if(!$q){
					return false;
				}
				return $q->fetch_assoc();
			}
			function num_rows($q) {
				if(!$q){
					return false;
				}
				return $q->num_rows;
			}
			function escape($str) {
				return $this->mysqli->real_escape_string($str);
			}
			function error() {
				return $this->mysqli->error;
			}
			function lastInsertId() {
				return $this->mysqli->insert_id;
			}
			function getrows($q) {
				if(!$q){
					return false;
				}
				$rows = array();
				while($as = $this->assoc($q)) {
					$rows[] = $as;
				}
				return $rows;
			}
			function getrowsq($query, $params = array()) {
				global $MYSQL;
				
				$q = $MYSQL->query($query, $params);
				
				if(!$q) 
					return null;
					
				$rows = array();
				while($as = $this->assoc($q)) {
					$rows[] = $as;
				}
				return $rows;
			}
			function getrowq($query, $params = array()) {
				global $MYSQL;
				
				$q = $MYSQL->query($query, $params);
				
				if(!$q) 
					return null;
				
				return $this->assoc($q);
			}
			function getlastrowid() {
				return mysqli_insert_id($this->mysqli);
			}
			function createEscapedElementList($array) {
				$escapedList = array();
				
				foreach ($array as $element) {
					$escapedElement = $this->mysqli->real_escape_string($element);
					$escapedList[] = "'$escapedElement'";
				}
				
				$elementList = implode(', ', $escapedList);
				return $elementList;
			}
		}
	}
	$MYSQL = new MySQLIExtension();
?>