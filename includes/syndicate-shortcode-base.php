<?php

/**
 * A base class for WSU syndicate shortcodes.
 *
 * Class WSU_Syndicate_Shortcode_Base
 */
class WSU_Syndicate_Shortcode_Base {
	/**
	 * A common constructor that initiates the shortcode.
	 */
	public function construct() {
		$this->add_shortcode();
	}

	/**
	 * Required to add a shortcode definition.
	 */
	public function add_shortcode() {}

	/**
	 * Required to display the content of a shortcode.
	 *
	 * @param array $atts A list of attributes assigned to the shortcode.
	 *
	 * @return string Final output for the shortcode.
	 */
	public function display_shortcode( $atts ) {
		return '';
	}

	/**
	 * Create a hash of all attributes to use as a cache key. If any attribute changes,
	 * then the cache will regenerate on the next load.
	 *
	 * @param array  $atts      List of attributes used for the shortcode.
	 * @param string $shortcode Shortcode being displayed.
	 *
	 * @return bool|string False if cache is not available or expired. Content if available.
	 */
	public function get_content_cache( $atts, $shortcode ) {
		$atts_key = md5( serialize( $atts ) );

		$content = wp_cache_get( $atts_key, $shortcode );

		return $content;
	}

	/**
	 * Store generated content from the shortcode in cache.
	 *
	 * @param array  $atts      List of attributes used for the shortcode.
	 * @param string $shortcode Shortcode being displayed.
	 * @param string $content   Generated content after processing the shortcode.
	 */
	public function set_content_cache( $atts, $shortcode, $content ) {
		$atts_key = md5( serialize( $atts ) );

		wp_cache_set( $atts_key, $content, $shortcode, 600 );
	}

	/**
	 * Determine what the base URL should be used for REST API data.
	 *
	 * @param array $atts List of attributes used for the shortcode.
	 *
	 * @return bool|array host and path if available, false if not.
	 */
	public function get_request_url( $atts ) {
		// If a site attribute is provided, it overrides the host attribute.
		if ( ! empty( $atts['site'] ) ) {
			$site_url = trailingslashit( esc_url( $atts['site'] ) );
		} else {
			$site_url = trailingslashit( esc_url( $atts['host'] ) );
		}

		$site_url = parse_url( $site_url );

		if ( empty( $site_url['host'] ) ) {
			return false;
		}

		return $site_url;
	}
}