<?php
/**
 * @package IPN-Adapter
 */
/*
Plugin Name: IPN-Adapter
Plugin URI: https://github.com/ulfk/ipn-adapter
Description: IPN Adapter by Ulf Kuehnle.
Version: 0.4.0
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

register_activation_hook(__FILE__, 'ipn_plugin_activation_hook');
register_deactivation_hook(__FILE__, 'ipn_plugin_deactivation_hook');
register_uninstall_hook(__FILE__, 'ipn_plugin_uninstall_hook');

function ipn_plugin_activation_hook() {
    $log_viewer = new Log_Viewer();
    $log_viewer->write_log("Plugin activated");
}

function ipn_plugin_deactivation_hook() {
    $log_viewer = new Log_Viewer();
    $log_viewer->write_log("Plugin deactivated");
}

function ipn_plugin_uninstall_hook() {
    $log_viewer = new Log_Viewer();
    $log_viewer->write_log("Plugin deinstalliert");
}

?>