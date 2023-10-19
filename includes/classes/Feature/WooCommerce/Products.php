<?php
/**
 * WooCommerce Products
 *
 * @since 4.7.0
 * @package elasticpress
 */

namespace ElasticPress\Feature\WooCommerce;

use ElasticPress\Indexables;
use ElasticPress\IndexHelper;
use ElasticPress\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WooCommerce Products
 */
class Products {
	/**
	 * WooCommerce feature object instance
	 *
	 * @var WooCommerce
	 */
	protected $woocommerce;

	/**
	 * Class constructor
	 *
	 * @param WooCommerce $woocommerce WooCommerce feature object instance
	 */
	public function __construct( WooCommerce $woocommerce ) {
		$this->woocommerce = $woocommerce;
	}

	/**
	 * Setup product related hooks
	 */
	public function setup() {
		add_action( 'ep_formatted_args', [ $this, 'price_filter' ], 10, 3 );
		add_filter( 'ep_prepare_meta_allowed_protected_keys', [ $this, 'allow_meta_keys' ], 10, 2 );
		add_filter( 'ep_sync_taxonomies', [ $this, 'sync_taxonomies' ] );
		add_filter( 'ep_term_suggest_post_type', [ $this, 'suggest_wc_add_post_type' ] );
		add_filter( 'ep_facet_include_taxonomies', [ $this, 'add_product_attributes' ] );
		add_filter( 'ep_weighting_fields_for_post_type', [ $this, 'add_product_attributes_to_weighting' ], 10, 2 );
		add_filter( 'ep_weighting_default_post_type_weights', [ $this, 'add_product_default_post_type_weights' ], 10, 2 );
		add_filter( 'ep_prepare_meta_data', [ $this, 'add_variations_skus_meta' ], 10, 2 );
		add_filter( 'request', [ $this, 'admin_product_list_request_query' ], 9 );
		add_action( 'pre_get_posts', [ $this, 'translate_args' ], 11, 1 );
		add_filter( 'ep_facet_tax_special_slug_taxonomies', [ $this, 'add_taxonomy_attributes' ] );

		// Custom product ordering
		add_action( 'ep_admin_notices', [ $this, 'maybe_display_notice_about_product_ordering' ] );
		add_action( 'woocommerce_after_product_ordering', [ $this, 'action_sync_on_woocommerce_sort_single' ], 10, 2 );

		// Settings for Weight results by date
		add_action( 'ep_weight_settings_after_search', [ $this, 'add_weight_settings_search' ] );
		add_filter( 'ep_feature_settings_schema', [ $this, 'add_weight_settings_search_schema' ], 10, 2 );
		add_filter( 'ep_is_decaying_enabled', [ $this, 'maybe_disable_decaying' ], 10, 3 );
	}

	/**
	 * Un-setup product related hooks
	 *
	 * @since 5.0.0
	 */
	public function tear_down() {
		remove_action( 'ep_formatted_args', [ $this, 'price_filter' ] );
		remove_filter( 'ep_prepare_meta_allowed_protected_keys', [ $this, 'allow_meta_keys' ] );
		remove_filter( 'ep_sync_taxonomies', [ $this, 'sync_taxonomies' ] );
		remove_filter( 'ep_term_suggest_post_type', [ $this, 'suggest_wc_add_post_type' ] );
		remove_filter( 'ep_facet_include_taxonomies', [ $this, 'add_product_attributes' ] );
		remove_filter( 'ep_weighting_fields_for_post_type', [ $this, 'add_product_attributes_to_weighting' ] );
		remove_filter( 'ep_weighting_default_post_type_weights', [ $this, 'add_product_default_post_type_weights' ] );
		remove_filter( 'ep_prepare_meta_data', [ $this, 'add_variations_skus_meta' ] );
		remove_filter( 'request', [ $this, 'admin_product_list_request_query' ], 9 );
		remove_action( 'pre_get_posts', [ $this, 'translate_args' ], 11 );
		remove_filter( 'ep_facet_tax_special_slug_taxonomies', [ $this, 'add_taxonomy_attributes' ] );

		// Custom product ordering
		remove_action( 'ep_admin_notices', [ $this, 'maybe_display_notice_about_product_ordering' ] );
		remove_action( 'woocommerce_after_product_ordering', [ $this, 'action_sync_on_woocommerce_sort_single' ] );

		// Settings for Weight results by date
		remove_action( 'ep_weight_settings_after_search', [ $this, 'add_weight_settings_search' ] );
		remove_filter( 'ep_feature_settings_schema', [ $this, 'add_weight_settings_search_schema' ] );
		remove_filter( 'ep_is_decaying_enabled', [ $this, 'maybe_disable_decaying' ] );
	}

	/**
	 * Modifies main query to allow filtering by price with WooCommerce "Filter by price" widget.
	 *
	 * @param array    $args ES args
	 * @param array    $query_args WP_Query args
	 * @param WP_Query $query WP_Query object
	 * @return array
	 */
	public function price_filter( $args, $query_args, $query ) {
		// Only can use widget on main query
		if ( ! $query->is_main_query() ) {
			return $args;
		}

		// Only can use widget on shop, product taxonomy, or search
		if ( ! is_shop() && ! is_product_taxonomy() && ! is_search() ) {
			return $args;
		}

		// phpcs:disable WordPress.Security.NonceVerification
		if ( empty( $_GET['min_price'] ) && empty( $_GET['max_price'] ) ) {
			return $args;
		}

		$min_price = ! empty( $_GET['min_price'] ) ? sanitize_text_field( wp_unslash( $_GET['min_price'] ) ) : null;
		$max_price = ! empty( $_GET['max_price'] ) ? sanitize_text_field( wp_unslash( $_GET['max_price'] ) ) : null;
		// phpcs:enable WordPress.Security.NonceVerification

		if ( $query->is_search() ) {
			/**
			 * This logic is iffy but the WC price filter widget is not intended for use with search anyway
			 */
			$old_query = $args['query']['bool'];
			unset( $args['query']['bool']['should'] );

			if ( ! empty( $min_price ) ) {
				$args['query']['bool']['must'][0]['range']['meta._price.long']['gte'] = $min_price;
			}

			if ( ! empty( $max_price ) ) {
				$args['query']['bool']['must'][0]['range']['meta._price.long']['lte'] = $max_price;
			}

			$args['query']['bool']['must'][0]['range']['meta._price.long']['boost'] = 2.0;
			$args['query']['bool']['must'][1]['bool']                               = $old_query;
		} else {
			unset( $args['query']['match_all'] );

			$args['query']['range']['meta._price.long']['gte'] = ! empty( $min_price ) ? $min_price : 0;

			if ( ! empty( $min_price ) ) {
				$args['query']['range']['meta._price.long']['gte'] = $min_price;
			}

			if ( ! empty( $max_price ) ) {
				$args['query']['range']['meta._price.long']['lte'] = $max_price;
			}

			$args['query']['range']['meta._price.long']['boost'] = 2.0;
		}

		return $args;
	}

	/**
	 * Index WooCommerce products meta fields
	 *
	 * @param array    $meta Existing post meta
	 * @param \WP_Post $post Post object.
	 * @return array
	 */
	public function allow_meta_keys( $meta, $post ) {
		if ( 'product' !== $post->post_type ) {
			return $meta;
		}

		return array_unique(
			array_merge(
				$meta,
				array(
					'_thumbnail_id',
					'_product_attributes',
					'_wpb_vc_js_status',
					'_swatch_type',
					'total_sales',
					'_downloadable',
					'_virtual',
					'_regular_price',
					'_sale_price',
					'_tax_status',
					'_tax_class',
					'_purchase_note',
					'_featured',
					'_weight',
					'_length',
					'_width',
					'_height',
					'_visibility',
					'_sku',
					'_sale_price_dates_from',
					'_sale_price_dates_to',
					'_price',
					'_sold_individually',
					'_manage_stock',
					'_backorders',
					'_stock',
					'_upsell_ids',
					'_crosssell_ids',
					'_stock_status',
					'_product_version',
					'_product_tabs',
					'_override_tab_layout',
					'_suggested_price',
					'_min_price',
					'_variable_billing',
					'_wc_average_rating',
					'_product_image_gallery',
					'_bj_lazy_load_skip_post',
					'_min_variation_price',
					'_max_variation_price',
					'_min_price_variation_id',
					'_max_price_variation_id',
					'_min_variation_regular_price',
					'_max_variation_regular_price',
					'_min_regular_price_variation_id',
					'_max_regular_price_variation_id',
					'_min_variation_sale_price',
					'_max_variation_sale_price',
					'_min_sale_price_variation_id',
					'_max_sale_price_variation_id',
					'_default_attributes',
					'_swatch_type_options',
					'_variations_skus',
				)
			)
		);
	}

	/**
	 * Index WooCommerce taxonomies
	 *
	 * @param array $taxonomies Index taxonomies array
	 * @return array
	 */
	public function sync_taxonomies( $taxonomies ) {
		$woo_taxonomies = [];

		$product_type = get_taxonomy( 'product_type' );
		if ( false !== $product_type ) {
			$woo_taxonomies[] = $product_type;
		}

		$product_visibility = get_taxonomy( 'product_visibility' );
		if ( false !== $product_visibility ) {
			$woo_taxonomies[] = $product_visibility;
		}

		/**
		 * Note product_shipping_class, product_cat, and product_tag are already public. Make
		 * sure to index non-attribute taxonomies.
		 */
		$attribute_taxonomies = wc_get_attribute_taxonomies();

		if ( ! empty( $attribute_taxonomies ) ) {
			foreach ( $attribute_taxonomies as $tax ) {
				$name = wc_attribute_taxonomy_name( $tax->attribute_name );

				if ( ! empty( $name ) ) {
					if ( empty( $tax->attribute_ ) ) {
						$woo_taxonomies[] = get_taxonomy( $name );
					}
				}
			}
		}

		return array_merge( $taxonomies, $woo_taxonomies );
	}

	/**
	 * Add WC product post type to autosuggest
	 *
	 * @param array $post_types Array of post types (e.g. post, page)
	 * @return array
	 */
	public function suggest_wc_add_post_type( $post_types ) {
		if ( ! in_array( 'product', $post_types, true ) ) {
			$post_types[] = 'product';
		}

		return $post_types;
	}

	/**
	 * Add WooCommerce Product Attributes to EP Facets.
	 *
	 * @param array $taxonomies Taxonomies array
	 * @return array
	 */
	public function add_product_attributes( $taxonomies = [] ) {
		$attribute_names = wc_get_attribute_taxonomy_names();

		foreach ( $attribute_names as $name ) {
			if ( ! taxonomy_exists( $name ) ) {
				continue;
			}
			$taxonomies[ $name ] = get_taxonomy( $name );
		}

		return $taxonomies;
	}

	/**
	 * Add WooCommerce Fields to the Weighting Dashboard.
	 *
	 * @param array  $fields    Current weighting fields.
	 * @param string $post_type Current post type.
	 * @return array            New fields.
	 */
	public function add_product_attributes_to_weighting( $fields, $post_type ) {
		if ( 'product' !== $post_type ) {
			return $fields;
		}

		if ( ! empty( $fields['attributes']['children']['author_name'] ) ) {
			unset( $fields['attributes']['children']['author_name'] );
		}

		$sku_key = 'meta._sku.value';

		unset( $fields['ep_metadata']['children'][ $sku_key ] );

		$fields['attributes']['children'][ $sku_key ] = array(
			'key'   => $sku_key,
			'label' => __( 'SKU', 'elasticpress' ),
		);

		$variations_skus_key = 'meta._variations_skus.value';

		unset( $fields['ep_metadata']['children'][ $variations_skus_key ] );

		$fields['attributes']['children'][ $variations_skus_key ] = array(
			'key'   => $variations_skus_key,
			'label' => __( 'Variations SKUs', 'elasticpress' ),
		);

		return $fields;
	}

	/**
	 * Add WooCommerce Fields to the default values of the Weighting Dashboard.
	 *
	 * @param array  $defaults  Default values for the post type.
	 * @param string $post_type Current post type.
	 * @return array
	 */
	public function add_product_default_post_type_weights( $defaults, $post_type ) {
		if ( 'product' !== $post_type ) {
			return $defaults;
		}

		if ( ! empty( $defaults['author_name'] ) ) {
			unset( $defaults['author_name'] );
		}

		$defaults['meta._sku.value'] = array(
			'enabled' => true,
			'weight'  => 1,
		);

		$defaults['meta._variations_skus.value'] = array(
			'enabled' => true,
			'weight'  => 1,
		);

		return $defaults;
	}

	/**
	 * Add a new `_variations_skus` meta field to the product to be indexed in Elasticsearch.
	 *
	 * @param array   $post_meta Post meta
	 * @param WP_Post $post      Post object
	 * @return array
	 */
	public function add_variations_skus_meta( $post_meta, $post ) {
		if ( 'product' !== $post->post_type ) {
			return $post_meta;
		}

		$product = wc_get_product( $post );
		if ( ! $product ) {
			return $post_meta;
		}

		$variations_ids = $product->get_children();

		$post_meta['_variations_skus'] = array_reduce(
			$variations_ids,
			function ( $variations_skus, $current_id ) {
				$variation = wc_get_product( $current_id );
				if ( ! $variation || ! $variation->exists() ) {
					return $variations_skus;
				}
				$variation_sku = $variation->get_sku();
				if ( ! $variation_sku ) {
					return $variations_skus;
				}
				$variations_skus[] = $variation_sku;
				return $variations_skus;
			},
			[]
		);

		return $post_meta;
	}

	/**
	 * Integrate ElasticPress with the WooCommerce Admin Product List.
	 *
	 * WooCommerce uses its `WC_Admin_List_Table_Products` class to control that screen. This
	 * function adds all necessary hooks to bypass the default behavior and integrate with ElasticPress.
	 * By default, WC runs a SQL query to get the Product IDs that match the list criteria and passes
	 * that list of IDs to the main WP_Query. This integration changes that process to a single query, run
	 * by ElasticPress.
	 *
	 * @param array $query_vars Query vars.
	 * @return array
	 */
	public function admin_product_list_request_query( $query_vars ) {
		global $typenow, $wc_list_table;

		// Return if not in the correct screen.
		if ( ! is_a( $wc_list_table, 'WC_Admin_List_Table_Products' ) || 'product' !== $typenow ) {
			return $query_vars;
		}

		// Return if admin WP_Query integration is not turned on, i.e., Protect Content is not enabled.
		if ( ! has_filter( 'ep_admin_wp_query_integration', '__return_true' ) ) {
			return $query_vars;
		}

		/**
		 * Filter to skip integration with WooCommerce Admin Product List.
		 *
		 * @hook ep_woocommerce_integrate_admin_products_list
		 * @since 4.2.0
		 * @param {bool}  $integrate  True to integrate, false to preserve original behavior. Defaults to true.
		 * @param {array} $query_vars Query vars.
		 * @return {bool} New integrate value
		 */
		if ( ! apply_filters( 'ep_woocommerce_integrate_admin_products_list', true, $query_vars ) ) {
			return $query_vars;
		}

		add_action( 'pre_get_posts', [ $this, 'translate_args_admin_products_list' ], 12 );

		// This short-circuits WooCommerce search for product IDs.
		add_filter( 'woocommerce_product_pre_search_products', '__return_empty_array' );

		return $query_vars;
	}

	/**
	 * Apply the necessary changes to WP_Query in WooCommerce Admin Product List.
	 *
	 * @param WP_Query $query The WP Query being executed.
	 */
	public function translate_args_admin_products_list( $query ) {
		// The `translate_args()` method sets it to `true` if we should integrate it.
		if ( ! $query->get( 'ep_integrate', false ) ) {
			return;
		}

		// WooCommerce unsets the search term right after using it to fetch product IDs. Here we add it back.
		$search_term = ! empty( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! empty( $search_term ) ) {
			$query->set( 's', sanitize_text_field( $search_term ) ); // phpcs:ignore WordPress.Security.NonceVerification

			/**
			 * Filter the fields used in WooCommerce Admin Product Search.
			 *
			 * ```
			 * add_filter(
			 *     'ep_woocommerce_admin_products_list_search_fields',
			 *     function ( $wc_admin_search_fields ) {
			 *         $wc_admin_search_fields['meta'][] = 'custom_field';
			 *         return $wc_admin_search_fields;
			 *     }
			 * );
			 * ```
			 *
			 * @hook ep_woocommerce_admin_products_list_search_fields
			 * @since 4.2.0
			 * @param {array} $wc_admin_search_fields Fields to be used in the WooCommerce Admin Product Search
			 * @return {array} New fields
			 */
			$search_fields = apply_filters(
				'ep_woocommerce_admin_products_list_search_fields',
				[
					'post_title',
					'post_content',
					'post_excerpt',
					'meta' => [
						'_sku',
						'_variations_skus',
					],
				]
			);

			$query->set( 'search_fields', $search_fields );
		}

		// Sets the meta query for `product_type` if needed. Also removed from the WP_Query by WC in `WC_Admin_List_Table_Products::query_filters()`.
		$product_type_query = $query->get( 'product_type', '' );
		$product_type_url   = ! empty( $_GET['product_type'] ) ? sanitize_text_field( wp_unslash( $_GET['product_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$allowed_prod_types = [ 'virtual', 'downloadable' ];
		if ( empty( $product_type_query ) && ! empty( $product_type_url ) && in_array( $product_type_url, $allowed_prod_types, true ) ) {
			$meta_query   = $query->get( 'meta_query', [] );
			$meta_query[] = [
				'key'   => "_{$product_type_url}",
				'value' => 'yes',
			];
			$query->set( 'meta_query', $meta_query );
		}

		// Sets the meta query for `stock_status` if needed.
		$stock_status_query   = $query->get( 'stock_status', '' );
		$stock_status_url     = ! empty( $_GET['stock_status'] ) ? sanitize_text_field( wp_unslash( $_GET['stock_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$allowed_stock_status = [ 'instock', 'outofstock', 'onbackorder' ];
		if ( empty( $stock_status_query ) && ! empty( $stock_status_url ) && in_array( $stock_status_url, $allowed_stock_status, true ) ) {
			$meta_query   = $query->get( 'meta_query', [] );
			$meta_query[] = [
				'key'   => '_stock_status',
				'value' => $stock_status_url,
			];
			$query->set( 'meta_query', $meta_query );
		}
	}

	/**
	 * Depending on the number of products display an admin notice in the custom sort screen for WooCommerce Products
	 *
	 * @param array $notices Current ElasticPress admin notices
	 * @return array
	 */
	public function maybe_display_notice_about_product_ordering( $notices ) {
		global $pagenow, $wp_query;

		/**
		 * Make sure we're on edit.php in admin dashboard.
		 */
		if ( ! is_admin() || 'edit.php' !== $pagenow || empty( $wp_query->query['orderby'] ) || 'menu_order title' !== $wp_query->query['orderby'] ) {
			return $notices;
		}

		$documents_per_page_sync = IndexHelper::factory()->get_index_default_per_page();
		if ( $documents_per_page_sync >= $wp_query->found_posts ) {
			return $notices;
		}

		$notices['woocommerce_custom_sort'] = [
			'html'    => sprintf(
				/* translators: Sync Page URL */
				__( 'Due to the number of products in the site, you will need to <a href="%s">resync</a> after applying a custom sort order.', 'elasticpress' ),
				Utils\get_sync_url()
			),
			'type'    => 'warning',
			'dismiss' => true,
		];

		return $notices;
	}

	/**
	 * Conditionally resync products after applying a custom order.
	 *
	 * @param int   $sorting_id  ID of post dragged and dropped
	 * @param array $menu_orders Post IDs and their new menu_order value
	 */
	public function action_sync_on_woocommerce_sort_single( $sorting_id, $menu_orders ) {
		$documents_per_page_sync = IndexHelper::factory()->get_index_default_per_page();
		if ( $documents_per_page_sync < count( $menu_orders ) ) {
			return;
		}

		$sync_manager = Indexables::factory()->get( 'post' )->sync_manager;
		foreach ( $menu_orders as $post_id => $order ) {
			$sync_manager->add_to_queue( $post_id );
		}
	}

	/**
	 * Add weight by date settings related to WooCommerce
	 *
	 * @param array $settings Current settings.
	 */
	public function add_weight_settings_search( $settings ) {
		?>
		<label><input name="settings[decaying_enabled]" type="radio" <?php checked( $settings['decaying_enabled'], 'disabled_only_products' ); ?> value="disabled_only_products"><?php esc_html_e( 'Disabled for product only queries', 'elasticpress' ); ?></label><br>
		<label><input name="settings[decaying_enabled]" type="radio" <?php checked( $settings['decaying_enabled'], 'disabled_includes_products' ); ?> value="disabled_includes_products"><?php esc_html_e( 'Disabled for any query that includes products', 'elasticpress' ); ?></label>
		<?php
	}

	/**
	 * Conditionally disable decaying by date based on WooCommerce Decay settings.
	 *
	 * @param bool  $is_decaying_enabled Whether decay by date is enabled or not
	 * @param array $settings            Settings
	 * @param array $args                WP_Query args
	 * @return bool
	 */
	public function maybe_disable_decaying( $is_decaying_enabled, $settings, $args ) {
		// If the decay setting isn't a WooCommerce related option, return
		if ( ! in_array( $settings['decaying_enabled'], [ 'disabled_only_products', 'disabled_includes_products' ], true ) ) {
			return $is_decaying_enabled;
		}

		// If the query is not dealing with products, return
		if ( ! isset( $args['post_type'] ) || ! in_array( 'product', (array) $args['post_type'], true ) ) {
			return $is_decaying_enabled;
		}

		$post_types = (array) $args['post_type'];

		// If set to disable decay on product-only queries and have more than one post type, return
		if ( 'disabled_only_products' === $settings['decaying_enabled'] && count( $post_types ) > 1 ) {
			return $is_decaying_enabled;
		}

		return false;
	}

	/**
	 * Translate args to ElasticPress compat format. This is the meat of what the feature does
	 *
	 * @param \WP_Query $query WP Query
	 */
	public function translate_args( $query ) {
		if ( ! $this->woocommerce->should_integrate_with_query( $query ) ) {
			return;
		}

		if ( ! $this->should_integrate_with_query( $query ) ) {
			return;
		}

		/**
		 * Make sure filters are suppressed
		 */
		$query->query['suppress_filters'] = false;
		$query->set( 'suppress_filters', false );

		$query->set( 'ep_integrate', true );

		$this->maybe_update_tax_query( $query );
		$this->maybe_update_post_type( $query );
		$this->maybe_update_meta_query( $query );

		$this->maybe_handle_top_rated( $query );

		$this->maybe_set_search_fields( $query );
		$this->maybe_set_orderby( $query );
	}

	/**
	 * Determines whether or not ES should be integrating with the provided query.
	 *
	 * A product-related query will be integrated if:
	 * * Is the main query OR is a search OR has `ep_integrate` set as true
	 * * Is querying a supported taxonomy like product attributes
	 * * Is querying a supported post type like `product`
	 *
	 * @param \WP_Query $query Query we might integrate with
	 * @return bool
	 */
	public function should_integrate_with_query( \WP_Query $query ) : bool {
		$has_ep_integrate = isset( $query->query_vars['ep_integrate'] ) && filter_var( $query->query_vars['ep_integrate'], FILTER_VALIDATE_BOOLEAN );
		$is_search        = '' !== $this->woocommerce->get_search_term( $query );

		if ( ! $query->is_main_query() && ! $is_search && ! $has_ep_integrate ) {
			return false;
		}

		/**
		 * Check for taxonomies
		 */
		$supported_taxonomies = $this->get_supported_taxonomies();
		$tax_query            = $query->get( 'tax_query', [] );
		$taxonomies_queried   = array_merge(
			array_column( $tax_query, 'taxonomy' ),
			array_keys( $query->query_vars )
		);
		if ( ! empty( array_intersect( $supported_taxonomies, $taxonomies_queried ) ) ) {
			return true;
		}

		/**
		 * Check the post type
		 */
		$supported_post_types = $this->get_supported_post_types( $query );
		$post_type            = $query->get( 'post_type', false );
		if ( ! empty( $post_type ) && ( in_array( $post_type, $supported_post_types, true ) || ( is_array( $post_type ) && ! array_diff( $post_type, $supported_post_types ) ) ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get the WooCommerce supported taxonomies (related to products.)
	 *
	 * @return array
	 */
	public function get_supported_taxonomies() : array {
		$supported_taxonomies = array(
			'product_cat',
			'product_tag',
			'product_type',
			'product_visibility',
			'product_shipping_class',
		);

		// Add in any attribute taxonomies that exist
		$attribute_taxonomies = wc_get_attribute_taxonomy_names();

		$supported_taxonomies = array_merge( $supported_taxonomies, $attribute_taxonomies );

		/**
		 * DEPRECATED. Filter supported custom taxonomies for WooCommerce integration.
		 *
		 * @param {array} $supported_taxonomies An array of default taxonomies.
		 * @hook ep_woocommerce_supported_taxonomies
		 * @since 2.3.0
		 * @return  {array} New taxonomies
		 */
		$supported_taxonomies = apply_filters_deprecated(
			'ep_woocommerce_supported_taxonomies',
			[ $supported_taxonomies ],
			'4.7.0',
			'ep_woocommerce_products_supported_taxonomies'
		);

		/**
		 * Filter supported custom taxonomies for WooCommerce product queries integration
		 *
		 * @param {array} $supported_taxonomies An array of default taxonomies.
		 * @hook ep_woocommerce_products_supported_taxonomies
		 * @since 4.7.0
		 * @return  {array} New taxonomies
		 */
		return apply_filters( 'ep_woocommerce_products_supported_taxonomies', $supported_taxonomies );
	}

	/**
	 * Get the WooCommerce supported post types (related to products.)
	 *
	 * @param \WP_Query $query The WP_Query object
	 * @return array
	 */
	public function get_supported_post_types( \WP_Query $query ) : array {
		$post_types = [ 'product_variation' ];

		$is_main_post_type_archive = $query->is_main_query() && $query->is_post_type_archive( 'product' );
		$has_ep_integrate_set_true = isset( $query->query_vars['ep_integrate'] ) && filter_var( $query->query_vars['ep_integrate'], FILTER_VALIDATE_BOOLEAN );
		if ( $is_main_post_type_archive || $has_ep_integrate_set_true ) {
			$post_types[] = 'product';
		}

		/**
		 * DEPRECATED. Expands or contracts the post_types eligible for indexing.
		 *
		 * @hook ep_woocommerce_default_supported_post_types
		 * @since 4.4.0
		 * @param {array} $post_types Post types
		 * @return {array} New post types
		 */
		$supported_post_types = apply_filters_deprecated(
			'ep_woocommerce_default_supported_post_types',
			[ $post_types ],
			'4.7.0',
			'ep_woocommerce_products_supported_post_types'
		);

		/**
		 * Expands or contracts the post_types related to products eligible for indexing.
		 *
		 * @hook ep_woocommerce_products_supported_post_types
		 * @since 4.7.0
		 * @param {array}    $supported_post_types Post types
		 * @param {WP_Query} $query                The WP_Query object
		 * @return {array} New post types
		 */
		$supported_post_types = apply_filters( 'ep_woocommerce_products_supported_post_types', $supported_post_types, $query );

		$supported_post_types = array_intersect(
			$supported_post_types,
			Indexables::factory()->get( 'post' )->get_indexable_post_types()
		);

		return $supported_post_types;
	}

	/**
	 * If needed, update the `'tax_query'` parameter
	 *
	 * If a supported taxonomy was added in the root of the args array,
	 * this method moves it to the `'tax_query'`
	 *
	 * @param \WP_Query $query The WP_Query object
	 */
	protected function maybe_update_tax_query( \WP_Query $query ) {
		$supported_taxonomies = $this->get_supported_taxonomies();
		$tax_query            = $query->get( 'tax_query', [] );

		foreach ( $supported_taxonomies as $taxonomy ) {
			$term = $query->get( $taxonomy, false );

			if ( ! empty( $term ) ) {
				$tax_query[] = array(
					'taxonomy' => $taxonomy,
					'field'    => 'slug',
					'terms'    => (array) $term,
				);
			}
		}

		$query->set( 'tax_query', $tax_query );
	}

	/**
	 * Set the post_type to product if empty
	 *
	 * @param \WP_Query $query The WP_Query object
	 */
	protected function maybe_update_post_type( \WP_Query $query ) {
		$post_type = $query->get( 'post_type', false );

		if ( empty( $post_type ) ) {
			$query->set( 'post_type', 'product' );
		}
	}

	/**
	 * If the `'meta_key'` or `'meta_value'` parameters were set,
	 * move them to `'meta_query'`
	 *
	 * @param \WP_Query $query The WP_Query object
	 */
	protected function maybe_update_meta_query( \WP_Query $query ) {
		/**
		 * Handle meta queries
		 */
		$meta_query = $query->get( 'meta_query', [] );
		$meta_key   = $query->get( 'meta_key', false );
		$meta_value = $query->get( 'meta_value', false );

		if ( ! empty( $meta_key ) && ! empty( $meta_value ) ) {
			$meta_query[] = array(
				'key'   => $meta_key,
				'value' => $meta_value,
			);

			$query->set( 'meta_query', $meta_query );
		}
	}

	/**
	 * Handle the WC Top Rated Widget
	 *
	 * @param \WP_Query $query The WP_Query object
	 * @return void
	 */
	protected function maybe_handle_top_rated( \WP_Query $query ) {
		if ( ! has_filter( 'posts_clauses', array( WC()->query, 'order_by_rating_post_clauses' ) ) ) {
			return;
		}

		remove_filter( 'posts_clauses', array( WC()->query, 'order_by_rating_post_clauses' ) );
		$query->set( 'orderby', 'meta_value_num' );
		$query->set( 'meta_key', '_wc_average_rating' );
	}

	/**
	 * If the query has a search term and the weighting dashboard is not
	 * available, add the needed fields
	 *
	 * @param \WP_Query $query The WP_Query
	 * @return \WP_Query
	 */
	protected function maybe_set_search_fields( \WP_Query $query ) {
		$search_term = $this->woocommerce->get_search_term( $query );
		if ( empty( $search_term ) ) {
			return $query;
		}

		$post_type = $query->get( 'post_type', false );
		if ( 'product' !== $post_type || ! defined( 'EP_IS_NETWORK' ) || ! EP_IS_NETWORK ) {
			return;
		}

		$search_fields = $query->get( 'search_fields', array( 'post_title', 'post_content', 'post_excerpt' ) );

		// Remove author_name from this search.
		$search_fields = $this->remove_author( $search_fields );

		$search_fields['meta']       = ( ! empty( $search_fields['meta'] ) ) ? $search_fields['meta'] : [];
		$search_fields['taxonomies'] = ( ! empty( $search_fields['taxonomies'] ) ) ? $search_fields['taxonomies'] : [];

		$search_fields['meta']       = array_merge( $search_fields['meta'], array( '_sku' ) );
		$search_fields['taxonomies'] = array_merge( $search_fields['taxonomies'], array( 'category', 'post_tag', 'product_tag', 'product_cat' ) );

		$query->set( 'search_fields', $search_fields );
	}

	/**
	 * Remove the author_name from search fields.
	 *
	 * @param array $search_fields Array of search fields.
	 * @return array
	 */
	public function remove_author( array $search_fields ) : array {
		foreach ( $search_fields as $field_key => $field ) {
			if ( 'author_name' === $field ) {
				unset( $search_fields[ $field_key ] );
			}
		}

		return $search_fields;
	}

	/**
	 * If needed, set the `'order'` and `'orderby'` parameters
	 *
	 * @param \WP_Query $query The WP_Query object
	 */
	protected function maybe_set_orderby( \WP_Query $query ) {
		$search_term = $this->woocommerce->get_search_term( $query );

		if ( empty( $search_term ) ) {
			/**
			 * For default sorting by popularity (total_sales) and rating
			 * WooCommerce doesn't set the orderby correctly.
			 * These lines will check the meta_key and correct the orderby based on that.
			 * And this won't run in search result and only run in main query
			 */
			$meta_key = $query->get( 'meta_key', false );
			if ( $meta_key && $query->is_main_query() ) {
				switch ( $meta_key ) {
					case 'total_sales':
						$query->set( 'orderby', $this->get_orderby_meta_mapping( 'total_sales' ) );
						$query->set( 'order', 'DESC' );
						break;
					case '_wc_average_rating':
						$query->set( 'orderby', $this->get_orderby_meta_mapping( '_wc_average_rating' ) );
						$query->set( 'order', 'DESC' );
						break;
				}
			}
		}

		/**
		 * Set orderby and order for price/popularity when GET param not set
		 */
		$orderby = $query->get( 'orderby', null );
		if ( $orderby && in_array( $orderby, [ 'price', 'popularity' ], true ) ) {
			switch ( $orderby ) {
				case 'price':
					$query->set( 'orderby', $this->get_orderby_meta_mapping( '_price' ) );
					$query->set( 'order', $query->get( 'order' ) );
					break;
				case 'popularity':
					$query->set( 'orderby', $this->get_orderby_meta_mapping( 'total_sales' ) );
					$query->set( 'order', 'DESC' );
					break;
			}
		}

		/**
		 * Set orderby from GET param
		 * Also make sure the orderby param affects only the main query
		 */
		if ( ! empty( $_GET['orderby'] ) && $query->is_main_query() ) { // phpcs:ignore WordPress.Security.NonceVerification
			$orderby = sanitize_text_field( wp_unslash( $_GET['orderby'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			switch ( $orderby ) { // phpcs:ignore WordPress.Security.NonceVerification
				case 'popularity':
					$query->set( 'orderby', $this->get_orderby_meta_mapping( 'total_sales' ) );
					$query->set( 'order', 'DESC' );
					break;
				case 'price':
					$query->set( 'order', $query->get( 'order', 'ASC' ) );
					$query->set( 'orderby', $this->get_orderby_meta_mapping( '_price' ) );
					break;
				case 'price-desc':
					$query->set( 'order', 'DESC' );
					$query->set( 'orderby', $this->get_orderby_meta_mapping( '_price' ) );
					break;
				case 'rating':
					$query->set( 'orderby', $this->get_orderby_meta_mapping( '_wc_average_rating' ) );
					$query->set( 'order', 'DESC' );
					break;
				case 'date':
				case 'title':
				case 'ID':
					$query->set( 'orderby', $this->get_orderby_meta_mapping( $orderby ) );
					break;
				case 'sku':
					$query->set( 'orderby', $this->get_orderby_meta_mapping( '_sku' ) );
					break;
				default:
					$query->set( 'orderby', $this->get_orderby_meta_mapping( 'menu_order' ) ); // Order by menu and title.
			}
		}
	}

	/**
	 * Fetch the ES related meta mapping for orderby
	 *
	 * @param array $meta_key The meta key to get the mapping for.
	 * @return string The mapped meta key.
	 */
	public function get_orderby_meta_mapping( $meta_key ) : string {
		/**
		 * Filter WooCommerce to Elasticsearch meta mapping
		 *
		 * @hook orderby_meta_mapping
		 * @param  {array} $mapping Meta mapping
		 * @return  {array} New mapping
		 */
		$mapping = apply_filters(
			'orderby_meta_mapping',
			array(
				'ID'                 => 'ID',
				'title'              => 'title date',
				'menu_order'         => 'menu_order title date',
				'menu_order title'   => 'menu_order title date',
				'total_sales'        => 'meta.total_sales.double date',
				'_wc_average_rating' => 'meta._wc_average_rating.double date',
				'_price'             => 'meta._price.double date',
				'_sku'               => 'meta._sku.value.sortable date',
			)
		);

		if ( isset( $mapping[ $meta_key ] ) ) {
			return $mapping[ $meta_key ];
		}

		return 'date';
	}

	/**
	 * Add taxonomies that should be woocommerce attributes.
	 *
	 * @param array $attribute_taxonomies  Attribute taxonomies.
	 * @return array $attribute_taxonomies Attribute taxonomies.
	 */
	public function add_taxonomy_attributes( array $attribute_taxonomies ) : array {
		$all_attr_taxonomies = wc_get_attribute_taxonomies();

		foreach ( $all_attr_taxonomies as $attr_taxonomy ) {
			$attribute_taxonomies[ $attr_taxonomy->attribute_name ] = wc_attribute_taxonomy_name( $attr_taxonomy->attribute_name );
		}
		return $attribute_taxonomies;
	}

	/**
	 * Add weight by date settings related to WooCommerce
	 *
	 * @since 5.0.0
	 * @param array  $settings_schema Settings schema
	 * @param string $feature_slug    Feature slug
	 * @return array New settings schema
	 */
	public function add_weight_settings_search_schema( $settings_schema, $feature_slug ) {
		if ( 'search' !== $feature_slug ) {
			return $settings_schema;
		}

		foreach ( $settings_schema as &$setting_schema ) {
			if ( 'decaying_enabled' !== $setting_schema['key'] ) {
				continue;
			}

			$setting_schema['options'] = array_merge(
				$setting_schema['options'],
				[
					[
						'label' => __( 'Disabled for product only queries', 'elasticpress' ),
						'value' => 'disabled_only_products',
					],
					[
						'label' => __( 'Disabled for any query that includes products', 'elasticpress' ),
						'value' => 'disabled_includes_products',
					],
				]
			);
		}

		return $settings_schema;
	}
}
