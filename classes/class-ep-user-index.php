<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // exit if accessed directly
}

/**
 * Class EP_User_Index
 *
 * @since 1.7.0
 */
class EP_User_Index extends EP_Abstract_Object_Index {

	/**
	 * {@inheritdoc}
	 */
	protected $name = 'user';

	/**
	 * Constructor for EP_User_Index
	 *
	 * @since 1.7.0
	 */
	public function __construct() {
		parent::__construct( $this->name );
	}

	/**
	 * Check whether user indexing is active. Indexing users is off by default
	 *
	 * @since 1.7.0
	 *
	 * @return bool
	 */
	public function active() {
		return (
			( ep_is_activated() || ( defined( 'WP_CLI' ) && WP_CLI ) ) &&
			apply_filters( 'ep_user_indexing_active', false )
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function sync_setup() {
		add_action( 'profile_update', array( $this, 'action_update_on_sync' ), 999999, 3 );
		add_action( 'user_register', array( $this, 'action_update_on_sync' ), 999999, 3 );
		add_action( 'add_user_to_blog', array( $this, 'action_add_user_to_blog' ), 999999, 3 );
		add_action( 'delete_user', array( $this, 'action_delete_user_from_site' ) );
		add_action( 'remove_user_from_blog', array( $this, 'action_delete_user_from_site' ), 10, 2 );
	}

	/**
	 * {@inheritdoc}
	 */
	public function sync_teardown() {
		remove_action( 'profile_update', array( $this, 'action_update_on_sync' ), 999999, 3 );
		remove_action( 'user_register', array( $this, 'action_update_on_sync' ), 999999, 3 );
		remove_action( 'add_user_to_blog', array( $this, 'action_add_user_to_blog' ), 999999, 3 );
		remove_action( 'delete_user', array( $this, 'action_delete_user_from_site' ) );
		remove_action( 'remove_user_from_blog', array( $this, 'action_delete_user_from_site' ), 10, 2 );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_settings() {
		$user_mapping = require( $this->get_mapping_file() );

		return isset( $user_mapping['settings'] ) ? (array) $user_mapping['settings'] : array();
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_mappings() {
		$user_mapping = require( $this->get_mapping_file() );

		return isset( $user_mapping['mappings']['user'] ) ? (array) $user_mapping['mappings']['user'] : array();
	}

	/**
	 * {@inheritdoc}
	 */
	public function prepare_object( $object ) {
		$user = $this->get_wp_user( $object );

		if ( ! $user || ! $user->exists() ) {
			return array();
		}

		$user_registered = $user->user_registered;
		if ( apply_filters( 'ep_ignore_invalid_dates', true, $user->ID, $user ) ) {
			if ( ! strtotime( $user_registered ) || $user_registered === '0000-00-00 00:00:00' ) {
				$user_registered = null;
			}
		}

		$data = array(
			'user_id'         => absint( $user->ID ),
			'user_login'      => $user->user_login,
			'user_nicename'   => $user->user_nicename,
			'nickname'        => $user->nickname,
			'user_email'      => $user->user_email,
			'description'     => $user->description,
			'first_name'      => $user->first_name,
			'last_name'       => $user->last_name,
			'user_url'        => $user->user_url,
			'display_name'    => $user->display_name,
			'user_registered' => $user_registered,
			'role'            => $user->roles,
			'terms'           => $this->prepare_terms( $user ),
			'meta'            => $this->api->prepare_meta_types( $this->prepare_meta( $user ) ),
		);

		return $data;
	}

	/**
	 * Update a user's data
	 *
	 * For multisite, this will update a user's data on all sites to which the user officially belongs.
	 *
	 * @param $user_id
	 */
	public function action_update_on_sync( $user_id ) {
		$user     = $this->get_wp_user( $user_id );
		$userdata = $this->prepare_object( $user );
		if ( ! $userdata ) {
			return;
		}
		if ( is_multisite() ) {
			$blogs = get_blogs_of_user( $user->ID );
			foreach ( $blogs as $blog ) {
				switch_to_blog( $blog->userblog_id );
				$this->index_document( $userdata );
				restore_current_blog();
			}
		} else {
			$this->index_document( $userdata );
		}
	}

	/**
	 * Add a user to the blog
	 *
	 * This should only run on multisite
	 *
	 * @param $user_id
	 * @param $role
	 * @param $blog_id
	 */
	public function action_add_user_to_blog( $user_id, $role, $blog_id ) {
		if ( ! is_multisite() ) {
			return;
		}
		$userdata = $this->prepare_object( $this->get_wp_user( $user_id ) );
		if ( empty( $userdata ) ) {
			return;
		}
		switch_to_blog( $blog_id );
		$this->index_document( $userdata );
		restore_current_blog();
	}

	/**
	 * Delete a user from the current site index
	 *
	 * In single site mode, this runs on delete_user and will simply delete the document from the one index in
	 * elasticsearch. In a multisite installation, this will check if the current filter is notremove_user_from_blog and
	 * will return if so. In multisite context, the remove_user_from_blog is the proper context in which to delete a
	 * user document, since we only want to get rid of user documents from the site affected.
	 *
	 * @param $user_id
	 */
	public function action_delete_user_from_site( $user_id, $site_id = null ) {
		if ( is_multisite() && 'remove_user_from_blog' !== current_filter() ) {
			return;
		}
		if ( is_multisite() ) {
			$site_id = (int) $site_id ? (int) $site_id : get_current_blog_id();
			switch_to_blog( $site_id );
		}
		$this->delete_document( $user_id );
		if ( is_multisite() ) {
			restore_current_blog();
		}
	}

	/**
	 * {@inheritdoc}
	 */
	protected function get_object_taxonomies( $object ) {
		$taxonomies = array_filter( array_map(
			array( $this, 'filter_user_taxonomies' ),
			apply_filters( 'ep_user_taxonomies', array(), $object )
		) );

		return $taxonomies;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function get_object_meta( $object ) {
		$user = $this->get_wp_user( $object );
		if ( ! $user || ! $user->exists() ) {
			return array();
		}
		$meta      = get_user_meta( $user->ID );
		$real_meta = array();
		/**
		 * Allow Plugins to filter the user meta key blacklist
		 *
		 * @since 1.7.0
		 *
		 * @param array   $meta The list of meta keys to omit
		 * @param WP_User $user The user being indexed
		 */
		$blacklist = apply_filters( 'ep_user_meta_key_blacklist', array(
			'first_name',
			'last_name',
			'nickname',
			'description',
			'rich_editing',
			'comment_shortcuts',
			'admin_color',
			'use_ssl',
			'show_admin_bar_front',
			'dismissed_wp_pointers',
			'default_password_nag',
			'session_tokens',
			'show_welcome_panel',
		), $user );
		/**
		 * Allow plugins to filter the list of prefixed meta keys to omit
		 *
		 * @since 1.7.0
		 *
		 * @param array   $keys The meta keys prefixed by the blog id to omit
		 * @param WP_User $user The user being indexed
		 */
		$prefixed_blacklist = apply_filters( 'ep_user_meta_prefixed_key_blacklist', array(
			'capabilities',
			'user_level',
			'dashboard_quick_press_last_post_id',
			'user-settings',
			'user-settings-time',
		), $user );
		$prefixed_blacklist = array_map(
			'preg_quote',
			$prefixed_blacklist,
			array_fill( 0, count( $prefixed_blacklist ), '/' )
		);
		foreach ( $meta as $key => $value ) {
			if (
				in_array( $key, $blacklist ) ||
				(
					! empty( $prefixed_blacklist ) &&
					preg_match( '/(' . implode( '|', $prefixed_blacklist ) . ')$/', $key )
				)
			) {
				continue;
			}
			if ( is_array( $value ) ) {
				// Serialize non-scalar meta values
				$value = array_map( 'maybe_serialize', $value );
				// If this is a single meta value, pop it out of the array
				if ( 1 === count( $value ) && isset( $value[0] ) ) {
					$value = $value[0];
				}
			}
			$real_meta[ $key ] = $value;
		}

		/**
		 * Allow plugins and themes to filter the user meta that should or should not be included in the document
		 *
		 * @since 1.7.0
		 *
		 * @param array   $real_meta The meta to index
		 * @param WP_User $user      The user being indexed
		 */

		return apply_filters( 'ep_user_get_meta_values', $real_meta, $user );
	}

	/**
	 * {@inheritdoc}
	 */
	protected function get_object_identifier( $object ) {
		if ( $object instanceof WP_User ) {
			return (int) $object->ID;
		}
		if ( is_array( $object ) && ! empty( $object['user_id'] ) ) {
			return (int) $object['user_id'];
		}

		return is_numeric( $object ) ? (int) $object : 0;
	}

	/**
	 * Get a taxonomy object from either a string or object
	 *
	 * Returns the taxonomy object on success, false on failure
	 *
	 * @since 1.7.0
	 *
	 * @param string|object $taxonomy
	 *
	 * @return bool|object The taxonomy object on success, false on failure
	 */
	private function filter_user_taxonomies( $taxonomy ) {
		if ( is_string( $taxonomy ) ) {
			$taxonomy = get_taxonomy( $taxonomy );
		} elseif ( is_object( $taxonomy ) && isset( $taxonomy->name ) ) {
			$taxonomy = get_taxonomy( $taxonomy->name );
		}
		if ( $taxonomy && ! $taxonomy->public ) {
			$taxonomy = false;
		}

		return $taxonomy;
	}

	/**
	 * Get the location of the user mapping data
	 *
	 * @since 1.7.0
	 *
	 * @return string
	 */
	private function get_mapping_file() {
		$user_mapping_file = apply_filters(
			'ep_config_user_mapping_file',
			dirname( __FILE__ ) . '/../includes/user-mappings.php'
		);

		return $user_mapping_file;
	}

	/**
	 * Get a WordPress user object
	 *
	 * @since 1.7.0
	 *
	 * @param mixed $object
	 *
	 * @return false|WP_User
	 */
	private function get_wp_user( $object ) {
		return get_user_by( 'id', $this->get_object_identifier( $object ) );
	}

}
