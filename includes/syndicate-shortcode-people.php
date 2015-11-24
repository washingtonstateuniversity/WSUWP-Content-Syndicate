<?php

class WSU_Syndicate_Shortcode_People extends WSU_Syndicate_Shortcode_Base {
	/**
	 * @var array A list of defaults specific to people that will override the
	 *            base defaults set for all syndicate shortcodes.
	 */
	public $local_default_atts = array(
		'output' => 'basic',
		'host'   => 'people.wsu.edu',
		'query'  => 'people',
	);

	/**
	 * @var array A set of default attributes for this shortcode only.
	 */
	public $local_extended_atts = array(
		'classification' => '',
	);

	/**
	 * @var string Shortcode name.
	 */
	public $shortcode_name = 'wsuwp_people';

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
		$atts = $this->process_attributes( $atts );

		if ( ! $site_url = $this->get_request_url( $atts ) ) {
			return '<!-- wsuwp_people ERROR - an empty host was supplied -->';
		}

		if ( $content = $this->get_content_cache( $atts, 'wsuwp_people' ) ) {
			return $content;
		}

		$request_url = esc_url( $site_url['host'] . $site_url['path'] . $this->default_path ) . $atts['query'];
		$request_url = $this->build_taxonomy_filters( $atts, $request_url );

		if ( ! empty( $atts['classification'] ) ) {
			$request_url = add_query_arg( array(
				'filter[taxonomy]' => 'classification',
				'filter[term]' => sanitize_key( $atts['classification'] ),
			), $request_url );
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

		$content = '<div class="wsuwp-people-wrapper">';

		$people = json_decode( $data );

		$people = apply_filters( 'wsuwp_people_sort_items', $people );

		foreach ( $people as $person ) {
			$content .= $this->generate_item_html( $person, $atts['output'] );
		}

		$content .= '</div><!-- end wsuwp-people-wrapper -->';

		$this->set_content_cache( $atts, 'wsuwp_people', $content );

		return $content;
	}

	/**
	 * Generate the HTML used for individual people when called with the shortcode.
	 *
	 * @param stdClass $person Data returned from the WP REST API.
	 * @param string   $type   The type of output expected.
	 *
	 * @return string The generated HTML for an individual person.
	 */
	private function generate_item_html( $person, $type ) {
		if ( 'basic' === $type ) {
			ob_start();
			?>
			<div class="wsuwp-person-container">
				<?php if ( isset( $person->profile_photo ) && $person->profile_photo ) : ?>
				<figure class="wsuwp-person-photo">
					<img src="<?php echo esc_url( $person->profile_photo ); ?>" />
				</figure>
				<?php endif; ?>
				<div class="wsuwp-person-name"><?php echo esc_html( $person->title->rendered ); ?></div>
				<div class="wsuwp-person-position"><?php echo esc_html( $person->position_title ); ?></div>
				<div class="wsuwp-person-office"><?php echo esc_html( $person->office ); ?></div>
				<div class="wsuwp-person-email"><?php echo esc_html( $person->email ); ?></div>
			</div>
			<?php
			$html = ob_get_contents();
			ob_end_clean();

			return $html;
		}

		return apply_filters( 'wsuwp_people_item_html', '', $person, $type );
	}
}