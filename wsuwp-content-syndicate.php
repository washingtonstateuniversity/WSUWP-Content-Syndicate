<?php
/*
Plugin Name: WSUWP Content Syndicate
Plugin URI: https://github.com/washingtonstateuniversity/WSUWP-Content-Syndicate
Description: Retrieve content for display from other WordPress sites.
Author: washingtonstateuniversity, jeremyfelt, philcable
Author URI: https://web.wsu.edu/
Version: 1.4.2
*/

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// This plugin uses namespaces and requires PHP 5.3 or greater.
if ( version_compare( PHP_VERSION, '5.3', '<' ) ) {
	add_action( 'admin_notices', create_function( '',
	"echo '<div class=\"error\"><p>" . __( 'WSUWP Content Syndicate requires PHP 5.3 to function properly. Please upgrade PHP or deactivate the plugin.', 'wsuwp-content-syndicate' ) . "</p></div>';" ) );
	return;
} else {
	include_once __DIR__ . '/includes/content-syndicate.php';
}
