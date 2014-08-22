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

		$post = get_post( $post_id );

		$user = get_userdata( $post->post_author );

		if ( $user instanceof WP_User ) {
			$user_data = array(
				'login' => $user->user_login,
				'display_name' => $user->display_name
			);
		} else {
			$user_data = array(
				'login' => '',
				'display_name' => ''
			);
		}

		$post_args = array(
			'post_id' => $post_id,
			'post_author' => $user_data,
			'post_date' => $post->post_date,
			'post_date_gmt' => $post->post_date_gmt,
			'post_title' => get_the_title( $post_id ),
			'post_excerpt' => $post->post_excerpt,
			'post_content' => apply_filters( 'the_content', $post->post_content ),
			'post_status' => 'publish',
			'post_name' => $post->post_name,
			'post_modified' => $post->post_modified,
			'post_modified_gmt' => $post->post_modified_gmt,
			'post_parent' => $post->post_parent,
			'post_type' => $post->post_type,
			'post_mime_type' => $post->post_mime_type,
			'permalink' => get_permalink( $post_id ),
			'terms' => $this->prepare_terms( $post ),
			'post_meta' => $this->prepare_meta( $post ),
			//'site_id' => $site_id,
		);

		$post_args = apply_filters( 'ep_post_sync_args', $post_args, $post_id );

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