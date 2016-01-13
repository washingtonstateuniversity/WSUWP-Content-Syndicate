<?php

class WSU_Syndicate_Shortcode_Events extends WSU_Syndicate_Shortcode_Base {
	/**
	 * @var array Overriding attributes applied to the base defaults.
	 */
	public $local_default_atts = array(
		'output'      => 'headlines',
		'host'        => 'calendar.wsu.edu',
		'query'       => 'events',
		'date_format' => 'M j',
	);

	/**
	 * @var array A set of default attributes for this shortcode only.
	 */
	public $local_extended_atts = array(
		'category' => '',
	);

	/**
	 * @var string Shortcode name.
	 */
	public $shortcode_name = 'wsuwp_events';

	public function __construct() {
		parent::construct();
	}

	public function add_shortcode() {
		add_shortcode( 'wsuwp_events', array( $this, 'display_shortcode' ) );
	}

	/**
	 * Display events information for the [wsuwp_events] shortcode.
	 *
	 * @param array $atts
	 *
	 * @return string
	 */
	public function display_shortcode( $atts ) {
		$atts = $this->process_attributes( $atts );

		if ( ! $site_url = $this->get_request_url( $atts ) ) {
			return '<!-- wsuwp_events ERROR - an empty host was supplied -->';
		}

		// Retrieve existing content from cache if available.
		if ( $content = $this->get_content_cache( $atts, 'wsuwp_events' ) ) {
			return apply_filters( 'wsuwp_content_syndicate_json', $content, $atts );
		}

		$request_url = esc_url( $site_url['host'] . $site_url['path'] . $this->default_path ) . $atts['query'];

		if ( '' !== $atts['category'] ) {
			$request_url = add_query_arg( array( 'filter[taxonomy]' => 'tribe_events_cat' ), $request_url );

			$terms = explode( ',', $atts['category'] );
			foreach( $terms as $term ) {
				$term = trim( $term );
				$request_url = add_query_arg( array( 'filter[term]' => sanitize_key( $term ) ), $request_url );
			}
		}

		if ( ! empty( $atts['tag'] ) ) {
			$request_url = add_query_arg( array( 'filter[tag]' => sanitize_key( $atts['tag'] ) ), $request_url );
		}

		if ( $atts['count'] ) {
			$request_url = add_query_arg( array( 'filter[posts_per_page]' => absint( $atts['count'] ) ), $request_url );
		}

		$response = wp_remote_get( $request_url );

		$data = wp_remote_retrieve_body( $response );

		$new_data = array();
		if ( ! empty( $data ) ) {
			$original_data = $data;
			$data = json_decode( $data );

			if ( NULL === $data ) {
				$original_type = gettype( $original_data );
				error_log( 'WSUWP Content Syndicate: Null JSON. Original type: ' . $original_type );
				error_log( 'WSUWP Content Syndicate: Original URL: ' . esc_url( $request_url ) );
				error_log( 'WSUWP Content Syndicate: Original Response Code: ' . wp_remote_retrieve_response_code( $response ) );
				$data = array();
			}

			foreach( $data as $post ) {
				$subset = new StdClass();
				$subset->ID = $post->id;
				$subset->title = $post->title->rendered;
				$subset->link = $post->link;
				$subset->excerpt = $post->excerpt->rendered;
				$subset->content = $post->content->rendered;
				$subset->terms = array(); // @todo implement terms
				$subset->date = $post->date;

				// Custom data added to events by WSUWP Extended Events Calendar
				$subset->start_date = isset( $post->start_date ) ? $post->start_date : '';
				$subset->event_city = isset( $post->event_city ) ? $post->event_city : '';
				$subset->event_state = isset( $post->event_state ) ? $post->event_state : '';
				$subset->event_venue = isset( $post->event_venue ) ? $post->event_venue : '';

				$subset_key = strtotime( $post->date );
				while ( array_key_exists( $subset_key, $new_data ) ) {
					$subset_key++;
				}
				$new_data[ $subset_key ] = $subset;
			}
		}

		ob_start();
		if ( 'headlines' === $atts['output'] ) {
			?>
			<div class="wsuwp-content-syndicate-wrapper">
				<ul class="wsuwp-content-syndicate-list">
					<?php
					foreach( $new_data as $content ) {
						?>
						<li class="wsuwp-content-syndicate-event">
						<span class="content-item-event-date"><?php echo date( $atts['date_format'], strtotime( $content->start_date ) ); ?></span>
						<span class="content-item-event-title"><a href="<?php echo esc_url( $content->link ); ?>"><?php echo esc_html( $content->title ); ?></a></span>
							<span class="content-item-event-meta">
								<span class="content-item-event-venue"><?php echo esc_html( $content->event_venue ); ?></span>
								<span class="content-item-event-city"><?php echo esc_html( $content->event_city ); ?></span>
								<span class="content-item-event-state"><?php echo esc_html( $content->event_state ); ?></span>
							</span>
						</li><?php
					}
					?>
				</ul>
			</div>
			<?php
		}
		$content = ob_get_contents();
		ob_end_clean();

		// Store the built content in cache for repeated use.
		$this->set_content_cache( $atts, 'wsuwp_events', $content );

		$content = apply_filters( 'wsuwp_content_syndicate_json', $content, $atts );

		return $content;
	}
}