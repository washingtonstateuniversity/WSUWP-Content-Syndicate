<?php

/**
 * A base class for WSU syndicate shortcodes.
 *
 * Class WSU_Syndicate_Shortcode_Base
 */
class WSU_Syndicate_Shortcode_Base {
	/**
	 * Default path used to consume the REST API from an individual site.
	 *
	 * @var string
	 */
	public $default_path = 'wp-json/wp/v2/';

	/**
	 * Default attributes applied to all shortcodes that extend this base.
	 *
	 * @var array
	 */
	public $defaults_atts = array(
		'object' => 'json_data',
		'output' => 'json',
		'host' => 'news.wsu.edu',
		'scheme' => 'http',
		'site' => '',
		'query' => 'posts',
		'local_count' => 0,
		'count' => false,
		'date_format' => 'F j, Y',
		'offset' => 0,
		'cache_bust' => '',
	);

	/**
	 * Defaults for individual base attributes can be overridden for a
	 * specific shortcode.
	 *
	 * @var array
	 */
	public $local_default_atts = array();

	/**
	 * Defaults can be extended with additional keys by a specific shortcode.
	 *
	 * @var array
	 */
	public $local_extended_atts = array();

	/**
	 * @var string The shortcode name.
	 */
	public $shortcode_name = '';

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
	 * Process passed attributes for a shortcode against arrays of base defaults,
	 * local defaults, and extended local defaults.
	 *
	 * @param array $atts Attributes passed to a shortcode.
	 *
	 * @return array Fully populated list of attributes expected by the shortcode.
	 */
	public function process_attributes( $atts ) {
		$this->defaults_atts = apply_filters( 'wsuwp_content_syndicate_default_atts', $this->defaults_atts );

		$defaults = shortcode_atts( $this->defaults_atts, $this->local_default_atts );
		$defaults = $defaults + $this->local_extended_atts;

		return shortcode_atts( $defaults, $atts, $this->shortcode_name );
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
		$atts_key = md5( serialize( $atts ) ); // @codingStandardsIgnoreLine

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
		$atts_key = md5( serialize( $atts ) ); // @codingStandardsIgnoreLine

		wp_cache_set( $atts_key, $content, $shortcode, 600 );
	}

	/**
	 * Processes a given site URL and shortcode attributes into data to be used for the
	 * request.
	 *
	 * @since 0.10.0
	 *
	 * @param array $site_url Contains host and path of the requested URL.
	 * @param array $atts     Contains the original shortcode attributes.
	 *
	 * @return array List of request information.
	 */
	public function build_initial_request( $site_url, $atts ) {
		$url_scheme = 'http';
		$local_site_id = false;

		// Account for a previous version that allowed "local" as a manual scheme.
		if ( 'local' === $atts['scheme'] ) {
			$atts['scheme'] = 'http';
		}

		$home_url_data = wp_parse_url( trailingslashit( get_home_url() ) );

		if ( $home_url_data['host'] === $site_url['host'] && $home_url_data['path'] === $site_url['path'] ) {
			$local_site_id = 1;
			$url_scheme = $home_url_data['scheme'];

			// Local is assigned as a scheme only if the requesting site is the requested site.
			$atts['scheme'] = 'local';
		} elseif ( is_multisite() ) {
			$local_site = get_blog_details( array(
				'domain' => $site_url['host'],
				'path' => $site_url['path'],
			), false );

			if ( $local_site ) {
				$local_site_id = $local_site->blog_id;
				$local_home_url = get_home_url( $local_site_id );
				$url_scheme = wp_parse_url( $local_home_url, PHP_URL_SCHEME );
				$atts['scheme'] = $url_scheme;
			}
		}

		$request_url = esc_url( $url_scheme . '://' . $site_url['host'] . $site_url['path'] . $this->default_path ) . $atts['query'];

		$request = array(
			'url' => $request_url,
			'scheme' => $atts['scheme'],
			'site_id' => $local_site_id,
		);

		return $request;
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

		$site_url = wp_parse_url( $site_url );

		if ( empty( $site_url['host'] ) ) {
			return false;
		}

		return $site_url;
	}

	/**
	 * Add proper filters to a given URL to handle lookup by customn taxonomies and
	 * built in WordPress taxonomies.
	 *
	 * @param array  $atts        List of attributes used for the shortcode.
	 * @param string $request_url REST API URL being built.
	 *
	 * @return string Modified REST API URL.
	 */
	public function build_taxonomy_filters( $atts, $request_url ) {
		$request_url = apply_filters( 'wsuwp_content_syndicate_taxonomy_filters', $request_url, $atts, $request_url );

		return $request_url;
	}

	/**
	 * Explode comma-separated terms into an array, sanitize each term, implode into a string.
	 *
	 * @since 0.10.0
	 *
	 * @param string $terms Comma separated list of terms.
	 *
	 * @return string Sanitized comma separated list of terms.
	 */
	public function sanitized_terms( $terms ) {
		$term_array = explode( ',', $terms );
		$sanitize_term_array = array_map( 'sanitize_key', $term_array );
		$imploded_terms = implode( ',', $sanitize_term_array );
		return $imploded_terms;
	}
}
