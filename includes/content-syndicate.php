<?php

namespace WSU\Content_Syndicate;

add_action( 'plugins_loaded', 'WSU\Content_Syndicate\bootstrap' );

/**
 * Loads the WSUWP Content Syndicate base.
 *
 * @since 1.0.0
 */
function bootstrap() {
	include_once __DIR__ . '/class-wsu-syndicate-shortcode-base.php';

	add_action( 'init', 'WSU\Content_Syndicate\activate_shortcodes' );
	add_action( 'save_post_post', 'WSU\Content_Syndicate\clear_local_content_cache' );
	add_action( 'save_post_page', 'WSU\Content_Syndicate\clear_local_content_cache' );
}

/**
 * Activates the shortcodes built in with WSUWP Content Syndicate.
 *
 * @since 1.0.0
 */
function activate_shortcodes() {
	include_once( dirname( __FILE__ ) . '/class-wsu-syndicate-shortcode-json.php' );

	// Add the [wsuwp_json] shortcode to pull standard post content.
	new \WSU_Syndicate_Shortcode_JSON();

	do_action( 'wsuwp_content_syndicate_shortcodes' );
}

/**
 * Clear the last changed cache for local results whenever
 * a post is saved.
 *
 * @since 1.4.0
 */
function clear_local_content_cache() {
	wp_cache_set( 'last_changed', microtime(), 'wsuwp-content' );
}
