<?php
/**
 * ElasticPress WooCommerce feature
 *
 * @since  2.1
 * @package elasticpress
 */

namespace ElasticPress\Feature\WooCommerce;

use ElasticPress\Feature;
use ElasticPress\FeatureRequirementsStatus;
use ElasticPress\Indexables;
use ElasticPress\IndexHelper;
use ElasticPress\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WooCommerce feature class
 */
class WooCommerce extends Feature {
	/**
	 * If enabled, receive the OrdersAutosuggest object instance
	 *
	 * @since 4.7.0
	 * @var null|OrdersAutosuggest
	 */
	public $orders_autosuggest = null;

	/**
	 * Receive the Products object instance
	 *
	 * @since 4.7.0
	 * @var null|Products
	 */
	public $products = null;

	/**
	 * Receive the Orders object instance
	 *
	 * @since 4.5.0
	 * @var null|Orders
	 */
	public $orders = null;

	/**
	 * Initialize feature setting it's config
	 *
	 * @since  3.0
	 */
	public function __construct() {
		$this->slug = 'woocommerce';

		$this->title = esc_html__( 'WooCommerce', 'elasticpress' );

		$this->summary = __( '“I want a cotton, woman’s t-shirt, for under $15 that’s in stock.” Faceted product browsing strains servers and increases load times. Your buyers can find the perfect product quickly, and buy it quickly.', 'elasticpress' );

		$this->docs_url = __( 'https://elasticpress.zendesk.com/hc/en-us/articles/360050447492-Configuring-ElasticPress-via-the-Plugin-Dashboard#woocommerce', 'elasticpress' );

		$this->requires_install_reindex = true;

		$this->setting_requires_install_reindex = 'orders';

		$this->available_during_installation = true;

		$this->default_settings = [
			'orders' => '0',
		];

		$this->orders             = new Orders( $this );
		$this->products           = new Products( $this );
		$this->orders_autosuggest = new OrdersAutosuggest();

		parent::__construct();
	}

	/**
	 * Setup all feature filters
	 *
	 * @since  2.1
	 */
	public function setup() {
		if ( ! function_exists( 'WC' ) ) {
			return;
		}

		$this->products->setup();
		$this->orders->setup();

		add_filter( 'ep_integrate_search_queries', [ $this, 'disallow_coupons' ], 10, 2 );

		// These hooks are deprecated and will be removed in an upcoming major version of ElasticPress
		add_filter( 'woocommerce_layered_nav_query_post_ids', [ $this, 'convert_post_object_to_id' ], 10, 4 );
		add_filter( 'woocommerce_unfiltered_product_ids', [ $this, 'convert_post_object_to_id' ], 10, 4 );
		add_action( 'ep_wp_query_search_cached_posts', [ $this, 'disallow_duplicated_query' ], 10, 2 );

		add_action( 'pre_get_posts', [ $this, 'translate_args' ], 11, 1 );

		// Orders Autosuggest feature.
		if ( $this->is_orders_autosuggest_enabled() ) {
			$this->orders_autosuggest->setup();
		}
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
		$post_types = array(
			'shop_order',
			'shop_order_refund',
			'product_variation',
		);

		$is_main_post_type_archive = $query->is_main_query() && $query->is_post_type_archive( 'product' );
		$has_ep_integrate_set_true = isset( $query->query_vars['ep_integrate'] ) && filter_var( $query->query_vars['ep_integrate'], FILTER_VALIDATE_BOOLEAN );
		if ( $is_main_post_type_archive || $has_ep_integrate_set_true ) {
			$post_types[] = 'product';
		}

		/**
		 * Expands or contracts the post_types eligible for indexing.
		 *
		 * @hook ep_woocommerce_default_supported_post_types
		 * @since 4.4.0
		 * @param  {array} $post_types Post types
		 * @return  {array} New post types
		 */
		$supported_post_types = apply_filters( 'ep_woocommerce_default_supported_post_types', $post_types );

		$supported_post_types = array_intersect(
			$supported_post_types,
			Indexables::factory()->get( 'post' )->get_indexable_post_types()
		);

		// For orders it queries an array of shop_order and shop_order_refund post types, hence an array_diff
		if ( ! empty( $post_type ) && ( in_array( $post_type, $supported_post_types, true ) || ( is_array( $post_type ) && ! array_diff( $post_type, $supported_post_types ) ) ) ) {
			$integrate = true;
		}

		/**
		 * If we have a WooCommerce specific query, lets hook it to ElasticPress and make the query ElasticSearch friendly
		 */
		if ( ! $integrate ) {
			return;
		}

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

		// Integrate with WooCommerce custom searches as well
		$search = $query->get( 'search' );
		if ( ! empty( $search ) ) {
			$s = $search;
			$query->set( 's', $s );
		} else {
			$s = $query->get( 's' );
		}

		$query->query_vars['ep_integrate'] = true;
		$query->query['ep_integrate']      = true;

		if ( ! empty( $s ) ) {

			$searchable_post_types = $this->get_admin_searchable_post_types();

			if ( in_array( $post_type, $searchable_post_types, true ) ) {
				$default_search_fields = array( 'post_title', 'post_content', 'post_excerpt' );
				if ( ctype_digit( $s ) ) {
					$default_search_fields[] = 'ID';
				}
				$search_fields = $query->get( 'search_fields', $default_search_fields );

				$search_fields['meta'] = array_map(
					'wc_clean',
					/**
					 * Filter shop order meta fields to search for WooCommerce
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

				$query->set(
					'search_fields',
					/**
					 * Filter all the shop order fields to search for WooCommerce
					 *
					 * @hook ep_woocommerce_shop_order_search_fields
					 * @since 4.0.0
					 * @param {array}    $fields Shop order fields
					 * @param {WP_Query} $query  WP Query
					 * @return {array} New fields
					 */
					apply_filters( 'ep_woocommerce_shop_order_search_fields', $search_fields, $query )
				);
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
	 * Make search coupons don't go through ES
	 *
	 * @param  bool     $enabled Coupons enabled or not
	 * @param  WP_Query $query WP Query
	 * @since  4.7.0
	 * @return bool
	 */
	public function disallow_coupons( $enabled, $query ) {
		if ( is_admin() ) {
			return $enabled;
		}

		if ( 'shop_coupon' === $query->get( 'post_type' ) && empty( $query->query_vars['ep_integrate'] ) ) {
			return false;
		}

		return $enabled;
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
	 * Dashboard WooCommerce settings
	 *
	 * @since 4.5.0
	 */
	public function output_feature_box_settings() {
		$available = $this->is_orders_autosuggest_available();
		$enabled   = $this->is_orders_autosuggest_enabled();
		?>
		<div class="field">
			<div class="field-name status"><?php esc_html_e( 'Orders Autosuggest', 'elasticpress' ); ?></div>
			<div class="input-wrap">
				<label><input name="settings[orders]" type="radio" <?php checked( $enabled ); ?> <?php disabled( $available, false, true ); ?> value="1"><?php echo wp_kses_post( __( 'Enabled', 'elasticpress' ) ); ?></label><br>
				<label><input name="settings[orders]" type="radio" <?php checked( ! $enabled ); ?> <?php disabled( $available, false, true ); ?> value="0"><?php echo wp_kses_post( __( 'Disabled', 'elasticpress' ) ); ?></label>
				<p class="field-description">
					<?php
					$epio_autosuggest_kb_link = 'https://elasticpress.zendesk.com/hc/en-us/articles/13374461690381-Configuring-ElasticPress-io-Order-Autosuggest';

					$message = ( $available ) ?
						/* translators: 1: <a> tag (ElasticPress.io); 2. </a>; 3: <a> tag (KB article); 4. </a>; */
						__( 'You are directly connected to %1$sElasticPress.io%2$s! Enable Orders Autosuggest to enhance Dashboard results and quickly find WooCommerce Orders. %3$sLearn More%4$s.', 'elasticpress' ) :
						/* translators: 1: <a> tag (ElasticPress.io); 2. </a>; 3: <a> tag (KB article); 4. </a>; */
						__( 'Due to the sensitive nature of orders, this autosuggest feature is available only to %1$sElasticPress.io%2$s customers. %3$sLearn More%4$s.', 'elasticpress' );

					printf(
						wp_kses( $message, 'ep-html' ),
						'<a href="https://elasticpress.io/" target="_blank">',
						'</a>',
						'<a href="' . esc_url( $epio_autosuggest_kb_link ) . '" target="_blank">',
						'</a>'
					);
					?>
				</p>
			</div>
		</div>
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
	 * Determines whether or not ES should be integrating with the provided query
	 *
	 * @param \WP_Query $query Query we might integrate with
	 *
	 * @return bool
	 */
	public function should_integrate_with_query( $query ) {
		// Lets make sure this doesn't interfere with the CLI
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return false;
		}

		if ( defined( 'WC_API_REQUEST' ) && WC_API_REQUEST ) {
			return false;
		}

		if ( isset( $query->query_vars['ep_integrate'] ) && ! filter_var( $query->query_vars['ep_integrate'], FILTER_VALIDATE_BOOLEAN ) ) {
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
		if ( apply_filters( 'ep_skip_query_integration', false, $query ) ) {
			return false;
		}

		if ( ! Utils\is_integrated_request( $this->slug ) ) {
			return false;
		}

		/**
		 * Do nothing for single product queries
		 */
		$product_name = $query->get( 'product', false );
		if ( ! empty( $product_name ) || $query->is_single() ) {
			return false;
		}

		/**
		 * ElasticPress does not yet support post_parent queries
		 */
		$post_parent = $query->get( 'post_parent', false );
		if ( ! empty( $post_parent ) ) {
			return false;
		}

		/**
		 * If this is just a preview, let's not use Elasticsearch.
		 */
		if ( $query->get( 'preview', false ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Whether orders autosuggest is available or not
	 *
	 * @since 4.5.0
	 * @return boolean
	 */
	public function is_orders_autosuggest_available() : bool {
		/**
		 * Whether the autosuggest feature is available for non
		 * ElasticPress.io customers.
		 *
		 * @since 4.5.0
		 * @hook ep_woocommerce_orders_autosuggest_available
		 * @param {boolean} $available Whether the feature is available.
		 */
		return apply_filters( 'ep_woocommerce_orders_autosuggest_available', Utils\is_epio() );
	}

	/**
	 * Whether orders autosuggest is enabled or not
	 *
	 * @since 4.5.0
	 * @return boolean
	 */
	public function is_orders_autosuggest_enabled() : bool {
		return $this->is_orders_autosuggest_available() && '1' === $this->get_setting( 'orders' );
	}


	/**
	 * DEPRECATED. Index Woocommerce meta
	 *
	 * @param   array $meta Existing post meta.
	 * @param   array $post Post arguments array.
	 * @since   2.1
	 * @return  array
	 */
	public function whitelist_meta_keys( $meta, $post ) {
		_deprecated_function( __METHOD__, '4.7.0', "\ElasticPress\Features::factory()->get_registered_feature( 'woocommerce' )->products->allow_meta_keys() AND/OR \ElasticPress\Features::factory()->get_registered_feature( 'woocommerce' )->orders->allow_meta_keys()" );
		return array_unique(
			array_merge(
				$this->products->allow_meta_keys( $meta ),
				$this->orders->allow_meta_keys( $meta )
			)
		);
	}

	/**
	 * DEPRECATED. Make sure all loop shop post ins are IDS. We have to pass post objects here since we override
	 * the fields=>id query for the layered filter nav query
	 *
	 * @param   array $posts Post object array.
	 * @since   2.1
	 * @return  array
	 */
	public function convert_post_object_to_id( $posts ) {
		_doing_it_wrong( __METHOD__, 'This filter was removed from WooCommerce and will be removed from ElasticPress in a future release.', '4.5.0' );
		return $posts;
	}

	/**
	 * DEPRECATED. Index Woocommerce taxonomies
	 *
	 * @param   array $taxonomies Index taxonomies array.
	 * @param   array $post Post properties array.
	 * @since   2.1
	 * @return  array
	 */
	public function whitelist_taxonomies( $taxonomies, $post ) {
		_deprecated_function( __METHOD__, '4.7.0', "\ElasticPress\Features::factory()->get_registered_feature( 'woocommerce' )->products->sync_taxonomies()" );
		return $this->products->sync_taxonomies( $taxonomies );
	}

	/**
	 * DEPRECATED. Disallow duplicated ES queries on Orders page.
	 *
	 * @since 2.4
	 *
	 * @param array    $value Original filter values.
	 * @param WP_Query $query WP_Query
	 *
	 * @return array
	 */
	public function disallow_duplicated_query( $value, $query ) {
		_doing_it_wrong( __METHOD__, 'This filter was removed from WooCommerce and will be removed from ElasticPress in a future release.', '4.5.0' );

		return $value;
	}

	/**
	 * DEPRECATED. Returns the WooCommerce-oriented post types in admin that EP will search
	 *
	 * @since 4.4.0
	 * @return mixed|void
	 */
	public function get_admin_searchable_post_types() {
		_deprecated_function( __METHOD__, '4.7.0', "\ElasticPress\Features::factory()->get_registered_feature( 'woocommerce' )->orders->get_admin_searchable_post_types()" );
		return $this->orders->get_admin_searchable_post_types();
	}

	/**
	 * DEPRECATED. Make search coupons don't go through ES
	 *
	 * @param  bool     $enabled Coupons enabled or not
	 * @param  WP_Query $query WP Query
	 * @since  2.1
	 * @return bool
	 */
	public function blacklist_coupons( $enabled, $query ) {
		_deprecated_function( __METHOD__, '4.7.0', "\ElasticPress\Features::factory()->get_registered_feature( 'woocommerce' )->disallow_coupons()" );
		return $this->disallow_coupons( $enabled, $query );
	}

	/**
	 * DEPRECATED. Allow order creations on the front end to get synced
	 *
	 * @since  2.1
	 * @param  bool $override Original order perms check value
	 * @param  int  $post_id Post ID
	 * @return bool
	 */
	public function bypass_order_permissions_check( $override, $post_id ) {
		_deprecated_function( __METHOD__, '4.7.0', "\ElasticPress\Features::factory()->get_registered_feature( 'woocommerce' )->orders->price_filter()" );
		return $this->orders->bypass_order_permissions_check( $override, $post_id );
	}

	/**
	 * DEPRECATED. Sets woocommerce meta search fields to an empty array if we are integrating the main query with ElasticSearch
	 *
	 * Woocommerce calls this action as part of its own callback on parse_query. We add this filter only if the query
	 * is integrated with ElasticSearch.
	 * If we were to always return array() on this filter, we'd break admin searches when WooCommerce module is activated
	 * without the Protected Content Module
	 *
	 * @param \WP_Query $query Current query
	 */
	public function maybe_hook_woocommerce_search_fields( $query ) {
		_deprecated_function( __METHOD__, '4.7.0', "\ElasticPress\Features::factory()->get_registered_feature( 'woocommerce' )->orders->maybe_hook_woocommerce_search_fields()" );
		return $this->orders->maybe_hook_woocommerce_search_fields( $query );
	}

	/**
	 * DEPRECATED. Enhance WooCommerce search order by order id, email, phone number, name, etc..
	 * What this function does:
	 * 1. Reverse the woocommerce shop_order_search_custom_fields query
	 * 2. If the search key is integer and it is an Order Id, just query with post__in
	 * 3. If the search key is integer but not an order id ( might be phone number ), use ES to find it
	 *
	 * @param WP_Query $wp WP Query
	 * @since  2.3
	 */
	public function search_order( $wp ) {
		_deprecated_function( __METHOD__, '4.7.0', "\ElasticPress\Features::factory()->get_registered_feature( 'woocommerce' )->orders->search_order()" );
		return $this->orders->search_order( $wp );
	}

	/**
	 * DEPRECATED. Add order items as a searchable string.
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
		_deprecated_function( __METHOD__, '4.7.0', "\ElasticPress\Features::factory()->get_registered_feature( 'woocommerce' )->orders->add_order_items_search()" );
		return $this->orders->add_order_items_search( $post_args, $post_id );
	}

	/**
	 * DEPRECATED. Add WooCommerce Product Attributes to EP Facets.
	 *
	 * @param array $taxonomies Taxonomies array
	 * @return array
	 */
	public function add_product_attributes( $taxonomies = [] ) {
		_deprecated_function( __METHOD__, '4.7.0', "\ElasticPress\Features::factory()->get_registered_feature( 'woocommerce' )->products->add_product_attributes()" );
		return $this->products->add_product_attributes( $taxonomies );
	}

	/**
	 * DEPRECATED. Add WooCommerce Fields to the Weighting Dashboard.
	 *
	 * @since 3.x
	 *
	 * @param array  $fields    Current weighting fields.
	 * @param string $post_type Current post type.
	 * @return array            New fields.
	 */
	public function add_product_attributes_to_weighting( $fields, $post_type ) {
		_deprecated_function( __METHOD__, '4.7.0', "\ElasticPress\Features::factory()->get_registered_feature( 'woocommerce' )->products->add_product_attributes_to_weighting()" );
		return $this->products->add_product_attributes_to_weighting( $fields, $post_type );
	}

	/**
	 * DEPRECATED. Add WooCommerce Fields to the default values of the Weighting Dashboard.
	 *
	 * @since 3.x
	 *
	 * @param array  $defaults  Default values for the post type.
	 * @param string $post_type Current post type.
	 * @return array
	 */
	public function add_product_default_post_type_weights( $defaults, $post_type ) {
		_deprecated_function( __METHOD__, '4.7.0', "\ElasticPress\Features::factory()->get_registered_feature( 'woocommerce' )->products->add_product_default_post_type_weights()" );
		return $this->products->add_product_default_post_type_weights( $defaults, $post_type );
	}

	/**
	 * DEPRECATED. Add WC post type to autosuggest
	 *
	 * @param array $post_types Array of post types (e.g. post, page).
	 * @since  2.6
	 * @return array
	 */
	public function suggest_wc_add_post_type( $post_types ) {
		_deprecated_function( __METHOD__, '4.7.0', "\ElasticPress\Features::factory()->get_registered_feature( 'woocommerce' )->products->suggest_wc_add_post_type()" );
		return $this->products->suggest_wc_add_post_type( $post_types );
	}

	/**
	 * DEPRECATED. Modifies main query to allow filtering by price with WooCommerce "Filter by price" widget.
	 *
	 * @param  array    $args ES args
	 * @param  array    $query_args WP_Query args
	 * @param  WP_Query $query WP_Query object
	 * @since  3.2
	 * @return array
	 */
	public function price_filter( $args, $query_args, $query ) {
		_deprecated_function( __METHOD__, '4.7.0', "\ElasticPress\Features::factory()->get_registered_feature( 'woocommerce' )->products->price_filter()" );
		return $this->products->price_filter( $args, $query_args, $query );
	}

	/**
	 * DEPRECATED. Prevent order fields from being removed.
	 *
	 * When Protected Content is enabled, all posts with password have their content removed.
	 * This can't happen for orders, as the order key is added in that field.
	 *
	 * @see https://github.com/10up/ElasticPress/issues/2726
	 *
	 * @since 4.2.0
	 * @param bool  $skip      Whether the password protected content should have their content, and meta removed
	 * @param array $post_args Post arguments
	 * @return bool
	 */
	public function keep_order_fields( $skip, $post_args ) {
		_deprecated_function( __METHOD__, '4.7.0', "\ElasticPress\Features::factory()->get_registered_feature( 'woocommerce' )->orders->keep_order_fields()" );
		return $this->orders->keep_order_fields( $skip, $post_args );
	}

	/**
	 * DEPRECATED. Add a new `_variations_skus` meta field to the product to be indexed in Elasticsearch.
	 *
	 * @since 4.2.0
	 * @param array   $post_meta Post meta
	 * @param WP_Post $post      Post object
	 * @return array
	 */
	public function add_variations_skus_meta( $post_meta, $post ) {
		_deprecated_function( __METHOD__, '4.7.0', "\ElasticPress\Features::factory()->get_registered_feature( 'woocommerce' )->products->add_variations_skus_meta()" );
		return $this->products->add_variations_skus_meta( $post_meta, $post );
	}

	/**
	 * DEPRECATED. Integrate ElasticPress with the WooCommerce Admin Product List.
	 *
	 * WooCommerce uses its `WC_Admin_List_Table_Products` class to control that screen. This
	 * function adds all necessary hooks to bypass the default behavior and integrate with ElasticPress.
	 * By default, WC runs a SQL query to get the Product IDs that match the list criteria and passes
	 * that list of IDs to the main WP_Query. This integration changes that process to a single query, run
	 * by ElasticPress.
	 *
	 * @since 4.2.0
	 * @param array $query_vars Query vars.
	 * @return array
	 */
	public function admin_product_list_request_query( $query_vars ) {
		_deprecated_function( __METHOD__, '4.7.0', "\ElasticPress\Features::factory()->get_registered_feature( 'woocommerce' )->products->admin_product_list_request_query()" );
		return $this->products->admin_product_list_request_query( $query_vars );
	}

	/**
	 * DEPRECATED. Apply the necessary changes to WP_Query in WooCommerce Admin Product List.
	 *
	 * @param WP_Query $query The WP Query being executed.
	 */
	public function translate_args_admin_products_list( $query ) {
		_deprecated_function( __METHOD__, '4.7.0', "\ElasticPress\Features::factory()->get_registered_feature( 'woocommerce' )->products->price_filter()" );
		$this->products->translate_args_admin_products_list( $query );
	}

	/**
	 * DEPRECATED. Depending on the number of products display an admin notice in the custom sort screen for WooCommerce Products
	 *
	 * @since 4.4.0
	 * @param array $notices Current ElasticPress admin notices
	 * @return array
	 */
	public function maybe_display_notice_about_product_ordering( $notices ) {
		_deprecated_function( __METHOD__, '4.7.0', "\ElasticPress\Features::factory()->get_registered_feature( 'woocommerce' )->products->maybe_display_notice_about_product_ordering()" );
		return $this->products->maybe_display_notice_about_product_ordering( $notices );
	}

	/**
	 * DEPRECATED. Conditionally resync products after applying a custom order.
	 *
	 * @since 4.4.0
	 * @param int   $sorting_id  ID of post dragged and dropped
	 * @param array $menu_orders Post IDs and their new menu_order value
	 */
	public function action_sync_on_woocommerce_sort_single( $sorting_id, $menu_orders ) {
		_deprecated_function( __METHOD__, '4.7.0', "\ElasticPress\Features::factory()->get_registered_feature( 'woocommerce' )->products->action_sync_on_woocommerce_sort_single()" );
		return $this->products->action_sync_on_woocommerce_sort_single( $sorting_id, $menu_orders );
	}

	/**
	 * DEPRECATED. Add weight by date settings related to WooCommerce
	 *
	 * @since 4.6.0
	 * @param array $settings Current settings.
	 */
	public function add_weight_settings_search( $settings ) {
		_deprecated_function( __METHOD__, '4.7.0', "\ElasticPress\Features::factory()->get_registered_feature( 'woocommerce' )->products->add_weight_settings_search()" );
		$this->products->add_weight_settings_search( $settings );
	}

	/**
	 * DEPRECATED. Conditionally disable decaying by date based on WooCommerce Decay settings.
	 *
	 * @since 4.6.0
	 * @param bool  $is_decaying_enabled Whether decay by date is enabled or not
	 * @param array $settings            Settings
	 * @param array $args                WP_Query args
	 * @return bool
	 */
	public function maybe_disable_decaying( $is_decaying_enabled, $settings, $args ) {
		_deprecated_function( __METHOD__, '4.7.0', "\ElasticPress\Features::factory()->get_registered_feature( 'woocommerce' )->products->maybe_disable_decaying()" );
		return $this->products->maybe_disable_decaying( $is_decaying_enabled, $settings, $args );
	}
}
