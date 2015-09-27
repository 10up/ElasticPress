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
		/*
		 * WordPress doesn't natively support user taxonomies, so there's no internal way to get a user's taxonomies.
		 * For this reason, we're going to allow implementations of any actual user taxonomy add the taxonomy itself
		 * here. Then we'll allow that same implementation code to prevent a standard term lookup against the user
		 * object by supplying its own terms.
		 */
		$user = $this->get_wp_user( $user );
		if ( ! $user->exists() ) {
			return array();
		}

		$taxonomies = array_filter( array_map(
			array( $this, 'filter_user_taxonomies' ),
			apply_filters( 'ep_user_taxonomies', array(), $user )
		) );
		if ( ! $taxonomies ) {
			return array();
		}

		$terms = array();

		$allow_hierarchy = apply_filters( 'ep_sync_terms_allow_hierarchy', false );

		foreach ( $taxonomies as $taxonomy ) {
			$tax_terms = apply_filters( "ep_user_taxonomy_{$taxonomy->name}_terms", null, $user, $taxonomy );
			if ( ! $tax_terms ) {
				$tax_terms = wp_get_object_terms( $user->ID, $taxonomy->name );
			}
			if ( ! $tax_terms || is_wp_error( $tax_terms ) ) {
				continue;
			}
			$terms_map = array();
			foreach ( $tax_terms as $term ) {
				if ( ! isset( $terms_map[ $term->term_id ] ) ) {
					$terms_map[ $term->term_id ] = array(
						'term_id' => $term->term_id,
						'slug'    => $term->slug,
						'name'    => $term->name,
						'parent'  => $term->parent,
					);
					if ( $allow_hierarchy ) {
						$terms_map = $this->get_parent_terms( $terms_map, $term, $taxonomy->name );
					}
				}
			}
			$terms[ $taxonomy->name ] = array_values( $terms_map );
		}

		return $terms;
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
	 * Recursively get all the ancestor terms of the given term
	 * @param $terms
	 * @param $term
	 * @param $tax_name
	 * @return array
	 */
	private function get_parent_terms( $terms, $term, $tax_name ) {
		$parent_term = get_term( $term->parent, $tax_name );
		if( ! $parent_term || is_wp_error( $parent_term ) )
			return $terms;
		if( ! isset( $terms[ $parent_term->term_id ] ) ) {
			$terms[ $parent_term->term_id ] = array(
				'term_id' => $parent_term->term_id,
				'slug'    => $parent_term->slug,
				'name'    => $parent_term->name,
				'parent'  => $parent_term->parent
			);
		}
		return $this->get_parent_terms( $terms, $parent_term, $tax_name );
	}

	/**
	 * Get a taxonomy object from either a string or object
	 *
	 * Returns the taxonomy object on success, false on failure
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
