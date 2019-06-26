<?php
/**
 * Search Ordering Feature
 *
 * @package elasticpress
 */

namespace ElasticPress\Feature\SearchOrdering;

use ElasticPress\Feature;

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
		add_action( 'admin_menu', [ $this, 'admin_menu' ], 50 );
		add_filter( 'parent_file', [ $this, 'parent_file' ], 50 );
		add_action( 'init', [ $this, 'register_post_type' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
		add_action( 'save_post_' . self::POST_TYPE_NAME, [ $this, 'save_post'], 10, 2 );
		add_filter( 'ep_searchable_post_types', [ $this, 'searchable_post_types'] );
		add_filter( 'ep_search_fields', [ $this, 'ep_search_fields' ], 10, 2 );
		add_action( 'posts_results', [ $this, 'posts_results' ], 20, 2 );  // Runs after core ES is done
		add_action( 'rest_api_init', [ $this, 'rest_api_init' ] );
		add_filter( 'ep_indexable_post_types', [ $this, 'filter_indexable_post_types'] );
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
	 * Adds this post type to indexable types
	 *
	 * @param array $post_types Current indexable post types
	 *
	 * @return array Updated post types
	 */
	public function filter_indexable_post_types( $post_types ) {
		$post_types[ self::POST_TYPE_NAME ] = self::POST_TYPE_NAME;

		return $post_types;
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
			'name'               => _x( 'Search Result', 'post type general name', 'elasticpress' ),
			'singular_name'      => _x( 'Search Result', 'post type singular name', 'elasticpress' ),
			'menu_name'          => _x( 'Search Results', 'admin menu', 'elasticpress' ),
			'name_admin_bar'     => _x( 'Search Result', 'add new on admin bar', 'elasticpress' ),
			'add_new'            => _x( 'Add New', 'book', 'elasticpress' ),
			'add_new_item'       => __( 'Add New Search Result', 'elasticpress' ),
			'new_item'           => __( 'New Search Result', 'elasticpress' ),
			'edit_item'          => __( 'Edit Search Result', 'elasticpress' ),
			'view_item'          => __( 'View Search Result', 'elasticpress' ),
			'all_items'          => __( 'All Search Results', 'elasticpress' ),
			'search_items'       => __( 'Search Injected Search Results', 'elasticpress' ),
			'parent_item_colon'  => __( 'Parent Search Result:', 'elasticpress' ),
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
		if ( ! isset( $_POST['search-ordering-nonce'] ) || ! wp_verify_nonce( $_POST['search-ordering-nonce'], 'save-search-ordering' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post ) ) {
			return;
		}

		$final_order_data = [];

		$ordered_posts = json_decode( wp_unslash( $_POST['ordered_posts'] ), true );

		foreach ( $ordered_posts as $order_data ) {
			$final_order_data[] = [
				'ID'    => intval( $order_data['ID'] ),
				'order' => intval( $order_data['order'] ),
			];
		}

		update_post_meta( $post_id, 'pointers', $final_order_data );

		// Set the meta key with the search term to the title
		update_post_meta( $post_id, 'pointer_query', sanitize_text_field( $post->post_title ) );
	}

	/**
	 * Adds this post type to the search post types for normal frontend WordPress searches
	 *
	 * @param array $post_types Current searchable post types
	 *
	 * @return array Modified searchable post types
	 */
	public function searchable_post_types( $post_types ) {
		$post_types[] = self::POST_TYPE_NAME;

		return $post_types;
	}

	/**
	 * Adds the pointer query field to the search fields with a high boost level so it always surfaces to the top
	 *
	 * @param array $fields Current search fields
	 * @param array $args   Current query args
	 *
	 * @return array Search fields with any modifications from the filter
	 */
	public function ep_search_fields( $fields, $args ) {
		if ( ! isset( $args['exclude_pointers'] ) || true !== $args['exclude_pointers'] ) {
			$fields[] = 'meta.pointer_query^9999';
		}

		return $fields;
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
			$to_inject = array();

			// So we can avoid duplicates
			$to_inject_ids = array();

			foreach ( $posts as $key => &$post ) {
				if ( $post->post_type === self::POST_TYPE_NAME ) {
					$pointers = get_post_meta( $post->ID, 'pointers', true );

					// Pointers always need to be unset, regardless if they have pointer IDs or not
					unset( $posts[ $key ] );

					if ( empty( $pointers ) ) {
						continue;
					}

					foreach ( $pointers as $pointer ) {
						$points_to = $pointer['ID'];
						$order = $pointer['order'];

						$to_inject[ $order ] = $points_to;
						$to_inject_ids[ $points_to ] = true;
					}
				}
			}

			// Remove any that will be duplicates
			foreach ( $posts as &$post ) {
				if ( isset( $to_inject_ids[ $post->ID ] ) ) {
					// Null so we don't break the loop
					$post = null;
				}
			}

			// Remove the null values
			$posts = array_filter( $posts );

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

		$post_types = get_post_types(
			[
				'public'              => 'true',
				'exclude_from_search' => false,
			]
		);

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

}
