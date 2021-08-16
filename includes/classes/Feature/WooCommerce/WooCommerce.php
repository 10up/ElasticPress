<?php
/**
 * ElasticPress WooCommerce feature
 *
 * @since  2.1
 * @package elasticpress
 */

namespace ElasticPress\Feature\WooCommerce;

use ElasticPress\Feature as Feature;
use ElasticPress\FeatureRequirementsStatus as FeatureRequirementsStatus;
use ElasticPress\Indexables as Indexables;
use ElasticPress\Utils as Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WooCommerce feature class
 */
class WooCommerce extends Feature {
	/**
	 * Initialize feature setting it's config
	 *
	 * @since  3.0
	 */
	public function __construct() {
		$this->slug = 'woocommerce';

		$this->title = esc_html__( 'WooCommerce', 'elasticpress' );

		$this->requires_install_reindex = true;

		parent::__construct();
	}

	/**
	 * Index Woocommerce meta
	 *
	 * @param   array $meta Existing post meta.
	 * @param   array $post Post arguments array.
	 * @since   2.1
	 * @return  array
	 */
	public function whitelist_meta_keys( $meta, $post ) {
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
					'_customer_user',
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
					'_order_key',
					'_billing_company',
					'_billing_address_1',
					'_billing_address_2',
					'_billing_city',
					'_billing_postcode',
					'_billing_country',
					'_billing_state',
					'_billing_email',
					'_billing_phone',
					'_shipping_address_1',
					'_shipping_address_2',
					'_shipping_city',
					'_shipping_postcode',
					'_shipping_country',
					'_shipping_state',
					'_billing_last_name',
					'_billing_first_name',
					'_shipping_first_name',
					'_shipping_last_name',
				)
			)
		);
	}

	/**
	 * Prevent order fields search meta query
	 *
	 * @since  2.1
	 */
	public function shop_order_search_fields() {
		return [];
	}

	/**
	 * Make sure all loop shop post ins are IDS. We have to pass post objects here since we override
	 * the fields=>id query for the layered filter nav query
	 *
	 * @param   array $posts Post object array.
	 * @since   2.1
	 * @return  array
	 */
	public function convert_post_object_to_id( $posts ) {
		$new_posts = [];

		foreach ( $posts as $post ) {
			if ( is_object( $post ) ) {
				$new_posts[] = $post->ID;
			} else {
				$new_posts[] = $post;
			}
		}

		return $new_posts;
	}

	/**
	 * Index Woocommerce taxonomies
	 *
	 * @param   array $taxonomies Index taxonomies array.
	 * @param   array $post Post properties array.
	 * @since   2.1
	 * @return  array
	 */
	public function whitelist_taxonomies( $taxonomies, $post ) {
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
	 * Disallow duplicated ES queries on Orders page.
	 *
	 * @since 2.4
	 *
	 * @param array    $value Original filter values.
	 * @param WP_Query $query WP_Query
	 *
	 * @return array
	 */
	public function disallow_duplicated_query( $value, $query ) {
		global $pagenow;

		/**
		 * Make sure we're on edit.php in admin dashboard.
		 */
		if ( 'edit.php' !== $pagenow || ! is_admin() || 'shop_order' !== $query->get( 'post_type' ) ) {
			return $value;
		}

		/**
		 * Check if EP API request was already done. If request was sent return its results.
		 */
		if ( isset( $query->elasticsearch_success ) && $query->elasticsearch_success ) {
			return $query->posts;
		}

		return $value;

	}

	/**
	 * Translate args to ElasticPress compat format. This is the meat of what the feature does
	 *
	 * @param  WP_Query $query WP Query
	 * @since  2.1
	 */
	public function translate_args( $query ) {
		if ( ! $this->should_integrate_with_query( $query ) ) {
			return;
		}

		// Flag to check and make sure we are in a WooCommerce specific query
		$integrate = false;

		/**
		 * Force ElasticPress if we are querying WC taxonomy
		 */
		$tax_query = $query->get( 'tax_query', [] );

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
		 * Filter supported custom taxonomies for WooCommerce integration
		 *
		 * @param {array} $supported_taxonomies An array of default taxonomies.
		 * @hook ep_woocommerce_supported_taxonomies
		 * @since 2.3.0
		 * @return  {array} New taxonomies
		 */
		$supported_taxonomies = apply_filters( 'ep_woocommerce_supported_taxonomies', $supported_taxonomies );

		if ( ! empty( $tax_query ) ) {

			/**
			 * First check if already set taxonomies are supported WC taxes
			 */
			foreach ( $tax_query as $taxonomy_array ) {
				if ( isset( $taxonomy_array['taxonomy'] ) && in_array( $taxonomy_array['taxonomy'], $supported_taxonomies, true ) ) {
					$integrate = true;
				}
			}
		}

		/**
		 * Next check if any taxonomies are in the root of query vars (shorthand form)
		 */
		foreach ( $supported_taxonomies as $taxonomy ) {
			$term = $query->get( $taxonomy, false );

			if ( ! empty( $term ) ) {
				$integrate = true;

				$tax_query[] = array(
					'taxonomy' => $taxonomy,
					'field'    => 'slug',
					'terms'    => (array) $term,
				);
			}
		}

		/**
		 * Force ElasticPress if product post type query
		 */
		$post_type = $query->get( 'post_type', false );

		// Act only on a defined subset of all indexable post types here
		$supported_post_types = array_intersect(
			array(
				'product',
				'shop_order',
				'shop_order_refund',
				'product_variation',
			),
			Indexables::factory()->get( 'post' )->get_indexable_post_types()
		);

		// For orders it queries an array of shop_order and shop_order_refund post types, hence an array_diff
		if ( ! empty( $post_type ) && ( in_array( $post_type, $supported_post_types, true ) || ( is_array( $post_type ) && ! array_diff( $post_type, $supported_post_types ) ) ) ) {
			$integrate = true;
		}

		/**
		 * If we have a WooCommerce specific query, lets hook it to ElasticPress and make the query ElasticSearch friendly
		 */
		if ( $integrate ) {
			// Set tax_query again since we may have added things
			$query->set( 'tax_query', $tax_query );

			// Default to product if no post type is set
			if ( empty( $post_type ) ) {
				$post_type = 'product';
				$query->set( 'post_type', 'product' );
			}

			// Handles the WC Top Rated Widget
			if ( has_filter( 'posts_clauses', array( WC()->query, 'order_by_rating_post_clauses' ) ) ) {
				remove_filter( 'posts_clauses', array( WC()->query, 'order_by_rating_post_clauses' ) );
				$query->set( 'orderby', 'meta_value_num' );
				$query->set( 'meta_key', '_wc_average_rating' );
			}

			/**
			 * WordPress have to be version 4.6 or newer to have "fields" support
			 * since it requires the "posts_pre_query" filter.
			 *
			 * @see WP_Query::get_posts
			 */
			$fields = $query->get( 'fields', false );
			if ( ! version_compare( get_bloginfo( 'version' ), '4.6', '>=' ) && ( 'ids' === $fields || 'id=>parent' === $fields ) ) {
				$query->set( 'fields', 'default' );
			}

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

			/**
			 * Make sure filters are suppressed
			 */
			$query->query['suppress_filters'] = false;
			$query->set( 'suppress_filters', false );

			$s = $query->get( 's' );

			$query->query_vars['ep_integrate'] = true;
			$query->query['ep_integrate']      = true;

			if ( ! empty( $s ) ) {
				$query->set( 'orderby', false ); // Just order by relevance.

				/**
				 * Default order when doing search in Woocommerce is 'ASC'
				 * These lines will change it to 'DESC' as we want to most relevant result
				 */
				if ( empty( $_GET['orderby'] ) && $query->is_main_query() ) { // phpcs:ignore WordPress.Security.NonceVerification
					$query->set( 'order', 'DESC' );
				}

				// Search query
				if ( 'shop_order' === $post_type ) {
					$search_fields = $query->get( 'search_fields', array( 'post_title', 'post_content', 'post_excerpt' ) );

					$search_fields['meta'] = array_map(
						'wc_clean',
						/**
						 * Filter shop order fields to search for WooCommerce
						 *
						 * @hook shop_order_search_fields
						 * @param  {array} $fields Shop order fields
						 * @return  {array} New fields
						 */
						apply_filters(
							'shop_order_search_fields',
							array(
								'_order_key',
								'_billing_company',
								'_billing_address_1',
								'_billing_address_2',
								'_billing_city',
								'_billing_postcode',
								'_billing_country',
								'_billing_state',
								'_billing_email',
								'_billing_phone',
								'_shipping_address_1',
								'_shipping_address_2',
								'_shipping_city',
								'_shipping_postcode',
								'_shipping_country',
								'_shipping_state',
								'_billing_last_name',
								'_billing_first_name',
								'_shipping_first_name',
								'_shipping_last_name',
								'_items',
							)
						)
					);

					$query->set( 'search_fields', $search_fields );
				} elseif ( 'product' === $post_type && defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
					$search_fields = $query->get( 'search_fields', array( 'post_title', 'post_content', 'post_excerpt' ) );

					// Remove author_name from this search.
					$search_fields = $this->remove_author( $search_fields );

					foreach ( $search_fields as $field_key => $field ) {
						if ( 'author_name' === $field ) {
							unset( $search_fields[ $field_key ] );
						}
					}

					$search_fields['meta']       = ( ! empty( $search_fields['meta'] ) ) ? $search_fields['meta'] : [];
					$search_fields['taxonomies'] = ( ! empty( $search_fields['taxonomies'] ) ) ? $search_fields['taxonomies'] : [];

					$search_fields['meta']       = array_merge( $search_fields['meta'], array( '_sku' ) );
					$search_fields['taxonomies'] = array_merge( $search_fields['taxonomies'], array( 'category', 'post_tag', 'product_tag', 'product_cat' ) );

					$query->set( 'search_fields', $search_fields );
				}
			} else {
				/**
				 * For default sorting by popularity (total_sales) and rating
				 * Woocommerce doesn't set the orderby correctly.
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
			if ( isset( $query->query_vars['orderby'], $query->query_vars['order'] ) && $query->is_main_query() ) {
				switch ( $query->query_vars['orderby'] ) {
					case 'price':
						$query->set( 'order', $query->query_vars['order'] );
						$query->set( 'orderby', $this->get_orderby_meta_mapping( '_price' ) );
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

				switch ( $_GET['orderby'] ) { // phpcs:ignore WordPress.Security.NonceVerification
					case 'popularity':
						$query->set( 'orderby', $this->get_orderby_meta_mapping( 'total_sales' ) );
						$query->set( 'order', 'DESC' );
						break;
					case 'price':
						$query->set( 'order', 'ASC' );
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
						$query->set( 'orderby', $this->get_orderby_meta_mapping( 'date' ) );
						break;
					case 'ID':
						$query->set( 'orderby', $this->get_orderby_meta_mapping( 'ID' ) );
						break;
					default:
						$query->set( 'orderby', $this->get_orderby_meta_mapping( 'menu_order' ) ); // Order by menu and title.
				}
			}
		}
	}

	/**
	 * Fetch the ES related meta mapping for orderby
	 *
	 * @param array $meta_key The meta key to get the mapping for.
	 * @since  2.1
	 * @return string    The mapped meta key.
	 */
	public function get_orderby_meta_mapping( $meta_key ) {
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
				'menu_order'         => 'menu_order title date',
				'menu_order title'   => 'menu_order title date',
				'total_sales'        => 'meta.total_sales.double date',
				'_wc_average_rating' => 'meta._wc_average_rating.double date',
				'_price'             => 'meta._price.double date',
			)
		);

		if ( isset( $mapping[ $meta_key ] ) ) {
			return $mapping[ $meta_key ];
		}

		return 'date';
	}

	/**
	 * Make search coupons don't go through ES
	 *
	 * @param  bool     $enabled Coupons enabled or not
	 * @param  WP_Query $query WP Query
	 * @since  2.1
	 * @return bool
	 */
	public function blacklist_coupons( $enabled, $query ) {
		if ( method_exists( $query, 'get' ) && 'shop_coupon' === $query->get( 'post_type' ) ) {
			return false;
		}

		return $enabled;
	}

	/**
	 * Allow order creations on the front end to get synced
	 *
	 * @since  2.1
	 * @param  bool $override Original order perms check value
	 * @param  int  $post_id Post ID
	 * @return bool
	 */
	public function bypass_order_permissions_check( $override, $post_id ) {
		if ( 'shop_order' === get_post_type( $post_id ) ) {
			return true;
		}

		return $override;
	}

	/**
	 * Sets woocommerce meta search fields to an empty array if we are integrating the main query with ElasticSearch
	 *
	 * Woocommerce calls this action as part of its own callback on parse_query. We add this filter only if the query
	 * is integrated with ElasticSearch.
	 * If we were to always return array() on this filter, we'd break admin searches when WooCommerce module is activated
	 * without the Protected Content Module
	 *
	 * @param \WP_Query $query Current query
	 */
	public function maybe_hook_woocommerce_search_fields( $query ) {
		global $pagenow, $wp;

		if ( ! $this->should_integrate_with_query( $query ) ) {
			return;
		}

		if ( 'edit.php' !== $pagenow || empty( $wp->query_vars['s'] ) || 'shop_order' !== $wp->query_vars['post_type'] || ! isset( $_GET['s'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		add_filter( 'woocommerce_shop_order_search_fields', [ $this, 'shop_order_search_fields' ], 9999 );
	}

	/**
	 * Enhance WooCommerce search order by order id, email, phone number, name, etc..
	 * What this function does:
	 * 1. Reverse the woocommerce shop_order_search_custom_fields query
	 * 2. If the search key is integer and it is an Order Id, just query with post__in
	 * 3. If the search key is integer but not an order id ( might be phone number ), use ES to find it
	 *
	 * @param WP_Query $wp WP Query
	 * @since  2.3
	 */
	public function search_order( $wp ) {
		if ( ! $this->should_integrate_with_query( $wp ) ) {
			return;
		}

		global $pagenow;
		if ( 'edit.php' !== $pagenow || empty( $wp->query_vars['post_type'] ) || 'shop_order' !== $wp->query_vars['post_type'] ||
			( empty( $wp->query_vars['s'] ) && empty( $wp->query_vars['shop_order_search'] ) ) ) {
			return;
		}

		$search_key_safe = str_replace( array( 'Order #', '#' ), '', wc_clean( $_GET['s'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$order_id        = absint( $search_key_safe );

		/**
		 * Order ID 0 is not valid value.
		 */
		$order = $order_id > 0 ? wc_get_order( $order_id ) : false;

		// If the order doesn't exist, fallback to other fields
		if ( ! $order ) {
			unset( $wp->query_vars['post__in'] );
			$wp->query_vars['s'] = $search_key_safe;
		} else {
			// we found the order. don't query ES
			unset( $wp->query_vars['s'] );
			$wp->query_vars['post__in'] = array( absint( $search_key_safe ) );
		}
	}

	/**
	 * Add order items as a searchable string.
	 *
	 * This mimics how WooCommerce currently does in the order_itemmeta
	 * table. They combine the titles of the products and put them in a
	 * meta field called "Items".
	 *
	 * @since 2.4
	 *
	 * @param array      $post_args Post arguments
	 * @param string|int $post_id Post id
	 *
	 * @return array
	 */
	public function add_order_items_search( $post_args, $post_id ) {
		// Make sure it is only WooCommerce orders we touch.
		if ( 'shop_order' !== $post_args['post_type'] ) {
			return $post_args;
		}

		$post_indexable = Indexables::factory()->get( 'post' );

		// Get order items.
		$order     = wc_get_order( $post_id );
		$item_meta = [];
		foreach ( $order->get_items() as $delta => $product_item ) {
			// WooCommerce 3.x uses WC_Order_Item_Product instance while 2.x an array
			if ( is_object( $product_item ) && method_exists( $product_item, 'get_name' ) ) {
				$item_meta['_items'][] = $product_item->get_name( 'edit' );
			} elseif ( is_array( $product_item ) && isset( $product_item['name'] ) ) {
				$item_meta['_items'][] = $product_item['name'];
			}
		}

		// Prepare order items.
		$item_meta['_items'] = empty( $item_meta['_items'] ) ? '' : implode( '|', $item_meta['_items'] );
		$post_args['meta']   = array_merge( $post_args['meta'], $post_indexable->prepare_meta_types( $item_meta ) );

		return $post_args;
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
	 * @since 3.x
	 *
	 * @param array  $fields    Current weighting fields.
	 * @param string $post_type Current post type.
	 * @return array            New fields.
	 */
	public function add_product_attributes_to_weighting( $fields, $post_type ) {
		if ( 'product' === $post_type ) {
			if ( ! empty( $fields['attributes']['children']['author_name'] ) ) {
				unset( $fields['attributes']['children']['author_name'] );
			}

			$sku_key = 'meta._sku.value';

			$fields['attributes']['children'][ $sku_key ] = array(
				'key'   => $sku_key,
				'label' => __( 'SKU', 'elasticpress' ),
			);
		}
		return $fields;
	}

	/**
	 * Add WooCommerce Fields to the default values of the Weighting Dashboard.
	 *
	 * @since 3.x
	 *
	 * @param array  $defaults  Default values for the post type.
	 * @param string $post_type Current post type.
	 * @return array
	 */
	public function add_product_default_post_type_weights( $defaults, $post_type ) {
		if ( 'product' === $post_type ) {
			if ( ! empty( $defaults['author_name'] ) ) {
				unset( $defaults['author_name'] );
			}

			$defaults['meta._sku.value'] = array(
				'enabled' => true,
				'weight'  => 1,
			);
		}
		return $defaults;
	}

	/**
	 * Add WC post type to autosuggest
	 *
	 * @param array $post_types Array of post types (e.g. post, page).
	 * @since  2.6
	 * @return array
	 */
	public function suggest_wc_add_post_type( $post_types ) {
		if ( ! in_array( 'product', $post_types, true ) ) {
			$post_types[] = 'product';
		}

		return $post_types;
	}

	/**
	 * Setup all feature filters
	 *
	 * @since  2.1
	 */
	public function setup() {
		if ( function_exists( 'WC' ) ) {
			add_action( 'ep_formatted_args', [ $this, 'price_filter' ], 10, 3 );
			add_filter( 'ep_sync_insert_permissions_bypass', [ $this, 'bypass_order_permissions_check' ], 10, 2 );
			add_filter( 'ep_elasticpress_enabled', [ $this, 'blacklist_coupons' ], 10, 2 );
			add_filter( 'ep_prepare_meta_allowed_protected_keys', [ $this, 'whitelist_meta_keys' ], 10, 2 );
			add_filter( 'woocommerce_layered_nav_query_post_ids', [ $this, 'convert_post_object_to_id' ], 10, 4 );
			add_filter( 'woocommerce_unfiltered_product_ids', [ $this, 'convert_post_object_to_id' ], 10, 4 );
			add_filter( 'ep_sync_taxonomies', [ $this, 'whitelist_taxonomies' ], 10, 2 );
			add_filter( 'ep_post_sync_args_post_prepare_meta', [ $this, 'add_order_items_search' ], 20, 2 );
			add_action( 'pre_get_posts', [ $this, 'translate_args' ], 11, 1 );
			add_action( 'ep_wp_query_search_cached_posts', [ $this, 'disallow_duplicated_query' ], 10, 2 );
			add_action( 'parse_query', [ $this, 'maybe_hook_woocommerce_search_fields' ], 1 );
			add_action( 'parse_query', [ $this, 'search_order' ], 11 );
			add_filter( 'ep_term_suggest_post_type', [ $this, 'suggest_wc_add_post_type' ] );
			add_filter( 'ep_facet_include_taxonomies', [ $this, 'add_product_attributes' ] );
			add_filter( 'ep_weighting_fields_for_post_type', [ $this, 'add_product_attributes_to_weighting' ], 10, 2 );
			add_filter( 'ep_weighting_default_post_type_weights', [ $this, 'add_product_default_post_type_weights' ], 10, 2 );
		}
	}

	/**
	 * Output feature box summary
	 *
	 * @since 2.1
	 */
	public function output_feature_box_summary() {
		?>
		<p><?php esc_html_e( '“I want a cotton, woman’s t-shirt, for under $15 that’s in stock.” Faceted product browsing strains servers and increases load times. Your buyers can find the perfect product quickly, and buy it quickly.', 'elasticpress' ); ?></p>
		<?php
	}

	/**
	 * Output feature box long
	 *
	 * @since 2.1
	 */
	public function output_feature_box_long() {
		?>
		<p><?php esc_html_e( 'Most caching and performance tools can’t keep up with the nearly infinite ways your visitors might filter or navigate your products. No matter how many products, filters, or customers you have, ElasticPress will keep your online store performing quickly. If used in combination with the Protected Content feature, ElasticPress will also accelerate order searches and back end product management.', 'elasticpress' ); ?></p>
		<?php
	}

	/**
	 * Remove the author_name from search fields.
	 *
	 * @param array $search_fields Array of search fields.
	 * @since  3.0
	 * @return array
	 */
	public function remove_author( $search_fields ) {
		foreach ( $search_fields as $field_key => $field ) {
			if ( 'author_name' === $field ) {
				unset( $search_fields[ $field_key ] );
			}
		}

		return $search_fields;
	}

	/**
	 * Determine WC feature reqs status
	 *
	 * @since  2.2
	 * @return EP_Feature_Requirements_Status
	 */
	public function requirements_status() {
		$status = new FeatureRequirementsStatus( 0 );

		if ( ! class_exists( 'WooCommerce' ) ) {
			$status->code    = 2;
			$status->message = esc_html__( 'WooCommerce not installed.', 'elasticpress' );
		}

		return $status;
	}

	/**
	 * Modifies main query to allow filtering by price with WooCommerce "Filter by price" widget.
	 *
	 * @param  array    $args ES args
	 * @param  array    $query_args WP_Query args
	 * @param  WP_Query $query WP_Query object
	 * @since  3.2
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

		if ( $query->is_search() ) {
			/**
			 * This logic is iffy but the WC price filter widget is not intended for use with search anyway
			 */
			$old_query = $args['query']['bool'];
			unset( $args['query']['bool']['should'] );

			if ( ! empty( $_GET['min_price'] ) ) {
				$args['query']['bool']['must'][0]['range']['meta._price.long']['gte'] = $_GET['min_price'];
			}

			if ( ! empty( $_GET['max_price'] ) ) {
				$args['query']['bool']['must'][0]['range']['meta._price.long']['lte'] = $_GET['max_price'];
			}

			$args['query']['bool']['must'][0]['range']['meta._price.long']['boost'] = 2.0;
			$args['query']['bool']['must'][1]['bool']                               = $old_query;
		} else {
			unset( $args['query']['match_all'] );

			$args['query']['range']['meta._price.long']['gte'] = ! empty( $_GET['min_price'] ) ? $_GET['min_price'] : 0;

			if ( ! empty( $_GET['min_price'] ) ) {
				$args['query']['range']['meta._price.long']['gte'] = $_GET['min_price'];
			}

			if ( ! empty( $_GET['max_price'] ) ) {
				$args['query']['range']['meta._price.long']['lte'] = $_GET['max_price'];
			}

			$args['query']['range']['meta._price.long']['boost'] = 2.0;
		}
		// phpcs:enable WordPress.Security.NonceVerification

		return $args;
	}

	/**
	 * Determines whether or not ES should be integrating with the provided query
	 *
	 * @param \WP_Query $query Query we might integrate with
	 *
	 * @return bool
	 */
	protected function should_integrate_with_query( $query ) {
		// Lets make sure this doesn't interfere with the CLI
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return false;
		}

		/**
		 * Filter to skip WP Query integration
		 *
		 * @hook ep_skip_query_integration
		 * @param  {bool} $skip True to skip
		 * @param  {WP_Query} $query WP Query to evaluate
		 * @return  {bool} New skip value
		 */
		if ( apply_filters( 'ep_skip_query_integration', false, $query ) ||
			( isset( $query->query_vars['ep_integrate'] ) && false === $query->query_vars['ep_integrate'] ) ) {
			return false;
		}

		if ( ! Utils\is_integrated_request( $this->slug ) ) {
			return false;
		}

		$product_name = $query->get( 'product', false );

		$post_parent = $query->get( 'post_parent', false );

		/**
		 * Do nothing for single product queries
		 */
		if ( ! empty( $product_name ) || $query->is_single() ) {
			return false;
		}

		/**
		 * ElasticPress does not yet support post_parent queries
		 */
		if ( ! empty( $post_parent ) ) {
			return false;
		}

		/**
		 * If this is just a preview, let's not use Elasticsearch.
		 */
		if ( $query->get( 'preview', false ) ) {
			return false;
		}

		/**
		 * Cant hook into WC API yet
		 */
		if ( defined( 'WC_API_REQUEST' ) && WC_API_REQUEST ) {
			return false;
		}

		return true;
	}
}
