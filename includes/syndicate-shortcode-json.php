<?php

class WSU_Syndicate_Shortcode_JSON extends WSU_Syndicate_Shortcode_Base {
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
	 *     @type string $university_category_slug The slug of a University Category from the University Taxonomy.
	 *     @type string $site_category_slug       The slug of a Site Category. Defaults to empty.
	 *     @type string $tag                      The slug of a tag. Defaults to empty.
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

		if ( ! $site_url = $this->get_request_url( $atts ) ) {
			return '<!-- wsuwp_json ERROR - an empty host was supplied -->';
		}

		// Retrieve existing content from cache if available.
		if ( $content = $this->get_content_cache( $atts, 'wsuwp_json' ) ) {
			return apply_filters( 'wsuwp_content_syndicate_json', $content, $atts );
		}

		$request_url = esc_url( $site_url['host'] . $site_url['path'] . $this->default_path ) . $atts['query'];

		$request_url = $this->build_taxonomy_filters( $atts, $request_url );

		if ( ! empty( $atts['offset'] ) ) {
			$atts['count'] = absint( $atts['count'] ) + absint( $atts['offset'] );
		}

		if ( $atts['count'] ) {
			$request_url = add_query_arg( array( 'filter[posts_per_page]' => absint( $atts['count'] ) ), $request_url );
		}

		$request_url = add_query_arg( array( '_embed' => '' ), $request_url );

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
				$subset->date = $post->date; // In time zone of requested site
				$subset->link = $post->link;

				// These fields all provide a rendered version when the response is generated.
				$subset->title   = $post->title->rendered;
				$subset->content = $post->content->rendered;
				$subset->excerpt = $post->excerpt->rendered;

				if ( isset( $post->featured_image ) && isset( $post->_embedded->{"http://api.w.org/featuredmedia"} ) ) {
					$subset_feature = $post->_embedded->{"http://api.w.org/featuredmedia"}[0]->media_details;

					if ( isset( $subset_feature->sizes->{'post-thumbnail'} ) ) {
						$subset->thumbnail = $subset_feature->sizes->{'post-thumbnail'}->source_url;
					} elseif ( isset( $subset_feature->sizes->{'thumbnail'} ) ) {
						$subset->thumbnail = $subset_feature->sizes->{'thumbnail'}->source_url;
					} else {
						$subset->thumbnail = $post->_embedded->{"http://api.w.org/featuredmedia"}[0]->source_url;
					}
				} else {
					$subset->thumbnail = false;
				}

				if ( isset( $post->_embedded ) ) {
					$subset->author_name = $post->_embedded->author[0]->name;
					$subset->terms = ''; // @todo implement
				} else {
					$subset->terms = '';
					$subset->author_name = '';
				}

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

				$subset->thumbnail = false;
				// Retrieve the source URL for any featured image assigned to the post.
				$post_thumbnail_id = get_post_thumbnail_id( get_the_ID() );
				if ( 0 < absint( $post_thumbnail_id ) ) {
					$post_thumbnail_src = wp_get_attachment_image_src( $post_thumbnail_id, 'post-thumbnail' );
					if ( $post_thumbnail_src ) {
						$subset->thumbnail = $post_thumbnail_src[0];
					}
				}

				// Split the content to display an excerpt marked by a more tag.
				$subset_content = get_the_content();
				$subset_content = explode( '<span id="more', $subset_content );
				$subset_content = wpautop( $subset_content[0] );

				$subset->content = apply_filters( 'the_content', $subset_content );
				$subset->terms = array();
				$subset->author_name = get_the_author();
				$subset->author_avatar = '';

				/**
				 * Filter the data stored for an individual local result after defaults have been built.
				 *
				 * @since 0.7.10
				 *
				 * @param object $subset Data attached to this result. Corresponds to a local post.
				 * @param array  $atts   Attributes originally passed to the `wsuwp_json` shortcode.
				 */
				$subset = apply_filters( 'wsu_content_syndicate_local_data', $subset, $atts );

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
		if ( $atts['count'] ) {
			$new_data = array_slice( $new_data, 0, $atts['count'], false );
		}

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
								<span class="content-item-byline-date"><?php echo date( $atts['date_format'], strtotime( $content->date ) ); ?></span>
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
		}
		$content = ob_get_contents();
		ob_end_clean();

		// Store the built content in cache for repeated use.
		$this->set_content_cache( $atts, 'wsuwp_json', $content );

		$content = apply_filters( 'wsuwp_content_syndicate_json', $content, $atts );

		return $content;
	}
}