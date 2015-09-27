<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // exit if accessed directly
}

class EP_User_API {

	/** @var EP_API */
	protected $api;

	/**
	 * Factory method to get singleton
	 *
	 * @return EP_User_API
	 */
	public static function factory() {
		static $instance;
		if ( ! $instance ) {
			$instance = new self();
			$instance->setup();
		}

		return $instance;
	}

	/**
	 * Set up object
	 *
	 * @param EP_API $api
	 */
	public function __construct( $api = null ) {
		if ( ! $api instanceof EP_API ) {
			$api = EP_API::factory();
		}
		$this->api = $api;
	}

	/**
	 * Set this object up
	 *
	 * Actions and filters should get set up here
	 */
	public function setup() {
		if ( ! $this->active() ) {
			return;
		}
		add_filter( 'ep_config_mapping', array( $this, 'add_user_to_mapping' ), 5 );
	}

	/**
	 * Check if user search is active
	 *
	 * User search defaults to being inactive. This cannot be active if the main plugin isn't.
	 *
	 * @return bool
	 */
	public function active() {
		if ( ! defined( 'EP_USER_SEARCH_ACTIVE' ) ) {
			$active = apply_filters( 'ep_user_search_is_active', false );
			define( 'EP_USER_SEARCH_ACTIVE', $active );
		}

		return $this->api->is_activated() && (bool) EP_USER_SEARCH_ACTIVE;
	}

	/**
	 * Add users to the mapping array
	 *
	 * @param array $mapping
	 *
	 * @return array
	 */
	public function add_user_to_mapping( $mapping ) {
		$user_mapping_file = apply_filters(
			'ep_config_user_mapping_file',
			dirname( __FILE__ ) . '/../includes/user-mappings.php'
		);
		$user_mapping      = require( $user_mapping_file );
		if ( $user_mapping ) {
			$mapping = array_merge_recursive( $mapping, $user_mapping );
		}

		return $mapping;
	}

	/**
	 * Index a user
	 *
	 * Returns the response body on success, false on failure
	 *
	 * @param array $user The user data
	 *
	 * @return bool|array
	 */
	public function index_user( $user ) {
	}

	/**
	 * Delete a user from the index
	 *
	 * Returns a boolean to indicate success of deletion
	 *
	 * @param int $user
	 *
	 * @return bool
	 */
	public function delete_user( $user ) {
	}

	/**
	 * Get a user's indexed data
	 *
	 * Returns false if the data cannot be retrieved. Otherwise this function returns the data indexed in elasticsearch.
	 *
	 * @param int $user
	 *
	 * @return bool|array
	 */
	public function get_user( $user ) {
	}

	/**
	 * Prepare user data for indexing
	 *
	 * @param int $user
	 *
	 * @return array
	 */
	public function prepare( $user ) {
		$user = $this->get_wp_user( $user );

		if ( ! $user->exists() ) {
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
			'user_meta'       => $this->prepare_meta( $user ),
		);

		return $data;
	}

	/**
	 * Bulk index some users
	 *
	 * @param array|object $body
	 *
	 * @return array|object|WP_Error
	 */
	public function bulk_index( $body ) {
	}

	/**
	 * Prepare terms for indexing
	 *
	 * @param WP_User $user
	 *
	 * @return array
	 */
	protected function prepare_terms( $user ) {
	}

	/**
	 * Prepare user meta for indexing
	 *
	 * @param WP_User $user
	 *
	 * @return array
	 */
	protected function prepare_meta( $user ) {
	}

	/**
	 * Get a user based on input
	 *
	 * @param WP_User|int|string|array $user
	 * @param string|null              $by
	 *
	 * @return WP_User
	 */
	private function get_wp_user( $user, $by = null ) {
		if ( is_numeric( $user ) ) {
			$user = get_user_by( 'id', (int) $user );
		} elseif ( $by ) {
			$user = get_user_by( $by, $user );
		} elseif ( is_array( $user ) && ( isset( $user['user_id'] ) || isset( $user['ID'] ) ) ) {
			$user = get_user_by( 'id', isset( $user['user_id'] ) ? $user['user_id'] : $user['ID'] );
		}
		if ( ! ( $user instanceof WP_User ) ) {
			$user = new WP_User( 0 );
		}

		return $user;
	}

}

add_action( 'plugins_loaded', array( 'EP_User_API', 'factory' ) );
