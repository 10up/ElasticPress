<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // exit if accessed directly
}

/**
 * Class EP_Post_Index
 *
 * @since 1.7
 */
class EP_Post_Index extends EP_Abstract_Object_Index {

	protected $name = 'post';

	/**
	 * EP_Post_Index constructor.
	 *
	 * @since 1.7
	 */
	public function __construct() {
		parent::__construct( $this->name );
	}

	/**
	 * For purposes of backwards compatibility, the methods for updating and deleting posts will stay on the sync
	 * manager.
	 *
	 * {@inheritdoc}
	 */
	public function sync_setup() {
		$sync_manager = EP_Sync_Manager::factory();
		add_action( 'wp_insert_post', array( $sync_manager, 'action_sync_on_update' ), 999, 3 );
		add_action( 'add_attachment', array( $sync_manager, 'action_sync_on_update' ), 999, 3 );
		add_action( 'edit_attachment', array( $sync_manager, 'action_sync_on_update' ), 999, 3 );
		add_action( 'delete_post', array( $sync_manager, 'action_delete_post' ) );
	}

	/**
	 * For purposes of backwards compatibility, the methods for updating and deleting posts will stay on the sync
	 * manager.
	 *
	 * {@inheritdoc}
	 */
	public function sync_teardown() {
		$sync_manager = EP_Sync_Manager::factory();
		remove_action( 'wp_insert_post', array( $sync_manager, 'action_sync_on_update' ), 999, 3 );
		remove_action( 'add_attachment', array( $sync_manager, 'action_sync_on_update' ), 999, 3 );
		remove_action( 'edit_attachment', array( $sync_manager, 'action_sync_on_update' ), 999, 3 );
		remove_action( 'delete_post', array( $sync_manager, 'action_delete_post' ) );
	}


	/**
	 * {@inheritdoc}
	 */
	public function get_settings() {
		$mapping = require( $this->get_mapping_file() );

		return isset( $mapping['settings'] ) ? (array) $mapping['settings'] : array();
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_mappings() {
		$mapping = require( $this->get_mapping_file() );

		return isset( $mapping['mappings']['post'] ) ? (array) $mapping['mappings']['post'] : array();
	}

	/**
	 * {@inheritdoc}
	 */
	public function search( $args, $scope = 'current' ) {
		$results = parent::search( $args, $scope );

		return array(
			'found_posts' => $results['found_objects'],
			'posts'       => $results['objects'],
		);
	}

	/**
	 * Prepare the object for indexing
	 *
	 * @since 1.7
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
	 * {@inheritdoc}
	 */
	protected function get_object_taxonomies( $object ) {
		return get_object_taxonomies( $object->post_type, 'objects' );
	}

	/**
	 * {@inheritdoc}
	 */
	protected function get_object_meta( $object ) {
		$post_meta = get_post_meta( $object->ID );
		foreach ( $post_meta as $key => $value ) {
			if ( is_array( $value ) ) {
				// Unserialize meta values
				$value = array_map( 'maybe_unserialize', $value );
				// If this is a single meta value, pop it out of the array
				if ( 1 === count( $value ) && isset( $value[0] ) ) {
					$value = $value[0];
				}
				$post_meta[ $key ] = $value;
			}
		}

		return $post_meta;
	}

	/**
	 * Get the primary identifier for an object
	 *
	 * This could be a slug, or an ID, or something else. It will be used as a canonical
	 * lookup for the document.
	 *
	 * @since 1.7
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

	/**
	 * Get the mapping file to get our config values from
	 *
	 * @since 1.7
	 *
	 * @return mixed|void
	 */
	private function get_mapping_file() {
		return apply_filters( 'ep_config_mapping_file', dirname( __FILE__ ) . '/../includes/mappings.php' );
	}

	/**
	 * Prepare the post date terms
	 *
	 * @since 1.7
	 *
	 * @param string $date
	 *
	 * @return array
	 */
	private function prepare_date_terms( $date ) {
		$timestamp  = strtotime( $date );
		$date_terms = array(
			'year'          => (int) date( "Y", $timestamp ),
			'month'         => (int) date( "m", $timestamp ),
			'week'          => (int) date( "W", $timestamp ),
			'dayofyear'     => (int) date( "z", $timestamp ),
			'day'           => (int) date( "d", $timestamp ),
			'dayofweek'     => (int) date( "w", $timestamp ),
			'dayofweek_iso' => (int) date( "N", $timestamp ),
			'hour'          => (int) date( "H", $timestamp ),
			'minute'        => (int) date( "i", $timestamp ),
			'second'        => (int) date( "s", $timestamp ),
			'm'             => (int) ( date( "Y", $timestamp ) . date( "m", $timestamp ) ), // yearmonth
		);

		return $date_terms;
	}

}
