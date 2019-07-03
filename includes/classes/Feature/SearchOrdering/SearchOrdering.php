<?php
/**
 * Search Ordering Feature
 *
 * @package elasticpress
 */

namespace ElasticPress\Feature\SearchOrdering;

use ElasticPress\Feature;
use ElasticPress\FeatureRequirementsStatus as FeatureRequirementsStatus;
use ElasticPress\Features;
use ElasticPress\Indexable\Post\Post;
use ElasticPress\Indexables;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Search Ordering Feature
 *
 * @package ElasticPress\Feature\SearchOrdering
 */
class SearchOrdering extends Feature {

	/**
	 * Internal name of the post type
	 */
	const POST_TYPE_NAME = 'ep-pointer';

	/**
	 * Internal name of the taxonomy
	 */
	const TAXONOMY_NAME = 'ep_custom_result';

	/**
	 * Initialize feature setting it's config
	 *
	 * @since  3.0
	 */
	public function __construct() {
		$this->slug = 'searchordering';

		$this->title = esc_html__( 'Custom Search Results', 'elasticpress' );

		$this->requires_install_reindex = false;
		$this->default_settings         = [];

		parent::__construct();
	}

	/**
	 * Setup Feature Functionality
	 */
	public function setup() {
		/** @var Features $features */
		$features = Features::factory();

		/** @var Feature\Search\Search $search */
		$search = $features->get_registered_feature( 'search' );

		if ( ! $search->is_active() ) {
			$features->deactivate_feature( $this->slug );
			return;
		}

		add_action( 'admin_menu', [ $this, 'admin_menu' ], 50 );
		add_filter( 'parent_file', [ $this, 'parent_file' ], 50 );
		add_action( 'init', [ $this, 'register_post_type' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
		add_action( 'save_post_' . self::POST_TYPE_NAME, [ $this, 'save_post'], 10, 2 );
		add_action( 'posts_results', [ $this, 'posts_results' ], 20, 2 );  // Runs after core ES is done
		add_action( 'rest_api_init', [ $this, 'rest_api_init' ] );
		add_filter( 'ep_sync_taxonomies', [ $this, 'filter_sync_taxonomies' ] );
		add_filter( 'ep_weighting_configuration_for_search', [ $this, 'filter_weighting_configuration'], 10, 2 );
		add_filter( 'ep_weighting_configuration_for_autosuggest', [ $this, 'filter_weighting_configuration'], 10, 1 );

		// Deals with trashing/untrashing/deleting
		add_action( 'wp_trash_post', [ $this, 'handle_post_trash' ] );
		add_action( 'before_delete_post', [ $this, 'handle_post_trash' ] );
		add_action( 'untrashed_post', [ $this, 'handle_post_untrash' ] );
	}

	/**
	 * Returns requirements status of feature
	 *
	 * Requires the search feature to be activated
	 *
	 * @return FeatureRequirementsStatus
	 */
	public function requirements_status() {
		/** @var Features $features */
		$features = Features::factory();

		/** @var Feature\Search\Search $search */
		$search = $features->get_registered_feature( 'search' );

		if ( ! $search->is_active() ) {
			$features->deactivate_feature( $this->slug );
			return new FeatureRequirementsStatus( 2, __( 'This feature requires the "Post Search" feature to be enabled', 'katerra' ) );
		}

		return parent::requirements_status();
	}

	/**
	 * Output feature box summary
	 */
	public function output_feature_box_summary() {
		?>
		<p><?php esc_html_e( 'Insert specific posts into search results for specific search queries.', 'elasticpress' ); ?></p>
		<?php
	}

	/**
	 * Output feature box long
	 */
	public function output_feature_box_long() {
		?>
		<p><?php esc_html_e( 'Selected posts will be inserted into search results in the specified position.', 'elasticpress' ); ?></p>
		<?php
	}

	/**
	 * Adds this taxonomy as one of the taxonomies to index
	 *
	 * @param array $taxonomies Current indexable taxonomies
	 *
	 * @return array
	 */
	public function filter_sync_taxonomies( $taxonomies ) {
		$taxonomies[] = get_taxonomy( self::TAXONOMY_NAME );

		return $taxonomies;
	}

	/**
	 * Adds the search ordering to the admin menu
	 */
	public function admin_menu() {
		add_submenu_page( 'elasticpress', __( 'Custom Results', 'elasticpress' ), __( 'Custom Results', 'elasticpress' ), 'manage_options', 'edit.php?post_type=' . self::POST_TYPE_NAME );
	}

	/**
	 * Sets the parent menu item for the post type submenu
	 *
	 * @param string $parent_file Current parent menu item
	 *
	 * @return string
	 */
	public function parent_file( $parent_file ) {
		global $submenu_file, $current_screen, $parent_file;

		// Set correct active/current menu and submenu in the WordPress Admin menu for the "pointer" CPT Add-New/Edit/List
		if ( $current_screen->post_type === self::POST_TYPE_NAME ) {
			$submenu_file = 'edit.php?post_type=' . self::POST_TYPE_NAME;
			$parent_file  = 'elasticpress';
		}

		return $parent_file;
	}

	/**
	 * Registers the pointer post type for the injected results
	 */
	public function register_post_type() {
		$labels = array(
			'name'               => _x( 'Custom Search Results', 'post type general name', 'elasticpress' ),
			'singular_name'      => _x( 'Custom Search Result', 'post type singular name', 'elasticpress' ),
			'menu_name'          => _x( 'Custom Search Results', 'admin menu', 'elasticpress' ),
			'name_admin_bar'     => _x( 'Custom Search Result', 'add new on admin bar', 'elasticpress' ),
			'add_new'            => _x( 'Add New', 'book', 'elasticpress' ),
			'add_new_item'       => __( 'Add New Custom Search Result', 'elasticpress' ),
			'new_item'           => __( 'New Custom Search Result', 'elasticpress' ),
			'edit_item'          => __( 'Edit Custom Search Result', 'elasticpress' ),
			'view_item'          => __( 'View Custom Search Result', 'elasticpress' ),
			'all_items'          => __( 'All Custom Search Results', 'elasticpress' ),
			'search_items'       => __( 'Search Custom Search Results', 'elasticpress' ),
			'parent_item_colon'  => __( 'Parent Custom Search Result:', 'elasticpress' ),
			'not_found'          => __( 'No results found.', 'elasticpress' ),
			'not_found_in_trash' => __( 'No results found in Trash.', 'elasticpress' )
		);

		$args = array(
			'labels'               => $labels,
			'description'          => __( 'Posts to inject into search results', 'elasticpress' ),
			'public'               => false,
			'publicly_queryable'   => false,
			'show_ui'              => true,
			'show_in_menu'         => 'edit.php?post_type=' . self::POST_TYPE_NAME,
			'query_var'            => true,
			'rewrite'              => array( 'slug' => 'ep-pointer' ),
			'capability_type'      => 'post',
			'has_archive'          => false,
			'hierarchical'         => false,
			'menu_position'        => 5,
			'supports'             => [ 'title' ],
			'register_meta_box_cb' => [ $this, 'register_meta_box' ],
		);

		register_post_type( self::POST_TYPE_NAME, $args );


		// Register taxonomy
		$labels = array(
			'name'              => _x( 'Custom Results', 'taxonomy general name', 'elasticpress' ),
			'singular_name'     => _x( 'Custom Result', 'taxonomy singular name', 'elasticpress' ),
			'search_items'      => __( 'Search Custom Results', 'elasticpress' ),
			'all_items'         => __( 'All Custom Results', 'elasticpress' ),
			'parent_item'       => __( 'Parent Custom Result', 'elasticpress' ),
			'parent_item_colon' => __( 'Parent Custom Result:', 'elasticpress' ),
			'edit_item'         => __( 'Edit Custom Result', 'elasticpress' ),
			'update_item'       => __( 'Update Custom Result', 'elasticpress' ),
			'add_new_item'      => __( 'Add New Custom Result', 'elasticpress' ),
			'new_item_name'     => __( 'New Custom Result Name', 'elasticpress' ),
			'menu_name'         => __( 'Custom Results', 'elasticpress' ),
		);

		$args = array(
			'hierarchical'      => false,
			'labels'            => $labels,
			'show_ui'           => false,
			'show_admin_column' => false,
			'query_var'         => false,
			'rewrite'           => false,
		);

		/** @var Features $features */
		$features = Features::factory();

		/** @var Feature\Search\Search $search */
		$search = $features->get_registered_feature( 'search' );

		$post_types = $search->get_searchable_post_types();

		register_taxonomy( 'ep_custom_result', $post_types, $args );
	}

	/**
	 * Registers meta box for the search pointers
	 */
	public function register_meta_box() {
		add_meta_box( 'ep-ordering', __( "Manage Results", 'elasticpress' ), [ $this, 'render_meta_box' ], self::POST_TYPE_NAME, 'normal' );
	}

	/**
	 * Renders the meta box for the injected search results
	 *
	 * @param \WP_Post $post Current post object
	 */
	public function render_meta_box( $post ) {
		?>
		<div id="ordering-app"></div>
		<?php
	}

	/**
	 * Sends initial pointer data to the frontend to reduce API requests required
	 *
	 * @return array
	 */
	public function get_pointer_data_for_localize() {
		$post_id = get_the_ID();

		$pointers = get_post_meta( $post_id, 'pointers', true );

		if ( empty( $pointers ) ) {
			return [
				'pointers' => [],
				'posts'    => [],
			];
		}

		$post_ids = wp_list_pluck( $pointers, 'ID' );

		$query = new \WP_Query(
			[
				'post_type' => 'any',
				'post__in'  => $post_ids,
				'count'     => count( $post_ids ),
			]
		);

		$final_posts = [];

		foreach ( $query->posts as $post ) {
			$final_posts[ $post->ID ] = $post;
		}

		return [
			'pointers' => $pointers,
			'posts' => $final_posts,
		];
	}

	/**
	 * Enqueues scripts for admin interface
	 */
	public function admin_enqueue_scripts() {
		global $pagenow; // post-new.php or post.php

		$screen = get_current_screen();

		if ( in_array( $pagenow, [ 'post-new.php', 'post.php' ] ) && $screen instanceof \WP_Screen && $screen->post_type === self::POST_TYPE_NAME ) {
			wp_enqueue_script( 'ep_ordering_scripts', EP_URL . 'dist/js/ordering.min.js', [ 'jquery' ], EP_VERSION, true );
			wp_enqueue_style( 'ep_ordering_styles', EP_URL . 'dist/css/ordering.min.css', [], EP_VERSION );

			$pointer_data = $this->get_pointer_data_for_localize();

			wp_localize_script(
				'ep_ordering_scripts',
				'epOrdering',
				array_merge(
					[
						'searchEndpoint' => rest_url( 'elasticpress/v1/pointer_search' ),
						'nonce'          => wp_create_nonce( 'save-search-ordering' ),
						'restApiRoot'    => rest_url( '/' ),
						'postsPerPage'   => (int) get_option( 'posts_per_page', 10 ),
					],
					$pointer_data
				)
			);
		}
	}

	/**
	 * Handles saving the injected post settings
	 *
	 * @param $post_id
	 * @param $post
	 */
	public function save_post( $post_id, $post ) {
		/** @var Post $post_indexable */
		$post_indexable = Indexables::factory()->get( 'post' );

		if ( ! isset( $_POST['search-ordering-nonce'] ) || ! wp_verify_nonce( $_POST['search-ordering-nonce'], 'save-search-ordering' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post ) ) {
			return;
		}

		$final_order_data = [];

		// Track the old IDs that aren't retained so we can delete the terms later
		$previous_order_data = get_post_meta( $post_id, 'pointers', true );
		$previous_post_ids = array_flip( wp_list_pluck( $previous_order_data, 'ID' ) );

		$ordered_posts = json_decode( wp_unslash( $_POST['ordered_posts'] ), true );

		$posts_per_page = (int) get_option( 'posts_per_page', 10 );

		foreach ( $ordered_posts as $order_data ) {
			if ( intval( $order_data['order'] ) <= $posts_per_page ) {
				$final_order_data[] = [
					'ID'    => intval( $order_data['ID'] ),
					'order' => intval( $order_data['order'] ),
				];
			} else {
				$previous_post_ids[ intval( $order_data['ID'] ) ] = true;
			}

			// If the post is still assigned, no need to delete the terms later
			if ( isset( $previous_post_ids[ $order_data['ID'] ] ) ) {
				unset( $previous_post_ids[ $order_data['ID'] ] );
			}
		}

		$custom_result_term = $this->create_or_return_custom_result_term( $post->post_title );
		if ( $custom_result_term ) {
			foreach ( $final_order_data as $final_order_datum ) {
				$this->assign_term_to_post( $final_order_datum['ID'], $custom_result_term->term_taxonomy_id, $final_order_datum['order'] );

				$post_indexable->sync_manager->action_sync_on_update( $final_order_datum['ID'] );
			}
		}

		// Remove terms for any that were deleted
		if ( ! empty( $previous_post_ids ) ) {
			foreach ( array_flip( $previous_post_ids ) as $old_post_id ) {
				wp_remove_object_terms( $old_post_id, (int) $custom_result_term->term_id, self::TAXONOMY_NAME );

				$post_indexable->sync_manager->action_sync_on_update( $old_post_id );
			}
		}

		update_post_meta( $post_id, 'pointers', $final_order_data );
	}

	/**
	 * Creates a term in the taxonomy for tracking ordered results or returns the existing term
	 *
	 * @param string $term_name Term name to fetch or create
	 *
	 * @return false|\WP_Term
	 */
	public function create_or_return_custom_result_term( $term_name ) {
		$term = get_term_by( 'name', $term_name, self::TAXONOMY_NAME );

		if ( ! $term ) {
			$term_ids = wp_insert_term( $term_name, self::TAXONOMY_NAME );

			if ( is_wp_error( $term_ids ) ) {
				return false;
			}

			$term = get_term( $term_ids['term_id'], self::TAXONOMY_NAME );
		}

		return $term;
	}

	/**
	 * Filters the weighting configuration to insert our weighting config when we're searching
	 *
	 * @param array $weighting_configuration Current weighting configuration
	 * @param array $args                    WP Query Args
	 *
	 * @return array Final weighting configuration
	 */
	public function filter_weighting_configuration( $weighting_configuration, $args = array() ) {
		if ( ! isset( $args['exclude_pointers'] ) || true !== $args['exclude_pointers'] ) {
			foreach ( $weighting_configuration as $post_type => $config ) {
				$weighting_configuration[ $post_type ]['terms.ep_custom_result.name'] = [
					'enabled'   => true,
					'weight'    => 9999,
					'fuzziness' => false,
				];
			}
		}

		return $weighting_configuration;
	}

	/**
	 * Finds and pointer post types in the result set and replaces them with the posts to be injected in the proper positions
	 *
	 * @param array     $posts Current array of post results
	 * @param \WP_Query $query The current query
	 *
	 * @return array Final modified posts array
	 */
	public function posts_results( $posts, $query ) {
		if ( is_array( $posts ) && $query->is_search() ) {
			$search_query = strtolower( $query->get( 's' ) );

			$to_inject = array();

			foreach ( $posts as $key => &$post ) {
				if ( isset( $post->terms ) && isset( $post->terms[ self::TAXONOMY_NAME ] ) ) {
					foreach ( $post->terms[ self::TAXONOMY_NAME ] as $current_term ) {
						if ( strtolower( $current_term['name'] ) === $search_query ) {
							$to_inject[ $current_term['term_order'] ] = $post->ID;

							unset( $posts[ $key ] );

							break;
						}
					}
				}
			}

			// Remove the null values
			$posts = array_filter( $posts );

			// Sort by key so they get injected in order and remain in the proper positions
			ksort( $to_inject );

			if ( ! empty( $to_inject ) ) {
				foreach ( $to_inject as $position => $newpost ) {
					array_splice( $posts, $position - 1, 0, $newpost );
				}
			}

			// reindex just in case we got out of order keys
			$posts = array_values( $posts );
		}

		return $posts;
	}

	/**
	 * Registers the API endpoint for searching from the admin interface
	 */
	public function rest_api_init() {
		register_rest_route(
			'elasticpress/v1',
			'pointer_search',
			[
				'methods' => 'GET',
				'callback' => [ $this, 'handle_pointer_search' ],
				'args' => [
					's' => [
						'validate_callback' => function( $param ) {
							return ! empty( $param );
						},
						'required' => true,
					]
				],
			]
		);

		register_rest_route(
			'elasticpress/v1',
			'pointer_preview',
			[
				'methods' => 'GET',
				'callback' => [ $this, 'handle_pointer_preview' ],
				'args' => [
					's' => [
						'validate_callback' => function( $param ) {
							return ! empty( $param );
						},
						'required' => true,
					]
				],
			]
		);
	}

	/**
	 * Handles the search for posts from the admin interface for the post type
	 *
	 * @param \WP_REST_Request $request Rest request
	 *
	 * @return array
	 */
	public function handle_pointer_search( $request ) {
		$search = $request->get_param( 's' );

		/** @var Features $features */
		$features = Features::factory();

		/** @var Feature\Search\Search $search */
		$search_feature = $features->get_registered_feature( 'search' );

		$post_types = $search_feature->get_searchable_post_types();

		$query = new \WP_Query(
			[
				'post_type'   => $post_types,
				'post_status' => 'publish',
				's'           => $search,
			]
		);

		return $query->posts;
	}

	/**
	 * Handles the search preview on the pointer edit screen
	 *
	 * @param \WP_REST_Request $request Rest request
	 *
	 * @return array
	 */
	public function handle_pointer_preview( $request ) {
		remove_filter( 'ep_searchable_post_types', [ $this, 'searchable_post_types'] );

		$search = $request->get_param( 's' );

		$query = new \WP_Query(
			[
				's'                => $search,
				'exclude_pointers' => true,
			]
		);

		add_filter( 'ep_searchable_post_types', [ $this, 'searchable_post_types'] );

		return $query->posts;
	}

	/**
	 * Removes taxonomy terms from the references posts when a pointer is deleted or trashed
	 *
	 * @param int $post_id Post ID that is being deleted
	 */
	public function handle_post_trash( $post_id ) {
		$post = get_post( $post_id );

		if ( self::POST_TYPE_NAME !== $post->post_type ) {
			return;
		}

		/** @var Post $post_indexable */
		$post_indexable = Indexables::factory()->get( 'post' );

		$pointers = get_post_meta( $post_id, 'pointers', true );
		$term = $this->create_or_return_custom_result_term( $post->post_title );

		foreach ( $pointers as $pointer ) {
			$ref_id = $pointer['ID'];
			wp_remove_object_terms( $ref_id, (int) $term->term_id, self::TAXONOMY_NAME );

			$post_indexable->sync_manager->action_sync_on_update( $ref_id );
		}
	}

	/**
	 * Handles reassigning terms to the posts when a pointer post is restored from trash
	 *
	 * @param int $post_id Post ID
	 */
	public function handle_post_untrash( $post_id ) {
		$post = get_post( $post_id );

		if ( self::POST_TYPE_NAME !== $post->post_type ) {
			return;
		}

		/** @var Post $post_indexable */
		$post_indexable = Indexables::factory()->get( 'post' );

		$pointers = get_post_meta( $post_id, 'pointers', true );
		$term = $this->create_or_return_custom_result_term( $post->post_title );

		foreach ( $pointers as $pointer ) {
			$this->assign_term_to_post( $pointer['ID'], $term->term_taxonomy_id, $pointer['order'] );

			$post_indexable->sync_manager->action_sync_on_update( $pointer['ID'] );
		}
	}

	/**
	 * Assigns the term to the post with the proper term_order value
	 *
	 * @param $post_id
	 * @param $term_taxonomy_id
	 * @param $order
	 * @return bool|int
	 */
	protected function assign_term_to_post( $post_id, $term_taxonomy_id, $order ) {
		global $wpdb;

		$result = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO $wpdb->term_relationships (object_id, term_taxonomy_id, term_order) VALUES ( %d, %d, %d ) ON DUPLICATE KEY UPDATE term_order = VALUES(term_order)",
				$post_id,
				$term_taxonomy_id,
				$order
			)
		);

		// Delete the term order cache
		wp_cache_delete( "{$post_id}_term_order" );

		return $result;
	}

}
