<?php
/*
Plugin Name: WSU Content Syndicate
Plugin URI: http://web.wsu.edu
Description: Retrieve content for display from throughout Washington State University
Author: washingtonstateuniversity, jeremyfelt
Version: 0.1.0
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
	 * @param array $atts Attributes passed with the shortcode
	 *
	 * @return string Data to output where the shortcode is used.
	 */
	public function display_wsuwp_json( $atts ) {
		$defaults = array(
			'object' => 'json_data',
			'host' => 'news.wsu.edu',
			'university_category_slug' => '',
			'query' => 'posts',
			'count' => false,
		);

		$atts = shortcode_atts( $defaults, $atts );

		// We only support queries that start with "posts"
		if ( 'posts' !== substr( $atts['query'], 0, 5 ) ) {
			return '<!-- wsuwp_json ERROR - query not supported -->';
		}

		// We only support queries for wsu.edu domains by default
		$host = parse_url( esc_url( $atts['host'] ) );
		if ( empty( $host['host'] ) ) {
			return '<!-- wsuwp_json ERROR - an empty host was supplied -->';
		}

		$host_parts = explode( '.', $host['host'] );
		$host_edu = array_pop( $host_parts );
		$host_wsu = array_pop( $host_parts );

		if ( ( 'edu' !== $host_edu || 'wsu' !== $host_wsu ) && false === apply_filters( 'wsu_consyn_valid_domain', false, $host['host'] ) ) {
			return '<!-- wsuwp_json ERROR - not a valid domain -->';
		}

		// If a University Category slug is provided, ignore the query.
		if ( '' !== $atts['university_category_slug'] ) {
			$atts['query'] = 'posts/?filter[taxonomy]=wsuwp_university_category&filter[term]=' . sanitize_key( $atts['university_category_slug'] );
		}

		$request_url = esc_url( $host['host'] . '/wp-json/' ) . $atts['query'];

		if ( $atts['count'] ) {
			$request_url = add_query_arg( array( 'filter[posts_per_page]' => absint( $atts['count'] ) ), $request_url );
		}

		$response = wp_remote_get( $request_url );

		$data = wp_remote_retrieve_body( $response );

		$new_data = array();
		if ( ! empty( $data ) ) {
			$data = json_decode( $data );
			foreach( $data as $post ) {
				$subset = new StdClass();
				$subset->ID = $post->ID;
				$subset->title = $post->title;
				$subset->link = $post->link;
				$subset->excerpt = $post->excerpt;
				$subset->content = $post->content;
				$subset->terms = $post->terms;
				$subset->author_name = $post->author->name;
				$subset->author_avatar = $post->author->avatar;
				$new_data[] = $subset;
			}
		}

		$data = json_encode( $new_data );
		ob_start();
		echo '<script>var ' . esc_js( $atts['object'] ) . ' = ' . $data . ';</script>';
		$content = ob_get_contents();
		ob_end_clean();

		$content = apply_filters( 'wsuwp_content_syndicate_json', $content, $atts );

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