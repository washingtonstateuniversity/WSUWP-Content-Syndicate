<?php
/*
Plugin Name: WSUWP Content Syndicate
Plugin URI: https://web.wsu.edu/wordpress/plugins/wsuwp-content-syndicate/
Description: Retrieve content for display from throughout Washington State University
Author: washingtonstateuniversity, jeremyfelt
Author URI: https://web.wsu.edu/
Version: 1.0.0
*/

namespace WSU\ContentSyndicate;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

add_action( 'plugins_loaded', 'WSU\ContentSyndicate\bootstrap' );
/**
 * Loads the WSUWP Content Syndicate base.
 *
 * @since 1.0.0
 */
function bootstrap() {
	include_once __DIR__ . '/includes/class-wsu-syndicate-shortcode-base.php';

	add_action( 'init', 'WSU\ContentSyndicate\activate_shortcodes' );
}

/**
 * Activates the shortcodes built in with WSUWP Content Syndicate.
 *
 * @since 1.0.0
 */
function activate_shortcodes() {
	include_once( dirname( __FILE__ ) . '/includes/class-wsu-syndicate-shortcode-json.php' );

	// Add the [wsuwp_json] shortcode to pull standard post content.
	new \WSU_Syndicate_Shortcode_JSON();

	do_action( 'wsuwp_content_syndicate_shortcodes' );
}
