<?php
/*
Plugin Name: WSU Content Syndicate
Plugin URI: http://web.wsu.edu
Description: Retrieve content for display from throughout Washington State University
Author: washingtonstateuniversity, jeremyfelt
Version: 0.4.5
*/

class WSU_Content_Syndicate {
	/**
	 * Setup hooks for shortcodes.
	 */
	public function __construct() {
		add_shortcode( 'wsuwp_json', array( $this, 'display_wsuwp_json' ) );
		add_shortcode( 'wsuwp_elastic', array( $this, 'display_wsuwp_elastic' ) );
		add_shortcode( 'wsuwp_events', array( $this, 'display_wsuwp_events' ) );
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
			'output' => 'json', // Can also be headlines, excerpts, or full
			'host' => 'news.wsu.edu',
			'university_category_slug' => '',
			'site_category_slug' => '',
			'tag' => '',
			'query' => 'posts',
			'local_count' => 0,
			'count' => false,
			'date_format' => 'F j, Y',
			'offset' => 0,
			'cache_bust' => '',
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

		$atts_key = md5( serialize( $atts ) );

		if ( $content = wp_cache_get( $atts_key, 'wsuwp_content' ) ) {
			return apply_filters( 'wsuwp_content_syndicate_json', $content, $atts );
		}

		$request_url = esc_url( $host['host'] . '/wp-json/' ) . $atts['query'];

		if ( ! empty( $atts['university_category_slug'] ) ) {
			$request_url = add_query_arg( array(
				'filter[taxonomy]' => 'wsuwp_university_category',
				'filter[term]' => sanitize_key( $atts['university_category_slug'] )
			), $request_url );
		}

		if ( ! empty( $atts['site_category_slug'] ) ) {
			$request_url = add_query_arg( array(
				'filter[taxonomy]' => 'category',
				'filter[term]' => sanitize_key( $atts['site_category_slug'] )
			), $request_url );
		}

		if ( ! empty( $atts['tag'] ) ) {
			$request_url = add_query_arg( array( 'filter[tag]' => sanitize_key( $atts['tag'] ) ), $request_url );
		}

		if ( ! empty( $atts['offset'] ) ) {
			$atts['count'] = absint( $atts['count'] ) + absint( $atts['offset'] );
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
				$subset->author_name = $post->author->name;
				$subset->author_avatar = $post->author->avatar;
				if ( isset( $post->featured_image ) ) {
					$subset->thumbnail = $post->featured_image->attachment_meta->sizes->{'post-thumbnail'}->url;
				} else {
					$subset->thubmnail = false;
				}

				if ( $post->date ) {
					$subset_key = strtotime( $post->date );
				} else {
					$subset_key = time();
				}

				while ( array_key_exists( $subset_key, $new_data ) ) {
					$subset_key++;
				}
				$new_data[ $subset_key ] = $subset;
			}
		}

		if ( 0 !== absint( $atts['local_count'] ) ) {
			$news_query_args = array( 'post_type' => 'post', 'posts_per_page' => absint( $atts['local_count'] ) );
			$news_query = new WP_Query( $news_query_args );

			while ( $news_query->have_posts() ) {
				$news_query->the_post();
				$subset = new StdClass();
				$subset->ID = get_the_ID();
				$subset->date = get_the_date();
				$subset->title = get_the_title();
				$subset->link = get_the_permalink();
				$subset->excerpt = get_the_excerpt();
				$subset->thumbnail = get_the_post_thumbnail( get_the_ID(), 'post-thumbnail' );

				// Split the content to display an excerpt marked by a more tag.
				$subset_content = get_the_content();
				$subset_content = explode( '<span id="more', $subset_content );
				$subset_content = wpautop( $subset_content[0] );

				$subset->content = apply_filters( 'the_content', $subset_content );
				$subset->terms = array();
				$subset->author_name = get_the_author();
				$subset->author_avatar = '';

				$subset_key = get_the_date( 'U' );
				while ( array_key_exists( $subset_key, $new_data ) ) {
					$subset_key++;
				}
				$new_data[ $subset_key ] = $subset;
			}
			wp_reset_query();
		}

		// Reverse sort the array of data by date.
		krsort( $new_data );

		// Only provide a count to match the total count, the array may be larger if local
		// items are also requested.
		$new_data = array_slice( $new_data, 0, $atts['count'], false );

		ob_start();
		// By default, we output a JSON object that can then be used by a script.
		if ( 'json' === $atts['output'] ) {
			$data = json_encode( $new_data );
			echo '<script>var ' . esc_js( $atts['object'] ) . ' = ' . $data . ';</script>';
		} elseif ( 'headlines' === $atts['output'] ) {
			?>
			<div class="wsuwp-content-syndicate-wrapper">
				<ul class="wsuwp-content-syndicate-list">
			<?php
			$offset_x = 0;
			foreach( $new_data as $content ) {
				if ( $offset_x < absint( $atts['offset'] ) ) {
					$offset_x++;
					continue;
				}
				?><li class="wsuwp-content-syndicate-item"><a href="<?php echo $content->link; ?>"><?php echo $content->title; ?></a></li><?php
			}
			?>
				</ul>
			</div>
			<?php
		} elseif ( 'excerpts' === $atts['output'] ) {
			?>
			<div class="wsuwp-content-syndicate-wrapper">
				<ul class="wsuwp-content-syndicate-list">
					<?php
					$offset_x = 0;
					foreach( $new_data as $content ) {
						if ( $offset_x < absint( $atts['offset'] ) ) {
							$offset_x++;
							continue;
						}
						?>
						<li class="wsuwp-content-syndicate-item">
							<span class="content-item-thumbnail">
								<?php if ( $content->thumbnail ) : ?><img src="<?php echo $content->thumbnail; ?>"><?php endif; ?></span>
							<span class="content-item-title"><a href="<?php echo $content->link; ?>"><?php echo $content->title; ?></a></span>
							<span class="content-item-byline">
								<span class="content-item-byline-date"><?php echo date( $atts['date_format'], strtotime( $content->date ) ); ?></span>
								<span class="content-item-byline-author"><?php echo $content->author_name; ?></span>
							</span>
							<span class="content-item-excerpt"><?php echo $content->excerpt; ?> <a class="content-item-read-story" href="<?php echo $content->link; ?>">Read Story</a></span>
						</li>
					<?php
					}
					?>
				</ul>
			</div>
		<?php
		} else {
			// Account for full HTML output here.
		}
		$content = ob_get_contents();
		ob_end_clean();

		wp_cache_add( $atts_key, $content, 'wsuwp_content', 600 );

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

	/**
	 * Display events information for the [wsuwp_events] shortcode.
	 *
	 * @param array $atts
	 *
	 * @return string
	 */
	public function display_wsuwp_events( $atts ) {
		$defaults = array(
			'output' => 'headlines', // Can also be sidebar, full
			'host' => 'calendar.wsu.edu',
			'tag' => '',
			'category' => '',
			'query' => 'posts/?type=tribe_events',
			'count' => false,
			'date_format' => 'M j',
			'cache_bust' => '',
		);
		$atts = shortcode_atts( $defaults, $atts );

		// We only support queries for wsu.edu domains by default
		$host = parse_url( esc_url( $atts['host'] ) );
		if ( empty( $host['host'] ) ) {
			return '<!-- wsuwp_json ERROR - an empty host was supplied -->';
		}

		$host_parts = explode( '.', $host['host'] );
		$host_edu = array_pop( $host_parts );
		$host_wsu = array_pop( $host_parts );

		if ( ( ! in_array( $host_edu, array( 'edu', 'dev' ) ) || 'wsu' !== $host_wsu ) && false === apply_filters( 'wsu_consyn_valid_domain', false, $host['host'] ) ) {
			return '<!-- wsuwp_json ERROR - not a valid domain -->';
		}

		$atts_key = md5( serialize( $atts ) );

		if ( $content = wp_cache_get( $atts_key, 'wsuwp_content' ) ) {
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

		$request_url = esc_url( $host['host'] . '/wp-json/' ) . $atts['query'];

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

		wp_cache_add( $atts_key, $content, 'wsuwp_content', 600 );

		$content = apply_filters( 'wsuwp_content_syndicate_json', $content, $atts );

		return $content;
	}
}
new WSU_Content_Syndicate();
