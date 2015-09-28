<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // exit if accessed directly
}

class EP_Post_Index extends EP_Abstract_Object_Index {

	/**
	 * Get the settings needed by this type's mapping
	 *
	 * @return array
	 */
	public function get_settings() {
		$mapping = require( $this->get_mapping_file() );

		return isset( $mapping['settings'] ) ? (array) $mapping['settings'] : array();
	}

	/**
	 * Get the mapping for this type
	 *
	 * @return array
	 */
	public function get_mappings() {
		$mapping = require( $this->get_mapping_file() );

		return isset( $mapping['mappings']['post'] ) ? (array) $mapping['mappings']['post'] : array();
	}

	/**
	 * Prepare the object for indexing
	 *
	 * @param mixed $object
	 *
	 * @return array
	 */
	public function prepare_object( $object ) {
		$post_id = $this->get_object_identifier( $object );
		$post    = get_post( $post_id );

		$user = get_userdata( $post->post_author );

		if ( $user instanceof WP_User ) {
			$user_data = array(
				'raw'          => $user->user_login,
				'login'        => $user->user_login,
				'display_name' => $user->display_name,
				'id'           => $user->ID,
			);
		} else {
			$user_data = array(
				'raw'          => '',
				'login'        => '',
				'display_name' => '',
				'id'           => '',
			);
		}

		$post_date         = $post->post_date;
		$post_date_gmt     = $post->post_date_gmt;
		$post_modified     = $post->post_modified;
		$post_modified_gmt = $post->post_modified_gmt;
		$comment_count     = absint( $post->comment_count );
		$comment_status    = absint( $post->comment_status );
		$ping_status       = absint( $post->ping_status );
		$menu_order        = absint( $post->menu_order );

		if ( apply_filters( 'ep_ignore_invalid_dates', true, $post_id, $post ) ) {
			if ( ! strtotime( $post_date ) || $post_date === "0000-00-00 00:00:00" ) {
				$post_date = null;
			}

			if ( ! strtotime( $post_date_gmt ) || $post_date_gmt === "0000-00-00 00:00:00" ) {
				$post_date_gmt = null;
			}

			if ( ! strtotime( $post_modified ) || $post_modified === "0000-00-00 00:00:00" ) {
				$post_modified = null;
			}

			if ( ! strtotime( $post_modified_gmt ) || $post_modified_gmt === "0000-00-00 00:00:00" ) {
				$post_modified_gmt = null;
			}
		}

		$post_args = array(
			'post_id'           => $post_id,
			'post_author'       => $user_data,
			'post_date'         => $post_date,
			'post_date_gmt'     => $post_date_gmt,
			'post_title'        => get_the_title( $post_id ),
			'post_excerpt'      => $post->post_excerpt,
			'post_content'      => apply_filters( 'the_content', $post->post_content ),
			'post_status'       => $post->post_status,
			'post_name'         => $post->post_name,
			'post_modified'     => $post_modified,
			'post_modified_gmt' => $post_modified_gmt,
			'post_parent'       => $post->post_parent,
			'post_type'         => $post->post_type,
			'post_mime_type'    => $post->post_mime_type,
			'permalink'         => get_permalink( $post_id ),
			'terms'             => $this->prepare_terms( $post ),
			'post_meta'         => $this->prepare_meta( $post ),
			'date_terms'        => $this->prepare_date_terms( $post_date ),
			'comment_count'     => $comment_count,
			'comment_status'    => $comment_status,
			'ping_status'       => $ping_status,
			'menu_order'        => $menu_order,
			'guid'              => $post->guid
		);

		/**
		 * This filter is named poorly but has to stay to keep backwards compat
		 */
		$post_args = apply_filters( 'ep_post_sync_args', $post_args, $post_id );

		return $post_args;
	}

	/**
	 * Get the primary identifier for an object
	 *
	 * This could be a slug, or an ID, or something else. It will be used as a canonical
	 * lookup for the document.
	 *
	 * @param mixed $object
	 *
	 * @return int|string
	 */
	protected function get_object_identifier( $object ) {
		if ( $object instanceof WP_Post ) {
			return $object->ID;
		} elseif ( is_array( $object ) && isset( $object['post_id'] ) ) {
			return $object['post_id'];
		} elseif ( is_numeric( $object ) ) {
			return (int) $object;
		}

		return 0;
	}

	protected function process_found_objects( $hits ) {
		// TODO: Implement process_found_objects() method.
	}

	private function get_mapping_file() {
		return apply_filters( 'ep_config_mapping_file', dirname( __FILE__ ) . '/../includes/mappings.php' );
	}

}
