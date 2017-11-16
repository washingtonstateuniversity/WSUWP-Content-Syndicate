<?php

class WSU_Syndicate_Shortcode_JSON extends WSU_Syndicate_Shortcode_Base {

	/**
	 * @var string Shortcode name.
	 */
	public $shortcode_name = 'wsuwp_json';

	public function __construct() {
		parent::construct();
	}

	/**
	 * Add the shortcode provided.
	 */
	public function add_shortcode() {
		add_shortcode( 'wsuwp_json', array( $this, 'display_shortcode' ) );
	}

	/**
	 * Process the requested parameters for use with the WordPress JSON API and output
	 * the response accordingly.
	 *
	 * @param array $atts {
	 *     Attributes passed with the shortcode.
	 *
	 *     @type string $object                   The name of the JSON object to use when output is set to json.
	 *     @type string $output                   The type of output to display.
	 *                              - json           Output a JSON object to be used with custom Javascript.
	 *                              - headlines      Display an unordered list of headlines.
	 *                              - excerpts       Display only excerpt information in an unordered list.
	 *                              - full           Display full content for each item.
	 *     @type string $host                     The hostname to pull items from. Defaults to news.wsu.edu.
	 *     @type string $site                     Overrides setting for host. Hostname and path to pull items from.
	 *     @type string $query                    Allows for a custom WP-API query. Defaults as "posts". Any
	 *     @type int    $local_count              The number of local items to merge with the remote results.
	 *     @type int    $count                    The number of items to pull from a feed. Defaults to the
	 *                                            posts_per_page setting of the remote site.
	 *     @type string $date_format              PHP Date format for the output of the item's date.
	 *     @type int    $offset                   The number of items to offset when displaying. Used with multiple
	 *                                            shortcode instances where one may pull in an excerpt and another
	 *                                            may pull in the rest of the feed as headlines.
	 *     @type string $cache_bust               Any change to this value will clear the cache and pull fresh data.
	 * }
	 *
	 * @return string Data to output where the shortcode is used.
	 */
	public function display_shortcode( $atts ) {
		$atts = $this->process_attributes( $atts );

		$site_url = $this->get_request_url( $atts );
		if ( ! $site_url ) {
			return '<!-- wsuwp_json ERROR - an empty host was supplied -->';
		}

		$request = $this->build_initial_request( $site_url, $atts );
		$request_url = $this->build_taxonomy_filters( $atts, $request['url'] );

		if ( 'headlines' === $atts['output'] ) {
			$request_url = add_query_arg( array(
				'_fields[]' => 'title',
			), $request_url );

			$request_url = add_query_arg( array(
				'_fields[]' => 'link',
			), $request_url );
		}

		if ( ! empty( $atts['offset'] ) ) {
			$atts['count'] = absint( $atts['count'] ) + absint( $atts['offset'] );
		}

		if ( $atts['count'] ) {
			$count = ( 100 < absint( $atts['count'] ) ) ? 100 : $atts['count'];
			$request_url = add_query_arg( array(
				'per_page' => absint( $count ),
			), $request_url );
		}

		$request_url = add_query_arg( array(
			'_embed' => '',
		), $request_url );

		if ( 'local' === $request['scheme'] ) {
			$last_changed = wp_cache_get_last_changed( 'wsuwp-content' );
			$cache_key = md5( $request_url ) . ':' . $last_changed;
			$new_data = wp_cache_get( $cache_key, 'wsuwp-content' );

			if ( ! is_array( $new_data ) ) {
				$request = WP_REST_Request::from_url( $request_url );
				$response = rest_do_request( $request );
				if ( 200 === $response->get_status() ) {
					$new_data = $this->process_local_posts( $response->data, $atts );
				}

				wp_cache_set( $cache_key, $new_data, 'wsuwp-content' );
			}
		} else {
			$new_data = $this->get_content_cache( $atts, 'wsuwp_json' );

			if ( ! is_array( $new_data ) ) {
				$response = wp_remote_get( $request_url );

				if ( ! is_wp_error( $response ) && 404 !== wp_remote_retrieve_response_code( $response ) ) {
					$data = wp_remote_retrieve_body( $response );
					$data = json_decode( $data );

					if ( null === $data ) {
						$data = array();
					}

					$new_data = $this->process_remote_posts( $data, $atts );

					// Store the built content in cache for repeated use.
					$this->set_content_cache( $atts, 'wsuwp_json', $new_data );
				}
			}
		}

		if ( ! is_array( $new_data ) ) {
			$new_data = array();
		}

		if ( 0 !== absint( $atts['local_count'] ) ) {
			$local_atts = array();
			foreach ( $atts as $attribute => $value ) {
				if ( 0 === stripos( $attribute, 'local_' ) ) {
					$local_atts[ substr( $attribute, 6 ) ] = $value;
				} else {
					$local_atts[ $attribute ] = $value;
				}
			}

			$local_atts['host'] = get_site()->domain . get_site()->path;
			$local_atts['count'] = $atts['local_count'];

			$local_url = $this->get_request_url( $local_atts );

			$request = $this->build_initial_request( $local_url, $local_atts );
			$request_url = $this->build_taxonomy_filters( $local_atts, $request['url'] );

			$local_count = ( 100 < absint( $local_atts['count'] ) ) ? 100 : $local_atts['count'];
			$request_url = add_query_arg( array(
				'per_page' => absint( $local_count ),
				'_embed' => '',
			), $request_url );

			$last_changed = wp_cache_get_last_changed( 'wsuwp-content' );
			$cache_key = md5( $request_url ) . ':' . $last_changed;
			$local_data = wp_cache_get( $cache_key, 'wsuwp-content' );

			if ( ! is_array( $local_data ) ) {
				$request = WP_REST_Request::from_url( $request_url );
				$response = rest_do_request( $request );

				$local_data = array();
				if ( 200 === $response->get_status() ) {
					$local_data = $this->process_local_posts( $response->data, $atts );
				}

				wp_cache_set( $cache_key, $local_data, 'wsuwp-content' );
			}

			if ( is_array( $local_data ) ) {
				$new_data = $new_data + $local_data;
			}
		} // End if().

		// Reverse sort the array of data by date.
		krsort( $new_data );

		// Only provide a count to match the total count, the array may be larger if local
		// items are also requested.
		if ( $atts['count'] ) {
			$new_data = array_slice( $new_data, 0, $atts['count'], false );
		}

		$content = apply_filters( 'wsuwp_content_syndicate_json_output', false, $new_data, $atts );

		if ( false === $content ) {
			$content = $this->generate_shortcode_output( $new_data, $atts );
		}

		$content = apply_filters( 'wsuwp_content_syndicate_json', $content, $atts );

		return $content;
	}

	/**
	 * Generates the content to display for a shortcode.
	 *
	 * @since 1.1.0
	 *
	 * @param array $new_data Data containing the posts to be displayed.
	 * @param array $atts     Array of options passed with the shortcode.
	 *
	 * @return string Content to display for the shortcode.
	 */
	private function generate_shortcode_output( $new_data, $atts ) {
		ob_start();
		// By default, we output a JSON object that can then be used by a script.
		if ( 'json' === $atts['output'] ) {
			echo '<script>var ' . esc_js( $atts['object'] ) . ' = ' . wp_json_encode( $new_data ) . ';</script>';
		} elseif ( 'headlines' === $atts['output'] ) {
			?>
			<div class="wsuwp-content-syndicate-wrapper">
				<ul class="wsuwp-content-syndicate-list">
					<?php
					$offset_x = 0;
					foreach ( $new_data as $content ) {
						if ( $offset_x < absint( $atts['offset'] ) ) {
							$offset_x++;
							continue;
						}
						?><li class="wsuwp-content-syndicate-item"><a href="<?php echo esc_url( $content->link ); ?>"><?php echo esc_html( $content->title ); ?></a></li><?php
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
					foreach ( $new_data as $content ) {
						if ( $offset_x < absint( $atts['offset'] ) ) {
							$offset_x++;
							continue;
						}
						?>
						<li class="wsuwp-content-syndicate-item">
							<span class="content-item-thumbnail"><?php if ( $content->thumbnail ) : ?><img src="<?php echo esc_url( $content->thumbnail ); ?>"><?php endif; ?></span>
							<span class="content-item-title"><a href="<?php echo esc_url( $content->link ); ?>"><?php echo esc_html( $content->title ); ?></a></span>
							<span class="content-item-byline">
								<span class="content-item-byline-date"><?php echo esc_html( date( $atts['date_format'], strtotime( $content->date ) ) ); ?></span>
								<span class="content-item-byline-author"><?php echo esc_html( $content->author_name ); ?></span>
							</span>
							<span class="content-item-excerpt"><?php echo wp_kses_post( $content->excerpt ); ?> <a class="content-item-read-story" href="<?php echo esc_url( $content->link ); ?>">Read Story</a></span>
						</li>
						<?php
					}
					?>
				</ul>
			</div>
			<?php
		} elseif ( 'full' === $atts['output'] ) {
			?>
			<div class="wsuwp-content-syndicate-wrapper">
				<div class="wsuwp-content-syndicate-container">
					<?php
					$offset_x = 0;
					foreach ( $new_data as $content ) {
						if ( $offset_x < absint( $atts['offset'] ) ) {
							$offset_x++;
							continue;
						}
						?>
						<div class="wsuwp-content-syndicate-full">
							<span class="content-item-thumbnail">
								<?php if ( $content->thumbnail ) : ?><img src="<?php echo esc_url( $content->thumbnail ); ?>"><?php endif; ?>
							</span>
							<span class="content-item-title"><a href="<?php echo esc_url( $content->link ); ?>"><?php echo esc_html( $content->title ); ?></a></span>
							<span class="content-item-byline">
								<span class="content-item-byline-date"><?php echo esc_html( date( $atts['date_format'], strtotime( $content->date ) ) ); ?></span>
								<span class="content-item-byline-author"><?php echo esc_html( $content->author_name ); ?></span>
							</span>
							<div class="content-item-content">
								<?php echo wp_kses_post( $content->content ); ?>
							</div>
						</div>
						<?php
					}
					?>
				</div>
			</div>
			<?php
		} // End if().
		$content = ob_get_contents();
		ob_end_clean();

		return $content;
	}

	/**
	 * Process REST API results received remotely through `wp_remote_get()`
	 *
	 * @since 0.9.0
	 *
	 * @param object $data List of post data.
	 * @param array  $atts Attributes passed with the original shortcode.
	 *
	 * @return array Array of objects representing individual posts.
	 */
	public function process_remote_posts( $data, $atts ) {
		if ( empty( $data ) ) {
			return array();
		}

		$new_data = array();

		foreach ( $data as $post ) {
			$subset = new StdClass();

			// Only a subset of data is returned for a headlines request.
			if ( 'headlines' === $atts['output'] ) {
				$subset->link = $post->link;
				$subset->title = $post->title->rendered;
			} else {
				$subset->ID = $post->id;
				$subset->date = $post->date; // In time zone of requested site
				$subset->link = $post->link;

				// These fields all provide a rendered version when the response is generated.
				$subset->title   = $post->title->rendered;
				$subset->content = $post->content->rendered;
				$subset->excerpt = $post->excerpt->rendered;

				// If a featured image is assigned (int), the full data will be in the `_embedded` property.
				if ( ! empty( $post->featured_media ) && isset( $post->_embedded->{'wp:featuredmedia'} ) && 0 < count( $post->_embedded->{'wp:featuredmedia'} ) ) {
					$subset->featured_media = $post->_embedded->{'wp:featuredmedia'}[0];

					if ( isset( $subset->featured_media->media_details->sizes->{'post-thumbnail'} ) ) {
						$subset->thumbnail = $subset->featured_media->media_details->sizes->{'post-thumbnail'}->source_url;
					} elseif ( isset( $subset->featured_media->media_details->sizes->{'thumbnail'} ) ) {
						$subset->thumbnail = $subset->featured_media->media_details->sizes->{'thumbnail'}->source_url;
					} else {
						$subset->thumbnail = $subset->featured_media->source_url;
					}
				} else {
					$subset->thumbnail = false;
				}

				// If an author is available, it will be in the `_embedded` property.
				if ( isset( $post->_embedded ) && isset( $post->_embedded->author ) && 0 < count( $post->_embedded->author ) ) {
					$subset->author_name = $post->_embedded->author[0]->name;
				} else {
					$subset->author_name = '';
				}

				// We've always provided an empty value for terms. @todo Implement terms. :)
				$subset->terms = array();
			} // End if().

			/**
			 * Filter the data stored for an individual result after defaults have been built.
			 *
			 * @since 0.7.10
			 *
			 * @param object $subset Data attached to this result.
			 * @param object $post   Data for an individual post retrieved via `wp-json/posts` from a remote host.
			 * @param array  $atts   Attributes originally passed to the `wsuwp_json` shortcode.
			 */
			$subset = apply_filters( 'wsu_content_syndicate_host_data', $subset, $post, $atts );

			if ( isset( $post->date ) && $post->date ) {
				$subset_key = strtotime( $post->date );
			} else {
				$subset_key = time();
			}

			while ( array_key_exists( $subset_key, $new_data ) ) {
				$subset_key++;
			}
			$new_data[ $subset_key ] = $subset;
		} // End foreach().

		return $new_data;
	}

	/**
	 * Process REST API results received locally through `rest_do_request()`
	 *
	 * @since 0.9.0
	 *
	 * @param array $data Array of post data.
	 * @param array $atts Attributes passed with the original shortcode.
	 *
	 * @return array Array of objects representing individual posts.
	 */
	public function process_local_posts( $data, $atts ) {
		if ( empty( $data ) ) {
			return array();
		}

		$new_data = array();

		foreach ( $data as $post ) {
			// Convert array to an object so that data can be handled as if it was remote.
			$post = json_decode( wp_json_encode( $post ) );

			$subset = new stdClass();

			// Only a subset of data is returned for a headlines request.
			if ( 'headlines' === $atts['output'] ) {
				$subset->link = $post->link;
				$subset->title = $post->title->rendered;
			} else {
				$subset->ID = $post->id;
				$subset->date = $post->date; // In time zone of requested site
				$subset->link = $post->link;

				// These fields all provide a rendered version when the response is generated.
				$subset->title   = $post->title->rendered;
				$subset->content = $post->content->rendered;
				$subset->excerpt = $post->excerpt->rendered;

				if ( ! empty( $post->featured_media ) && ! empty( $post->_links->{'wp:featuredmedia'} ) ) {
					$media_request_url = $post->_links->{'wp:featuredmedia'}[0]->href;
					$media_request = WP_REST_Request::from_url( $media_request_url );
					$media_response = rest_do_request( $media_request );

					// Convert array to an object so that data can be handled as if it was remote.
					$data = json_decode( wp_json_encode( $media_response->data ) );

					$subset->featured_media = $data;

					if ( isset( $data->media_details->sizes->{'post-thumbnail'} ) ) {
						$subset->thumbnail = $data->media_details->sizes->{'post-thumbnail'}->source_url;
					} elseif ( isset( $data->media_details->sizes->thumbnail ) ) {
						$subset->thumbnail = $data->media_details->sizes->thumbnail->source_url;
					} else {
						$subset->thumbnail = $data->source_url;
					}
				} else {
					$subset->thumbnail = false;
				}

				$subset->author_name = '';

				if ( ! empty( $post->author ) && ! empty( $post->_links->author ) ) {
					$author_request_url = $post->_links->author[0]->href;
					$author_request = WP_REST_Request::from_url( $author_request_url );
					$author_response = rest_do_request( $author_request );
					if ( isset( $author_response->data['name'] ) ) {
						$subset->author_name = $author_response->data['name'];
					}
				}

				// We've always provided an empty value for terms. @todo Implement terms. :)
				$subset->terms = array();
			} // End if().

			/**
			 * Filter the data stored for an individual result after defaults have been built.
			 *
			 * @since 0.7.10
			 *
			 * @param object $subset Data attached to this result.
			 * @param object $post   Data for an individual post retrieved via `wp-json/posts` from a remote host.
			 * @param array  $atts   Attributes originally passed to the `wsuwp_json` shortcode.
			 */
			$subset = apply_filters( 'wsu_content_syndicate_host_data', $subset, $post, $atts );

			if ( isset( $post->date ) && $post->date ) {
				$subset_key = strtotime( $post->date );
			} else {
				$subset_key = time();
			}

			while ( array_key_exists( $subset_key, $new_data ) ) {
				$subset_key++;
			}
			$new_data[ $subset_key ] = $subset;
		} // End foreach().

		return $new_data;
	}
}
