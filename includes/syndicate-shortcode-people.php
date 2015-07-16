<?php

class WSU_Syndicate_Shortcode_People extends WSU_Syndicate_Shortcode_Base {
	/**
	 * @var array A list of defaults specific to people that will override the
	 *            base defaults set for all syndicate shortcodes.
	 */
	public $local_default_atts = array(
		'output' => 'basic',
		'host'   => 'people.wsu.edu',
		'query'  => 'posts/?type=wsuwp_people_profile',
	);

	public function __construct() {
		parent::construct();
	}

	public function add_shortcode() {
		add_shortcode( 'wsuwp_people', array( $this, 'display_shortcode' ) );
	}

	/**
	 * Display people from people.wsu.edu in a structured format using the
	 * WP REST API.
	 *
	 * @param array $atts Attributes passed to the shortcode.
	 *
	 * @return string Content to display in place of the shortcode.
	 */
	public function display_shortcode( $atts ) {
		$default_atts = shortcode_atts( $this->defaults_atts, $this->local_default_atts );
		$atts         = shortcode_atts( $default_atts, $atts );

		if ( ! $site_url = $this->get_request_url( $atts ) ) {
			return '<!-- wsuwp_people ERROR - an empty host was supplied -->';
		}

		if ( $content = $this->get_content_cache( $atts, 'wsuwp_people' ) ) {
			return $content;
		}

		$request_url = esc_url( $site_url['host'] . $site_url['path'] . 'wp-json/' ) . $atts['query'];
		$request_url = $this->build_taxonomy_filters( $atts, $request_url );

		if ( ! empty( $atts['offset'] ) ) {
			$atts['count'] = absint( $atts['count'] ) + absint( $atts['offset'] );
		}

		if ( $atts['count'] ) {
			$request_url = add_query_arg( array( 'filter[posts_per_page]' => absint( $atts['count'] ) ), $request_url );
		}

		$response = wp_remote_get( $request_url );

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$data = wp_remote_retrieve_body( $response );

		if ( empty( $data ) ) {
			return '';
		}

		$people = json_decode( $data );

		foreach ( $people as $person ) {
			// Capture individual person information.
		}

		return '';
	}
}