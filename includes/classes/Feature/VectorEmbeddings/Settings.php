<?php
/**
 * Vector Embeddings
 *
 * This feature configures storage of vector embeddings.
 *
 * @since 5.4.0
 * @package ElasticPress
 */

namespace ElasticPress\Feature\VectorEmbeddings;

use ElasticPress\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Vector Embeddings feature
 */
class Settings {

	const SETTINGS_KEY = 'ep_vector_embeddings_settings';

	/**
	 * WordPress Hooks
	 */
	public function setup() {
		add_action( 'rest_api_init', [ $this, 'setup_endpoint' ] );
		add_action( 'admin_menu', [ $this, 'add_vector_embedding_submenu_page' ], 15 );
		add_action( 'admin_init', [ $this, 'set_current_screen' ], 11 );
		add_action( 'admin_enqueue_scripts', [ $this, 'scripts' ] );
		add_filter( 'elasticpress_general_ep_screens', [ $this, 'add_vector_embeddings_screen' ] );
	}

	/**
	 * Setup REST endpoints
	 */
	public function setup_endpoint() {
		$controller = new \ElasticPress\REST\VectorEmbeddingSettings();
		$controller->register_routes();
	}

	/**
	 * Add the Vector Embeddings submenu page.
	 */
	public function add_vector_embedding_submenu_page() {
		add_submenu_page(
			'elasticpress',
			esc_html__( 'Vector Embeddings', 'elasticpress' ),
			esc_html__( 'Vector Embeddings', 'elasticpress' ),
			'manage_options',
			'elasticpress-vector-embeddings',
			[ $this, 'render_settings_page' ],
			15
		);
	}

	/**
	 * Renders the settings page
	 */
	public function render_settings_page() {
		include EP_PATH . '/includes/partials/header.php'; ?>
		<div class="wrap">
			<div id="ep-vector-embeddings"></div>
		</div>
		<?php
	}

	/**
	 * Check if we are on the Vector Embeddings page.
	 *
	 * @return boolean
	 */
	public function is_vector_embeddings_page() {
		if ( empty( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return false;
		}

		$page = sanitize_key( $_GET['page'] ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( false === strpos( $page, 'elasticpress' ) ) {
			return false;
		}

		return 'elasticpress-vector-embeddings' === $page;
	}

	/**
	 * Set the current screen.
	 *
	 * @since 5.4.0
	 * @return void
	 */
	public function set_current_screen() {
		// If EP version is less than 5.3.3, setting the current screen breaks the header.
		if ( ! defined( 'EP_VERSION' ) || version_compare( EP_VERSION, '5.3.3', '<' ) ) {
			return;
		}

		if ( ! $this->is_vector_embeddings_page() ) {
			return;
		}

		\ElasticPress\Screen::factory()->set_current_screen( 'vector-embeddings' );
	}

	/**
	 * Enqueue scripts and styles for the settings page.
	 */
	public function scripts() {
		if ( ! $this->is_vector_embeddings_page() ) {
			return;
		}

		wp_enqueue_script(
			'ep_vector_embeddings_scripts',
			EP_URL . 'dist/js/embeddings-script.js',
			Utils\get_asset_info( 'embeddings-script', 'dependencies' ),
			Utils\get_asset_info( 'embeddings-script', 'version' ),
			true
		);

		wp_set_script_translations( 'ep_vector_embeddings_scripts', 'elasticpress' );

		wp_enqueue_style( 'wp-edit-post' );

		wp_enqueue_style(
			'ep_vector_embeddings_scripts',
			EP_URL . 'dist/css/embeddings-script.css',
			[],
			Utils\get_asset_info( 'embeddings-script', 'version' ),
			'all'
		);

		wp_localize_script(
			'ep_vector_embeddings_scripts',
			'epVectorEmbeddings',
			[
				'apiUrl'             => rest_url( 'elasticpress/v1/vector-embeddings' ),
				'settings'           => $this->get_settings(),
				'indexablePostTypes' => array_keys( $this->get_searchable_post_types() ),
				'embeddingsFiltered' => has_filter( 'ep_embeddings_is_embeddable' ),
			]
		);
	}

	/**
	 * Add the vector embeddings screen to the list of ElasticPress general screens.
	 *
	 * This way, JS and CSS assets for the header are enqueued.
	 *
	 * @since 5.4.0
	 * @param array $screens The list of screens.
	 * @return array The list of screens.
	 */
	public function add_vector_embeddings_screen( $screens ) {
		$screens[] = 'vector-embeddings';
		return $screens;
	}

	/**
	 * Get the default embeddable post type configurations. If options are saved, return those instead.
	 *
	 * Get a list of searchable post types, add their taxonomies, and set the inclusion to include.
	 *
	 * @return array
	 */
	public function get_default_post_type_config() {

		// else, generate the default settings.
		$post_types = $this->get_searchable_post_types();

		$return = [];

		foreach ( $post_types as $post_type ) {
			$post_type_object  = get_post_type_object( $post_type );
			$object_taxonomies = get_object_taxonomies( $post_type, 'objects' );

			// only use public taxonomies.
			$public_taxonomies = array_filter(
				$object_taxonomies,
				function ( $taxonomy ) {
					return $taxonomy->public && 'post_format' !== $taxonomy->name;
				}
			);

			$public_taxonomies = array_map(
				function ( $taxonomy ) {
					return [
						'name'         => $taxonomy->name,
						'label'        => $taxonomy->label,
						'termsInclude' => [],
						'termsExclude' => [],
						'enabled'      => true,
					];
				},
				$public_taxonomies
			);

			// this is the shape of a post type configuration.
			$return[] = [
				'embeddable'            => true, // whether to allow embeddings for this post type.
				'embeddingMode'         => 'automatic', // Whether to use auto or manual embedding.
				'enableFieldsIndexing'  => false, // Whether to flagging content inclusion via post meta.
				'fieldsIndexingInclude' => [], // Meta fields used to flag content for inclusion.
				'fieldsIndexingExclude' => [], // Meta fields used to flag content for exclusion. A post with an exluded
				'fieldsEmbedding'       => [ 'post_title', 'post_content' ], // Fields to use for embedding generation.
				'label'                 => $post_type_object->label, // Label used for settings panel.
				'key'                   => $post_type, // post type name used for key in the settings object.
				'taxonomies'            => $public_taxonomies, // Taxonomies to consider for vector embedding.
			];
		}

		return $return;
	}

	/**
	 * Get the post types that are applicable for indexing.
	 *
	 * @return array
	 */
	public function get_searchable_post_types() {
		$search = \ElasticPress\Features::factory()->get_registered_feature( 'search' );

		return $search->get_searchable_post_types();
	}

	/**
	 * Get the current settings.
	 *
	 * @return array
	 */
	public function get_settings() {
		static $settings;

		if ( ! $settings ) {
			$settings = get_option(
				self::SETTINGS_KEY,
				[
					'postTypeConfig' => $this->get_default_post_type_config(),
					'chunkSize'      => 100,
					'chunkOverlap'   => 50,
				]
			);
		}

		return $settings;
	}

	/**
	 * Get the post type configuration for a given post type.
	 *
	 * @param string $post_type The post type to get the configuration for.
	 * @return array The post type configuration.
	 */
	public function get_post_type_config( $post_type ) {
		$post_type_config = array_filter(
			$this->get_settings()['postTypeConfig'] ?? [],
			function ( $config ) use ( $post_type ) {
				return $config['key'] === $post_type;
			}
		);

		if ( empty( $post_type_config ) ) {
			return [];
		}

		// get the first element of the array
		return reset( $post_type_config );
	}

	/**
	 * Check if a post type is embeddable.
	 *
	 * @param string $post_type The post type to check.
	 * @return bool True if the post type is embeddable, false otherwise.
	 */
	public function is_post_type_embeddable( $post_type ) {
		$config = $this->get_post_type_config( $post_type );
		if ( empty( $config ) ) {
			return false;
		}

		return ! empty( $config['embeddable'] );
	}

	/**
	 * Check if a post is embeddable based on taxonomy and post meta conditions.
	 *
	 * @param int $post_id The ID of the post to check.
	 * @return bool True if the post is embeddable, false otherwise.
	 */
	public function is_embeddable( $post_id ) {
		/**
		 * Override the embeddable status of a post.
		 *
		 * @param bool $embeddable The embeddable status of the post.
		 * @param int $post_id The ID of the post to check.
		 *
		 * @since 5.4.0
		 * @return bool
		 */
		$embeddable = apply_filters( 'ep_embeddings_is_embeddable', null, $post_id );
		if ( has_filter( 'ep_embeddings_is_embeddable' ) ) {
			return $embeddable;
		}

		$config = $this->get_post_type_config( get_post_type( $post_id ) );

		if ( empty( $config ) ) {
			return false;
		}

		if ( ! $config['embeddable'] ) {
			return false;
		}

		[
			'embeddable'    => $embeddable,
			'embeddingMode' => $embedding_mode
		] = $config;

		if ( 'manual' === $embedding_mode ) {
			return get_post_meta( $post_id, 'ep_embedding_include', true );
		}

		if ( 'automatic' === $embedding_mode ) {
			return ! get_post_meta( $post_id, 'ep_embedding_exclude', true );
		}

		if ( ! $embeddable ) {
			return false;
		}

		if ( $this->is_excluded_by_taxonomy( $post_id ) || $this->is_excluded_by_meta( $post_id ) ) {
			return false;
		}

		if ( $this->is_included_by_taxonomy( $post_id ) || $this->is_included_by_meta( $post_id ) ) {
			return true;
		}

		$default = $this->get_default_inclusion_rule();

		return $default;
	}

	/**
	 * Check if a post is excluded by taxonomy conditions.
	 *
	 * @param int $post_id The ID of the post to check.
	 * @return bool True if the post is excluded, false otherwise.
	 */
	public function is_excluded_by_taxonomy( $post_id ) {
		$config = $this->get_post_type_config( get_post_type( $post_id ) );

		if ( empty( $config ) ) {
			return false;
		}

		$taxonomies = $config['taxonomies'] ?? [];

		foreach ( $taxonomies as $taxonomy ) {
			$terms = get_the_terms( $post_id, $taxonomy['name'] );

			if ( ! $terms || is_wp_error( $terms ) ) {
				continue;
			}

			foreach ( $terms as $term ) {
				if ( in_array( $term->term_id, $taxonomy['termsExclude'], true ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Check if a post is included by taxonomy conditions.
	 *
	 * @param int $post_id The ID of the post to check.
	 * @return bool True if the post is included, false otherwise.
	 */
	public function is_included_by_taxonomy( $post_id ) {
		$config = $this->get_post_type_config( get_post_type( $post_id ) );

		if ( empty( $config ) ) {
			return false;
		}

		$taxonomies = $config['taxonomies'] ?? [];

		foreach ( $taxonomies as $taxonomy ) {
			$terms = get_the_terms( $post_id, $taxonomy['name'] );

			if ( ! $terms || is_wp_error( $terms ) ) {
				continue;
			}

			foreach ( $terms as $term ) {
				if ( in_array( $term->term_id, $taxonomy['termsInclude'], true ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Check if a post is excluded by meta conditions.
	 *
	 * @param int $post_id The ID of the post to check.
	 * @return bool True if the post is excluded, false otherwise.
	 */
	public function is_excluded_by_meta( $post_id ) {
		$config = $this->get_post_type_config( get_post_type( $post_id ) );

		if ( empty( $config ) ) {
			return false;
		}

		$fields_indexing_exclude = $config['fieldsIndexingExclude'] ?? [];

		foreach ( $fields_indexing_exclude as $field ) {
			$value = get_post_meta( $post_id, $field, true );

			if ( $value ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if a post is included by meta conditions.
	 *
	 * @param int $post_id The ID of the post to check.
	 * @return bool True if the post is included, false otherwise.
	 */
	public function is_included_by_meta( $post_id ) {
		$config = $this->get_post_type_config( get_post_type( $post_id ) );

		if ( empty( $config ) ) {
			return false;
		}

		$fields_indexing_include = $config['fieldsIndexingInclude'] ?? [];

		foreach ( $fields_indexing_include as $field ) {
			$value = get_post_meta( $post_id, $field, true );

			if ( $value ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get the fields used for embedding content.
	 *
	 * @param int $post_id The ID of the post to get the fields for.
	 * @return array The fields used for embedding content.
	 */
	public function get_embedding_fields( $post_id ) {
		$config = $this->get_post_type_config( get_post_type( $post_id ) );

		if ( empty( $config ) ) {
			return [];
		}

		return $config['fieldsEmbedding'] ?? [];
	}

	/**
	 * Get the default inclusion rule.
	 *
	 * Default Inclusion Rule Help
	 *
	 * The Default Inclusion Rule determines how posts are included or excluded for vector embeddings
	 * based on taxonomy conditions.
	 *
	 * - Taxonomy with `termsInclude`:
	 *     - If a taxonomy has `termsInclude` values, only posts with the specified terms will be included.
	 *     - Posts without these terms will be excluded.
	 *     - Default behavior: Posts are not included by default.
	 *
	 * - Taxonomy with `termsExclude`:
	 *     - If a taxonomy has `termsExclude` values, posts with the specified terms will be excluded.
	 *     - Posts without these terms will be included.
	 *     - Default behavior: Posts are included by default.
	 *
	 * - Both `termsInclude` and `termsExclude`:
	 *     - If both `termsInclude` and `termsExclude` are set for a taxonomy, the rule defaults to excluding
	 *       posts that match `termsExclude`, even if they also match `termsInclude`.
	 *     - Default behavior: Posts are not included by default.
	 *
	 * Scenarios:
	 *
	 * - No Rules Set:
	 *     - If no `termsInclude` or `termsExclude` values are set, all posts are included by default.
	 *
	 * - Conflicting Rules:
	 *     - If conflicting rules are set, this system prioritizes `termsExclude` over `termsInclude`.
	 *
	 * Examples:
	 *
	 * - Example 1: A taxonomy has `termsInclude` set to "Category A".
	 *     - Posts in "Category A" are included.
	 *     - Posts not in "Category A" are excluded.
	 *
	 * - Example 2: A taxonomy has `termsExclude` set to "Tag B".
	 *     - Posts with "Tag B" are excluded.
	 *     - Posts without "Tag B" are included.
	 *
	 * - Example 3: A taxonomy has both `termsInclude` ("Category A") and `termsExclude` ("Tag B").
	 *     - Posts with "Tag B" are always excluded, even if they are in "Category A".
	 *     - Only posts in "Category A" that do not have "Tag B" are included.
	 *
	 * @return bool Whether to include posts by default.
	 */
	public function get_default_inclusion_rule() {
		if ( empty( $this->get_settings()['postTypeConfig'] ) ) {
			return false; // No rules set - posts are not included by default
		}

		$has_include_rules = false;

		// Check each post type config for taxonomy rules
		foreach ( $this->get_settings()['postTypeConfig'] as $post_type_config ) {
			if ( empty( $post_type_config['taxonomies'] ) ) {
				return true;
			}

			foreach ( $post_type_config['taxonomies'] as $taxonomy ) {
				// Check for include rules
				if ( ! empty( $taxonomy['termsInclude'] ) ) {
					$has_include_rules = true;
				}
			}
		}

		// if post meta is set to include, return true
		if ( ! empty( $this->get_settings()['fieldsIndexingInclude'] ) ) {
			$has_include_rules = true;
		}

		return ! $has_include_rules;
	}

	/**
	 * Get chunk size set in settings
	 *
	 * @return int
	 */
	public function get_chunk_size() {
		return $this->get_settings()['chunkSize'] ?? 100;
	}

	/**
	 * Get chunk overlap set in settings
	 *
	 * @return int
	 */
	public function get_chunk_overlap() {
		return $this->get_settings()['chunkOverlap'] ?? 50;
	}
}
