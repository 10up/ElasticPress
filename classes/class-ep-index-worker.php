<?php
/**
 * Process indexing of posts
 *
 * @package ElasticPress
 *
 * @since   1.9
 *
 * @author  Chris Wiegman <chris.wiegman@10up.com>
 */

/**
 * Worker Process for Indexing posts
 *
 * Handles and dispatches the indexing of posts.
 */
class EP_Index_Worker {

	/**
	 * Holds the posts that will be bulk synced.
	 *
	 * @since 1.9
	 *
	 * @var array
	 */
	protected $posts;

	/**
	 * Initiate Index Worker
	 *
	 * Initiates the index worker process.
	 *
	 * @since 1.9
	 *
	 * @return EP_Index_Worker
	 */
	public function __construct() {

		$this->posts = array();

	}

	/**
	 * Create network alias
	 *
	 * Helper method for creating the network alias
	 *
	 * @since 1.9
	 *
	 * @return array|bool Array of indexes or false on error
	 */
	protected function _create_network_alias() {

		$sites   = ep_get_sites();
		$indexes = array();

		foreach ( $sites as $site ) {

			switch_to_blog( $site['blog_id'] );

			$indexes[] = ep_get_index_name();

			restore_current_blog();

		}

		return ep_create_network_alias( $indexes );

	}

	/**
	 * Index all posts
	 *
	 * Index all posts for a site or network wide.
	 *
	 * @since 1.9
	 *
	 * @return bool True on success or false
	 */
	public function index() {

		ep_check_host();

		$result = $this->_index_helper();

		if ( ! empty( $result['errors'] ) ) {
			return false;
		}

		return $result;

	}

	/**
	 * Helper method for indexing posts
	 *
	 * Handles the sync operation for individual posts.
	 *
	 * @since 1.9
	 *
	 * @return array Array of posts successfully synced as well as errors
	 */
	protected function _index_helper() {

		global $wpdb, $wp_object_cache;

		$posts_per_page = apply_filters( 'ep_index_posts_per_page', 350 );

		$offset_transient = get_transient( 'ep_index_offset' );
		$sync_transient   = get_transient( 'ep_index_synced' );

		$synced         = false === $sync_transient ? 0 : absint( $sync_transient );
		$errors         = array();
		$offset         = false === $offset_transient ? 0 : absint( $offset_transient );
		$complete       = false;
		$current_synced = 0;

		$args = apply_filters( 'ep_index_posts_args', array(
			'posts_per_page'         => $posts_per_page,
			'post_type'              => ep_get_indexable_post_types(),
			'post_status'            => ep_get_indexable_post_status(),
			'offset'                 => $offset,
			'ignore_sticky_posts'    => true,
			'orderby'                => 'ID',
			'order'                  => 'DESC',
		) );

		$query = new WP_Query( $args );

		if ( $query->have_posts() ) {

			while ( $query->have_posts() ) {

				$query->the_post();

				$result = $this->queue_post( get_the_ID(), $query->post_count, $offset );

				if ( ! $result ) {

					$errors[] = get_the_ID();

				} else {

					$current_synced ++;
					$synced ++;

				}
			}

			$totals = get_transient( 'ep_post_count' );

			if ( $totals['total'] === $synced ) {
				$complete = true;
			}
		} else {

			$complete = true;

		}

		$offset += $posts_per_page;

		usleep( 500 ); // Delay to let $wpdb catch up.

		// Avoid running out of memory.
		$wpdb->queries = array();

		if ( is_object( $wp_object_cache ) ) {

			$wp_object_cache->group_ops      = array();
			$wp_object_cache->stats          = array();
			$wp_object_cache->memcache_debug = array();

			// Make sure this is a public property, before trying to clear it
			try {
				$cache_property = new ReflectionProperty( $wp_object_cache, 'cache' );
				if ( $cache_property->isPublic() ) {
					$wp_object_cache->cache = array();
				}
				unset( $cache_property );
			} catch ( ReflectionException $e ) {
			}

			if ( is_callable( array( $wp_object_cache, '__remoteset' ) ) ) {
				call_user_func( array( $wp_object_cache, '__remoteset' ) ); // Important.
			}
		}

		// Prevent wp_actions from growing out of control
		$wp_actions = array();

		set_transient( 'ep_index_offset', $offset, 600 );
		set_transient( 'ep_index_synced', $synced, 600 );

		if ( true === $complete ) {

			delete_transient( 'ep_index_offset' );

			/**
			 * Allow disabling of bulk error email.
			 *
			 * @since 1.9
			 *
			 * @param bool $to_send true to send bulk errors or false [Default: true]
			 */
			if ( true === apply_filters( 'ep_disable_index_bulk_errors', true ) ) {
				$this->send_bulk_errors();
			}
		}

		wp_reset_postdata();

		return array( 'synced' => $synced, 'current_synced' => $current_synced, 'errors' => $errors );

	}

	/**
	 * Queues up a post for bulk indexing
	 *
	 * Adds individual posts to a queue for later processing.
	 *
	 * @since 1.9
	 *
	 * @param int $post_id      The post ID to add.
	 * @param int $bulk_trigger The maximum number of posts to hold in the queue before triggering a bulk-update operation.
	 * @param int $offset       The current offset to keep track of.
	 *
	 * @return bool True on success or false
	 */
	protected function queue_post( $post_id, $bulk_trigger, $offset ) {

		static $post_count = 0;

		// Put the post into the queue.
		$this->posts[ $post_id ][] = '{ "index": { "_id": "' . absint( $post_id ) . '" } }';
		$this->posts[ $post_id ][] = addcslashes( wp_json_encode( ep_prepare_post( $post_id ) ), "\n" );

		// Augment the counter.
		++ $post_count;

		// If we have hit the trigger, initiate the bulk request.
		if ( absint( $bulk_trigger ) === $post_count ) {

			$this->bulk_index( $offset );

			// Reset the post count.
			$post_count = 0;

			// Reset the posts.
			$this->posts = array();

		}

		return true;

	}

	/**
	 * Perform the bulk index operation
	 *
	 * Sends multiple posts to the ES server at once.
	 *
	 * @since 1.9
	 *
	 * @param int $offset The current offset to keep track of.
	 *
	 * @return bool|WP_Error true on success or WP_Error on failure
	 */
	protected function bulk_index( $offset ) {

		$failed_transient = get_transient( 'ep_index_failed_posts' );

		$failed_posts  = is_array( $failed_transient ) ? $failed_transient : array();
		$failed_blocks = array();

		// Monitor how many times we attempt to add this particular bulk request.
		static $attempts = 0;

		// Augment the attempts.
		++ $attempts;

		// Make sure we actually have something to index.
		if ( empty( $this->posts ) ) {
			return 0;
		}

		$flatten = array();

		foreach ( $this->posts as $post ) {

			$flatten[] = $post[0];
			$flatten[] = $post[1];

		}

		// Make sure to add a new line at the end or the request will fail.
		$body = rtrim( implode( PHP_EOL, $flatten ) ) . PHP_EOL;

		// Decode the response.
		$response = ep_bulk_index_posts( $body );

		do_action( 'ep_post_bulk_index', $this->posts );

		if ( is_wp_error( $response ) ) {

			$failed_blocks   = is_array( get_transient( 'ep_index_failed_blocks' ) ) ? get_transient( 'ep_index_failed_blocks' ) : array();
			$failed_blocks[] = $offset;

			return $response;

		}

		// If we did have errors, try to add the documents again.
		if ( isset( $response['errors'] ) && true === $response['errors'] ) {

			if ( $attempts < 5 ) {

				foreach ( $response['items'] as $item ) {

					if ( empty( $item['index']['error'] ) ) {
						unset( $this->posts[ $item['index']['_id'] ] );
					}
				}

				$this->bulk_index( $offset );

			} else {

				foreach ( $response['items'] as $item ) {

					if ( ! empty( $item['index']['_id'] ) ) {

						$failed_blocks[] = $offset;
						$failed_posts[]  = $item['index']['_id'];

					}
				}

				$attempts = 0;
			}
		} else {

			// There were no errors, all the posts were added.
			$attempts = 0;

		}

		if ( empty( $failed_posts ) ) {

			delete_transient( 'ep_index_failed_posts' );

		} else {

			set_transient( 'ep_index_failed_posts', $failed_posts, 600 );

		}

		if ( empty( $failed_blocks ) ) {

			delete_transient( 'ep_index_failed_blocks' );

		} else {

			set_transient( 'ep_index_failed_blocks', $failed_blocks, 600 );

		}

		return true;

	}

	/**
	 * Send any bulk indexing errors
	 *
	 * Emails bulk errors regarding any posts that failed to index.
	 *
	 * @since 1.9
	 *
	 * @return void
	 */
	protected function send_bulk_errors() {

		$failed_posts = get_transient( 'ep_index_failed_posts' );

		if ( false !== $failed_posts && is_array( $failed_posts ) ) {

			$email_text = esc_html__( 'The following posts failed to index:' . PHP_EOL . PHP_EOL, 'elasticpress' );

			foreach ( $failed_posts as $failed ) {

				$failed_post = get_post( $failed );

				if ( $failed_post ) {
					$email_text .= "- {$failed}: " . $failed_post->post_title . PHP_EOL;
				}
			}

			/**
			 * Filter the email text used to send the bulk error email
			 *
			 * @since 1.9
			 *
			 * @param string $email_text The message body of the bulk error email.
			 */
			$email_text = apply_filters( 'ep_bulk_errors_email_text', $email_text );

			/**
			 * Filter the email subject used to send the bulk error email
			 *
			 * @since 1.9
			 *
			 * @param string $email_subject The subject of the bulk error email.
			 */
			$email_subject = apply_filters( 'ep_bulk_errors_email_subject', wp_specialchars_decode( get_option( 'blogname' ) ) . esc_html__( ': ElasticPress Index Errors', 'elasticpress' ) );

			/**
			 * Filter the email recipient who should receive the bulk indexing errors
			 *
			 * @since 1.9
			 *
			 * @param string $email_to The email address to which the bulk errors should be sent.
			 */
			$email_to = apply_filters( 'wp_bulk_errors_email_to', get_option( 'admin_email' ) );

			wp_mail( $email_to, $email_subject, $email_text );

			// Clear failed posts after sending emails.
			delete_transient( 'ep_index_failed_posts' );

		}
	}
}
