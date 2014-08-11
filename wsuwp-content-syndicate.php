<?php
/*
Plugin Name: WSU Content Syndicate
Plugin URI: http://web.wsu.edu
Description: Retrieve content for display from throughout Washington State University
Author: washingtonstateuniversity, jeremyfelt
Version: 0.0.1
*/

class WSU_Content_Syndicate {
	/**
	 * Setup hooks for shortcodes.
	 */
	public function __construct() {
		add_shortcode( 'wsuwp_json', array( $this, 'display_wsuwp_json' ) );
		add_shortcode( 'wsuwp_elastic', array( $this, 'display_wsuwp_elastic' ) );
	}

	/**
	 * Process the requested parameters for use with the WordPress JSON API and output
	 * the response accordingly.
	 *
	 * @return string
	 */
	public function display_wsuwp_json( $atts ) {
		$defaults = array(
			'object' => 'json_data',
			'host' => 'news.wsu.edu',
			'query' => 'posts',
			'format' => 'json',
		);

		$atts = shortcode_atts( $defaults, $atts );

		$response = wp_remote_get( esc_url( $atts['host'] . '/wp-json/' . $atts['query'] ) );
		$data = wp_remote_retrieve_body( $response );

		ob_start();
		echo '<script>var ' . esc_js( $atts['object'] ) . ' = ' . $data . ';</script>';
		$content = ob_get_contents();
		ob_end_clean();

		return $content;
	}

	/**
	 * Process the requested parameters for use with WSU Search via Elasticsearch and output
	 * the response accordingly.
	 *
	 * @return string
	 */
	public function display_wsuwp_elastic() {
		ob_start();

		$content = ob_get_contents();
		ob_end_clean();

		return $content;
	}
}
new WSU_Content_Syndicate();