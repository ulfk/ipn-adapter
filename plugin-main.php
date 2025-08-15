<?php
/**
 * @package IPN-Adapter
 */
/*
Plugin Name: IPN-Adapter
Plugin URI: https://github.com/ulfk/ipn-adapter
Description: IPN Adapter by Ulf Kuehnle.
Version: 0.3.0
Author: Ulf Kuehnle
Author URI: https://ulf-kuehnle.de/
License: GPLv2
*/

include_once "logging.php";
$log_viewer = null;

add_action('init', function() {
    global $log_viewer;
    $log_viewer = new Log_Viewer();
	$log_viewer->init_wp_hooks();
});

?>