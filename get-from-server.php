<?php
namespace brmgina\WordPress\GetFromServer;
/*
 * Plugin Name: Get From Server
 * Version: 1.0.0
 * Plugin URI: https://brmgina.net/
 * Description: Plugin to allow the Media Manager to get files from the webservers filesystem.
 * Author: Eng. A7meD KaMeL
 * Author URI: https://a-kamel.com/
 * Text Domain: get-from-server
 */

if ( !is_admin() ) {
	return;
}

const MIN_WP  = '6.0';
const MIN_PHP = '8.0';
const VERSION = '1.0.0';

// Dynamic constants must be define()'d.
define( __NAMESPACE__ . '\PLUGIN', plugin_basename( __FILE__ ) );

// Load the main plugin class
include __DIR__ . '/class.get-from-server.php';

// Load PHP8 compat functions.
include __DIR__ . '/compat.php';

Plugin::instance();
