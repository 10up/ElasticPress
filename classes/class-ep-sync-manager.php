<?php

class EP_Sync_Manager {

	/**
	* Holds the posts that will be bulk synced.
	* @since 0.9
	*/
	private $posts = array();
	
	/**
	* Holds all of the posts that failed to index during a bulk index.
	* @since 0.9
	*/
	private $failed_posts = array();

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
		add_action( 'wp_trash_post', array( $this, 'action_trash_post' ) );
	}

	/**
	 * Delete ES post when WP post is deleted
	 *
	 * @param int $post_id
	 * @since 0.1.0
	 */
	public function action_trash_post( $post_id ) {

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
		if ( 'publish' !== $new_status ) {
			return;
		}

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post->ID ) || 'revision' === get_post_type( $post->ID ) ) {
			return;
		}

		$post_type = get_post_type( $post->ID );

		$indexable_post_types = ep_get_indexable_post_types();

		if ( in_array( $post_type, $indexable_post_types ) ) {

			do_action( 'ep_sync_on_transition', $post->ID );

			$this->sync_post( $post->ID );
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
	 * Prepare terms to send to ES.
	 *
	 * @param object $post
	 * @since 0.1.0
	 * @return array
	 */
	private function prepare_terms( $post ) {
		$taxonomies = get_object_taxonomies( $post->post_type, 'objects' );
		$selected_taxonomies = array();

		foreach ( $taxonomies as $taxonomy ) {
			if ( $taxonomy->public ) {
				$selected_taxonomies[] = $taxonomy;
			}
		}

		$selected_taxonomies = apply_filters( 'ep_sync_taxonomies', $selected_taxonomies, $post );

		if ( empty( $selected_taxonomies ) ) {
			return array();
		}

		$terms = array();

		foreach ( $selected_taxonomies as $taxonomy ) {
			$object_terms = get_the_terms( $post->ID, $taxonomy->name );

			if ( ! $object_terms || is_wp_error( $object_terms ) ) {
				continue;
			}

			foreach ( $object_terms as $term ) {
				$terms[$term->taxonomy][] = array(
					'term_id' => $term->term_id,
					'slug'    => $term->slug,
					'name'    => $term->name,
					'parent'  => $term->parent
				);
			}
		}

		return $terms;
	}

	/**
	 * Prepare post meta to send to ES
	 *
	 * @param object $post
	 * @since 0.1.0
	 * @return array
	 */
	public function prepare_meta( $post ) {
		$meta = (array) get_post_meta( $post->ID );

		if ( empty( $meta ) ) {
			return array();
		}

		$prepared_meta = array();

		foreach ( $meta as $key => $value ) {
			if ( ! is_protected_meta( $key ) ) {
				$prepared_meta[$key] = maybe_unserialize( $value );
			}
		}

		return $prepared_meta;
	}

	/**
	 * Sync a post for a specific site or globally.
	 *
	 * @param int $post_id
	 * @since 0.1.0
	 * @return bool|array
	 */
	public function sync_post( $post_id ) {

		$post_args = apply_filters( 'ep_post_sync_args', $this->prepare_post( $post_id ), $post_id );

		$response = ep_index_post( $post_args );

		return $response;
	}

	public function prepare_post( $post_id ) {
		$post = get_post( $post_id );

		$user = get_userdata( $post->post_author );

		if ( $user instanceof WP_User ) {
			$user_data = array(
				'login'        => $user->user_login,
				'display_name' => $user->display_name
			);
		} else {
			$user_data = array(
				'login'        => '',
				'display_name' => ''
			);
		}

		return array(
			'post_id'           => $post_id,
			'post_author'       => $user_data,
			'post_date'         => $post->post_date,
			'post_date_gmt'     => $post->post_date_gmt,
			'post_title'        => get_the_title( $post_id ),
			'post_excerpt'      => $post->post_excerpt,
			'post_content'      => apply_filters( 'the_content', $post->post_content ),
			'post_status'       => 'publish',
			'post_name'         => $post->post_name,
			'post_modified'     => $post->post_modified,
			'post_modified_gmt' => $post->post_modified_gmt,
			'post_parent'       => $post->post_parent,
			'post_type'         => $post->post_type,
			'post_mime_type'    => $post->post_mime_type,
			'permalink'         => get_permalink( $post_id ),
			'terms'             => $this->prepare_terms( $post ),
			'post_meta'         => $this->prepare_meta( $post ),
			//'site_id'         => get_current_blog_id(),
		);
	}

	public function queue_post( $post_id, $bulk_trigger ) {
		static $post_count = 0;

		// put the post into the queue
		$this->posts[$post_id][] = '{ "index": { "_id": "' . absint( $post_id ) . '" } }';
		$this->posts[$post_id][] = addcslashes( json_encode( $this->prepare_post( $post_id ) ) );

		// augment the counter
		++$post_count;

		// if we have hit the trigger, initiate the bulk request
		if ( $post_count === $bulk_trigger ) {
			$this->bulk_index();

			// reset the post count
			$post_count = 0;

			// reset the posts
			$this->posts = array();
		}
		return true;
	}

	public function bulk_index() {
		// monitor how many times we attempt to add this particular bulk request
		static $attempts = 0;

		// augment the attempts
		++$attempts;

		// make sure we actually have something to index
		if ( empty( $this->posts ) ) {
			WP_CLI::error( 'There are no posts to index.' );
		}

		// flatten out the array and implode everything to form the request
		$flatten = new RecursiveIteratorIterator( new RecursiveArrayIterator( $this->posts ) );
		$flatten = iterator_to_array( $flatten );

		// make sure to add a new line at the end or the request will fail
		$body    = rtrim( implode( "\n", $flatten ) ) . "\n";

		// show the content length in bytes if in debug
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			WP_CLI::line( WP_CLI::colorize( '%MRequest size:%N' ) . ' ' . mb_strlen( $body, '8bit' ) );
		}

		// create the url with index name and type so that we don't have to repeat it over and over in the request (thereby reducing the request size)
		$url     = trailingslashit( EP_HOST ) . trailingslashit( ep_get_index_name() ) . 'post/_bulk';
		$request = wp_remote_request( $url, array( 'method' => 'POST', 'body' => $body ) );

		// kill it on an error and show the message readout
		if ( is_wp_error( $request ) ) {
			WP_CLI::error( implode( "\n", $request->get_error_messages() ) );
		}

		// decode the response
		$response = json_decode( wp_remote_retrieve_body( $request ), true );

		// if we did have errors, try to add the documents again
		if ( isset( $response['errors'] ) && $response['errors'] === true ) {
			if ( $attempts < 5 ) {
				foreach ( $response['items'] as $item ) {
					if ( empty( $item['index']['error'] ) ) {
						unset( $this->posts[ $item['index']['_id'] ] );
					}
				}
				$this->bulk_index();
			} else {
				foreach ( $response['items'] as $items ) {
					if ( !empty( $item['index']['_id'] ) ) {
						$this->failed_posts[] = $item['index']['_id'];
					}
				}
				$attempts = 0;
			}
		} else {
			// there were no errors, all the posts were added
			$attempts = 0;
		}
	}
}

$ep_sync_manager = EP_Sync_Manager::factory();

/**
 * Accessor functions for methods in above class. See doc blocks above for function details.
 */

function ep_sync_post( $post_id ) {
	return EP_Sync_Manager::factory()->sync_post( $post_id );
}

function ep_queue_bulk_sync( $post_id ) {
	return EP_Sync_Manager::factory()->queue_post( $post_id );
}

function ep_bulk_index() {
	return EP_Sync_Manager::factory()->bulk_index();
}