<?php

namespace WSU\Content_Syndicate;

add_action( 'save_post_post', 'WSU\Content_Syndicate\clear_local_content_cache' );

/**
 * Clear the last changed cache for local results whenever
 * a post is saved.
 *
 * @since 1.3.0
 */
function clear_local_content_cache() {
	wp_cache_set( 'last_changed', microtime(), 'wsuwp-content' );
}
