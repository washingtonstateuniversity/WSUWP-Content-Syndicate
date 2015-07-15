<?php

class WSU_Syndicate_Shortcode_Events extends WSU_Syndicate_Shortcode_Base {
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
		$defaults = array(
			'output' => 'headlines',
			'host' => 'calendar.wsu.edu',
			'site' => '',
			'tag' => '',
			'category' => '',
			'query' => 'posts/?type=tribe_events',
			'count' => false,
			'date_format' => 'M j',
			'cache_bust' => '',
		);
		$atts = shortcode_atts( $defaults, $atts );

		if ( ! empty( $atts['site'] ) ) {
			$site_url = trailingslashit( esc_url( $atts['site'] ) );
		} else {
			$site_url = trailingslashit( esc_url( $atts['host'] ) );
		}

		$site_url = parse_url( $site_url );

		if ( empty( $site_url['host'] ) ) {
			return '<!-- wsuwp_json ERROR - an empty host was supplied -->';
		}

		$host_parts = explode( '.', $site_url['host'] );
		$host_edu = array_pop( $host_parts );
		$host_wsu = array_pop( $host_parts );

		// We only support queries for wsu.edu domains by default
		if ( ( ! in_array( $host_edu, array( 'edu', 'dev' ) ) || 'wsu' !== $host_wsu ) && false === apply_filters( 'wsu_consyn_valid_domain', false, $site_url['host'] ) ) {
			return '<!-- wsuwp_json ERROR - not a valid domain -->';
		}

		// Retrieve existing content from cache if available.
		if ( $content = $this->get_content_cache( $atts, 'wsuwp_events' ) ) {
			return apply_filters( 'wsuwp_content_syndicate_json', $content, $atts );
		}

		if ( '' !== $atts['category'] ) {
			$atts['query'] = 'posts/?type=tribe_events&filter[taxonomy]=tribe_events_cat';

			$terms = explode( ',', $atts['category'] );
			foreach( $terms as $term ) {
				$term = trim( $term );
				$atts['query'] .= '&filter[term]=' . sanitize_key( $term );
			}
		}

		$request_url = esc_url( $site_url['host'] . $site_url['path'] . 'wp-json/' ) . $atts['query'];

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
			$data = json_decode( $data );

			foreach( $data as $post ) {
				$subset = new StdClass();
				$subset->ID = $post->ID;
				$subset->title = $post->title;
				$subset->link = $post->link;
				$subset->excerpt = $post->excerpt;
				$subset->content = $post->content;
				$subset->terms = $post->terms;
				$subset->date = $post->date;

				// Custom data added to events by WSUWP Extended Events Calendar
				$subset->start_date = isset( $post->meta->start_date ) ? $post->meta->start_date : '';
				$subset->event_city = isset( $post->meta->event_city ) ? $post->meta->event_city : '';
				$subset->event_state = isset( $post->meta->event_state ) ? $post->meta->event_state : '';
				$subset->event_venue = isset( $post->meta->event_venue ) ? $post->meta->event_venue : '';

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