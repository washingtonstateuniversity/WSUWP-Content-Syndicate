<?php
/*
Plugin Name: WSUWP Content Syndicate
Plugin URI: https://web.wsu.edu/wordpress/plugins/wsuwp-content-syndicate/
Description: Retrieve content for display from throughout Washington State University
Author: washingtonstateuniversity, jeremyfelt
Author URI: https://web.wsu.edu/
Version: 0.10.1
*/

namespace WSU\ContentSyndicate;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

add_action( 'init', 'WSU\ContentSyndicate\activate_shortcodes' );
function activate_shortcodes() {
	require_once( dirname( __FILE__ ) . '/includes/class-wsu-syndicate-shortcode-base.php' );
	require_once( dirname( __FILE__ ) . '/includes/class-wsu-syndicate-shortcode-json.php' );
	require_once( dirname( __FILE__ ) . '/includes/class-wsu-syndicate-shortcode-people.php' );
	require_once( dirname( __FILE__ ) . '/includes/class-wsu-syndicate-shortcode-events.php' );

	// Add the [wsuwp_json] shortcode to pull standard post content.
	new \WSU_Syndicate_Shortcode_JSON();

	// Add the [wsuwp_people] shortcode to pull profiles from people.wsu.edu.
	new \WSU_Syndicate_Shortcode_People();

	// Add the [wsuwp_events] shortcode to pull calendar events.
	new \WSU_Syndicate_Shortcode_Events();
}
