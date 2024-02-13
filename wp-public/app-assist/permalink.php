<?php
    define('WP_USE_THEMES', false); // Prevent WordPress from loading themes
    require('../wp-load.php'); // Adjust the path to your WordPress installation

    $id = $_GET["id"];

    if(!is_numeric($id))
        die();

    echo get_permalink($id);
?>