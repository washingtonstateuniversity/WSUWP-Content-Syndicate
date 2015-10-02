<?php
/*
Plugin Name: WSU Content Syndicate
Plugin URI: https://web.wsu.edu/
Description: Retrieve content for display from throughout Washington State University
Author: washingtonstateuniversity, jeremyfelt
Version: 0.7.3
*/

class WSU_Content_Syndicate {
	/**
	 * @var WSU_Content_Syndicate
	 */
	private static $instance;

	/**
	 * Maintain and return the one instance and initiate hooks when
	 * called the first time.
	 *
	 * @return \WSU_Content_Syndicate
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new WSU_Content_Syndicate;
			self::$instance->setup_hooks();
		}
		return self::$instance;
	}

	/**
	 * Setup hooks to include and then activate the plugin's shortcodes.
	 */
	public function setup_hooks() {
		add_action( 'init', array( $this, 'activate_shortcodes' ) );
	}

	/**
	 * Include individual and activate individual syndicate shortcodes.
	 */
	public function activate_shortcodes() {
		require_once( dirname( __FILE__ ) . '/includes/syndicate-shortcode-base.php' );
		require_once( dirname( __FILE__ ) . '/includes/syndicate-shortcode-json.php' );
		require_once( dirname( __FILE__ ) . '/includes/syndicate-shortcode-people.php' );
		require_once( dirname( __FILE__ ) . '/includes/syndicate-shortcode-events.php' );

		// Add the [wsuwp_json] shortcode to pull standard post content.
		new WSU_Syndicate_Shortcode_JSON();

		// Add the [wsuwp_people] shortcode to pull profiles from people.wsu.edu.
		new WSU_Syndicate_Shortcode_People();

		// Add the [wsuwp_events] shortcode to pull calendar events.
		new WSU_Syndicate_Shortcode_Events();
	}
}

add_action( 'after_setup_theme', 'WSU_Content_Syndicate' );
/**
 * Start things up.
 *
 * @return \WSU_Content_Syndicate
 */
function WSU_Content_Syndicate() {
	return WSU_Content_Syndicate::get_instance();
}