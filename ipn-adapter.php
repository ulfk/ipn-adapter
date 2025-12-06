<?php
/**
 * @package IPN-Adapter
 */
/*
Plugin Name: IPN-Adapter
Plugin URI: https://github.com/ulfk/ipn-adapter
Description: IPN Adapter - Connect Digistore to Brevo to add buyers e-mail addresses to your mailing-lists.
Version: 1.2.0
Author: Ulf Kuehnle
Author URI: https://ulf-kuehnle.de/
License: GPLv2
*/

include_once "settings.php";
$settings_manager = null;
include_once "logging.php";
$log_viewer = null;


add_action('init', function() {
    global $log_viewer;
    $log_viewer = new Log_Viewer();
	$log_viewer->init_wp_hooks();
});

add_action('plugins_loaded', function() {
    global $settings_manager;
    $settings_manager = new Settings_Manager();
    $settings_manager->init_wp_hooks();
});


add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'add_plugin_settings_link');

function add_plugin_settings_link($links) {
    $settings_link = '<a href="options-general.php?page=ipn-adapter">'. __('Settings') .'</a>';
    array_unshift($links, $settings_link);
    return $links;
}

// De-/Activation hooks
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
    $log_viewer->write_log("Plugin removed");
}

?>
