<?php

class EP_Sync_Manager {

	/**
	 * Placeholder method
	 *
	 * @since 0.1.0
	 */
	public function __construct() { }

	/**
	 * Setup actions and filters
	 *
	 * @since 0.1.2
	 */
	public function setup() {
		add_action( 'transition_post_status', array( $this, 'action_sync_on_transition' ), 10, 3 );
		add_action( 'delete_post', array( $this, 'action_delete_post' ) );
		add_action( 'edited_terms',  array( $this, 'action_sync_on_term_update' ), 10, 2  );
	}

	/**
	 * Sync ES index with the post under updated term
	 *
	 * @param  int $term_id  ID of the eidted term
	 * @param  string $taxonomy
	 */
	public function action_sync_on_term_update( $term_id, $taxonomy ) {
		global $wpdb, $wp_object_cache;

		$posts_to_sync = array();
		$posts_per_page = 500;
		$offset = 0;

		$indexable_post_statuses = ep_get_indexable_post_status();
		$indexable_post_types = ep_get_indexable_post_types();

		if( empty( $indexable_post_statuses ) ) {
			return;
		}

		if( empty( $indexable_post_types ) ) {
			return;
		}

		while ( true ) {
			// query to find posts under edited term
			$query = new WP_Query(array(
				'posts_per_page' => $posts_per_page,
				'offset' => $offset,
				'post_status' => $indexable_post_statuses,
				'post_type' => $indexable_post_types,
				'tax_query' => array(
					array(
						'taxonomy' => $taxonomy,
						'field' => 'id',
						'terms' => $term_id,
						)
					)
				)
			);

			if( $query->have_posts() ) {
				while ( $query->have_posts() ) {
					$query->the_post();
					$post_id = absint( get_the_ID() );

					// prepare data for bulk index
					$posts_to_sync[$post_id][] = '{ "index": { "_id": "' . $post_id . '" } }';
					$posts_to_sync[$post_id][] = addcslashes( json_encode( ep_prepare_post( $post_id ) ), "\n" );
				}
			} else {
				break;
			}

			// prepare body for bul index
			$flatten = array();
			foreach ( $posts_to_sync as $post ) {
				$flatten[] = $post[0];
				$flatten[] = $post[1];
			}

			$body = rtrim( implode( "\n", $flatten ) ) . "\n";

			// perform bulk sync
			ep_bulk_index_posts( $body );

			usleep( 500 );

			// free up memory
			$wpdb->queries = array();

			if ( is_object( $wp_object_cache ) ) {
				$wp_object_cache->group_ops = array();
				$wp_object_cache->stats = array();
				$wp_object_cache->memcache_debug = array();
				$wp_object_cache->cache = array();

				if ( is_callable( $wp_object_cache, '__remoteset' ) ) {
					call_user_func( array( $wp_object_cache, '__remoteset' ) ); // important
				}
			}

			// go to next page
			$offset += $posts_per_page;
		}

		wp_reset_postdata();
	}

	/**
	 * Delete ES post when WP post is deleted
	 *
	 * @param int $post_id
	 * @since 0.1.0
	 */
	public function action_delete_post( $post_id ) {

		if ( ! current_user_can( 'edit_post', $post_id ) || 'revision' === get_post_type( $post_id ) ) {
			return;
		}

		do_action( 'ep_delete_post', $post_id );

		ep_delete_post( $post_id );
	}

	/**
	 * Sync ES index with what happened to the post being saved
	 *
	 * @param string $new_status
	 * @param string $old_status
	 * @param object $post
	 * @since 0.1.0
	 */
	public function action_sync_on_transition( $new_status, $old_status, $post ) {
		global $importer;

		// If we have an importer we must be doing an import - let's abort
		if ( ! empty( $importer ) ) {
			return;
		}

		$indexable_post_statuses = ep_get_indexable_post_status();

		if ( ! in_array( $new_status, $indexable_post_statuses ) && ! in_array( $old_status, $indexable_post_statuses ) ) {
			return;
		}

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post->ID ) || 'revision' === get_post_type( $post->ID ) ) {
			return;
		}

		// Our post was published, but is no longer, so let's remove it from the Elasticsearch index
		if ( ! in_array( $new_status, $indexable_post_statuses ) ) {
			$this->action_delete_post( $post->ID );
		} else {
			$post_type = get_post_type( $post->ID );

			$indexable_post_types = ep_get_indexable_post_types();

			if ( in_array( $post_type, $indexable_post_types ) ) {

				do_action( 'ep_sync_on_transition', $post->ID );

				$this->sync_post( $post->ID );
			}
		}
	}

	/**
	 * Return a singleton instance of the current class
	 *
	 * @since 0.1.0
	 * @return EP_Sync_Manager
	 */
	public static function factory() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
			$instance->setup();
		}

		return $instance;
	}

	/**
	 * Sync a post for a specific site or globally.
	 *
	 * @param int $post_id
	 * @since 0.1.0
	 * @return bool|array
	 */
	public function sync_post( $post_id ) {

		$post_args = ep_prepare_post( $post_id );

		if ( apply_filters( 'ep_post_sync_kill', false, $post_args, $post_id ) ) {
			return;
		}

		$response = ep_index_post( $post_args );

		return $response;
	}
}

$ep_sync_manager = EP_Sync_Manager::factory();

/**
 * Accessor functions for methods in above class. See doc blocks above for function details.
 */

function ep_sync_post( $post_id ) {
	return EP_Sync_Manager::factory()->sync_post( $post_id );
}