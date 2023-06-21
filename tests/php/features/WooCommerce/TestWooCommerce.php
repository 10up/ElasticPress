<?php
/**
 * Test woocommerce feature
 *
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;

/**
 * WC test class
 */
class TestWooCommerce extends BaseTestCase {

	/**
	 * Setup each test.
	 *
	 * @since 2.1
	 * @group woocommerce
	 */
	public function set_up() {
		global $wpdb;
		parent::set_up();
		$wpdb->suppress_errors();

		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );

		wp_set_current_user( $admin_id );

		ElasticPress\Elasticsearch::factory()->delete_all_indices();
		ElasticPress\Indexables::factory()->get( 'post' )->put_mapping();

		ElasticPress\Indexables::factory()->get( 'post' )->sync_manager->sync_queue = [];

		$this->setup_test_post_type();
	}

	/**
	 * Clean up after each test. Reset our mocks
	 *
	 * @since 2.1
	 * @group woocommerce
	 */
	public function tear_down() {
		parent::tear_down();

		$this->fired_actions = array();
	}

	/**
	 * Test products post type query does not get integrated when the feature is active
	 *
	 * @since 2.1
	 * @group woocommerce
	 */
	public function testProductsPostTypeQueryOn() {
		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		$this->ep_factory->post->create();
		$this->ep_factory->product->create(
			array(
				'description' => 'product 1',
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			'post_type' => 'product',
		);

		$query = new \WP_Query( $args );

		$this->assertNull( $query->elasticsearch_success );
		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );
	}

	/**
	 * Test products post type query does get integrated when querying WC product_cat taxonomy
	 *
	 * @since 2.1
	 * @group woocommerce
	 */
	public function testProductsPostTypeQueryProductCatTax() {
		ElasticPress\Features::factory()->activate_feature( 'admin' );
		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		$this->ep_factory->post->create();

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			'tax_query' => array(
				array(
					'taxonomy' => 'product_cat',
					'terms'    => array( 'cat' ),
					'field'    => 'slug',
				),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
	}

	/**
	 * Test search integration is on for shop orders
	 *
	 * @since 2.1
	 * @group woocommerce
	 */
	public function testSearchOnShopOrderAdmin() {
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		$this->ep_factory->post->create(
			array(
				'post_content' => 'findme',
				'post_type'    => 'shop_order',
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		// mock the pagenow to bypass the search_order checks
		global $pagenow;
		$pagenow = 'edit.php';

		parse_str( 's=findme', $_GET );
		$args = array(
			's'         => 'findme',
			'post_type' => 'shop_order',
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );

		$pagenow = 'index.php';
	}

	/**
	 * Test Shop Order post type query does not get integrated when the protected content feature is deactivated.
	 *
	 * @since 4.5
	 */
	public function testShopOrderPostTypeQueryOn() {
		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		$this->ep_factory->post->create();
		$this->ep_factory->post->create(
			array(
				'post_type' => 'shop_order',
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args  = array(
			'post_type' => 'shop_order',
		);
		$query = new \WP_Query( $args );

		$this->assertNull( $query->elasticsearch_success );
		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );
	}

	/**
	 * Test Shop Order post type query does get integrated when the protected content feature is activated.
	 *
	 * @since 4.5
	 */
	public function testShopOrderPostTypeQueryWhenProtectedContentEnable() {
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		$this->ep_factory->post->create();
		$this->ep_factory->post->create(
			array(
				'post_type' => 'shop_order',
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args  = array(
			'post_type' => 'shop_order',
		);
		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );
	}

	/**
	 * Test Shop Order post type query does not get integrated when the protected content feature is activated and ep_integrate is set to false.
	 *
	 * @since 4.5
	 */
	public function testShopOrderPostTypeQueryWhenEPIntegrateSetFalse() {
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		$this->ep_factory->post->create();
		$this->ep_factory->post->create(
			array(
				'post_type' => 'shop_order',
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args  = array(
			'post_type'    => 'shop_order',
			'ep_integrate' => false,
		);
		$query = new \WP_Query( $args );

		$this->assertNull( $query->elasticsearch_success );
	}

	/**
	 * Test search for shop orders by order ID
	 *
	 * @since 4.0.0
	 * @group woocommerce
	 */
	public function testSearchShopOrderById() {
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		$shop_order_id = $this->ep_factory->post->create(
			array(
				'post_type' => 'shop_order',
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'         => (string) $shop_order_id,
			'post_type' => 'shop_order',
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );
	}

	/**
	 * Test search integration is on in general for product searches
	 *
	 * @since 2.1
	 * @group woocommerce
	 */
	public function testSearchOnAllFrontEnd() {
		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'         => 'findme',
			'post_type' => 'product',
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
	}

	/**
	 * Test the addition of variations skus to product meta
	 *
	 * @since 4.2.0
	 * @group woocommerce
	 * @expectedDeprecated ElasticPress\Feature\WooCommerce\WooCommerce::add_variations_skus_meta
	 */
	public function testAddVariationsSkusMeta() {
		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		$this->assertTrue( class_exists( '\WC_Product_Variable' ) );
		$this->assertTrue( class_exists( '\WC_Product_Variation' ) );

		$main_product = new \WC_Product_Variable();
		$main_product->set_sku( 'main-product_sku' );
		$main_product_id = $main_product->save();

		$variation_1 = new \WC_Product_Variation();
		$variation_1->set_parent_id( $main_product_id );
		$variation_1->set_sku( 'child-sku-1' );
		$variation_1->save();

		$variation_2 = new \WC_Product_Variation();
		$variation_2->set_parent_id( $main_product_id );
		$variation_2->set_sku( 'child-sku-2' );
		$variation_2->save();

		$main_product_as_post  = get_post( $main_product_id );
		$product_meta_to_index = ElasticPress\Features::factory()
			->get_registered_feature( 'woocommerce' )
			->add_variations_skus_meta( [], $main_product_as_post );

		$this->assertArrayHasKey( '_variations_skus', $product_meta_to_index );
		$this->assertContains( 'child-sku-1', $product_meta_to_index['_variations_skus'] );
		$this->assertContains( 'child-sku-2', $product_meta_to_index['_variations_skus'] );
	}

	/**
	 * Test the translate_args_admin_products_list method
	 *
	 * @since 4.2.0
	 * @group woocommerce
	 * @expectedDeprecated ElasticPress\Feature\WooCommerce\WooCommerce::translate_args_admin_products_list
	 */
	public function testTranslateArgsAdminProductsList() {
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		parse_str( 'post_type=product&s=product&product_type=downloadable&stock_status=instock', $_GET );

		$query_args = [
			'ep_integrate' => true,
		];

		$woocommerce_feature = ElasticPress\Features::factory()->get_registered_feature( 'woocommerce' );
		add_action( 'pre_get_posts', [ $woocommerce_feature, 'translate_args_admin_products_list' ] );

		$query = new \WP_Query( $query_args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( $query->query_vars['s'], 'product' );
		$this->assertEquals( $query->query_vars['meta_query'][0]['key'], '_downloadable' );
		$this->assertEquals( $query->query_vars['meta_query'][0]['value'], 'yes' );
		$this->assertEquals( $query->query_vars['meta_query'][1]['key'], '_stock_status' );
		$this->assertEquals( $query->query_vars['meta_query'][1]['value'], 'instock' );
		$this->assertEquals(
			$query->query_vars['search_fields'],
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
	}

	/**
	 * Test the ep_woocommerce_admin_products_list_search_fields filter
	 *
	 * @since 4.2.0
	 * @group woocommerce
	 * @expectedDeprecated ElasticPress\Feature\WooCommerce\WooCommerce::translate_args_admin_products_list
	 */
	public function testEPWoocommerceAdminProductsListSearchFields() {
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		parse_str( 'post_type=product&s=product&product_type=downloadable', $_GET );

		$query_args = [
			'ep_integrate' => true,
		];

		$woocommerce_feature = ElasticPress\Features::factory()->get_registered_feature( 'woocommerce' );
		add_action( 'pre_get_posts', [ $woocommerce_feature, 'translate_args_admin_products_list' ] );

		$search_fields_function = function () {
			return [ 'post_title', 'post_content' ];
		};
		add_filter( 'ep_woocommerce_admin_products_list_search_fields', $search_fields_function );

		$query = new \WP_Query( $query_args );
		$this->assertEquals(
			$query->query_vars['search_fields'],
			[ 'post_title', 'post_content' ]
		);
	}

	/**
	 * Tests the search query for a shop_coupon.
	 *
	 * @since 4.4.1
	 */
	public function testSearchQueryForCoupon() {

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		// ensures that the search query doesn't use Elasticsearch.
		$query = new \WP_Query(
			[
				'post_type' => 'shop_coupon',
				's'         => 'test-coupon',
			]
		);
		$this->assertNull( $query->elasticsearch_success );

		// ensures that the search query doesn't use Elasticsearch when ep_integrate set to false.
		$query = new \WP_Query(
			[
				'post_type'    => 'shop_coupon',
				's'            => 'test-coupon',
				'ep_integrate' => false,
			]
		);
		$this->assertNull( $query->elasticsearch_success );

		// ensures that the search query use Elasticsearch when ep_integrate set to true.
		$query = new \WP_Query(
			[
				'post_type'    => 'shop_coupon',
				's'            => 'test-coupon',
				'ep_integrate' => true,
			]
		);
		$this->assertTrue( $query->elasticsearch_success );
	}

	/**
	 * Tests the search query for a shop_coupon in admin use Elasticsearch when protected content is enabled.
	 *
	 * @since 4.4.1
	 */
	public function testSearchQueryForCouponWhenProtectedContentIsEnable() {

		set_current_screen( 'dashboard' );
		$this->assertTrue( is_admin() );

		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		$this->ep_factory->post->create(
			array(
				'post_content' => 'test-coupon',
				'post_type'    => 'shop_coupon',
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$query = new \WP_Query(
			[
				'post_type' => 'shop_coupon',
				's'         => 'test-coupon',
			]
		);

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 1, $query->post_count );
	}

	/**
	 * Tests the search query for a shop_coupon in admin does not use Elasticsearch when protected content is not enabled.
	 *
	 * @since 4.4.1
	 */
	public function testSearchQueryForCouponWhenProtectedContentIsNotEnable() {

		set_current_screen( 'dashboard' );
		$this->assertTrue( is_admin() );

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		$this->ep_factory->post->create(
			array(
				'post_content' => 'test-coupon',
				'post_type'    => 'shop_coupon',
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$query = new \WP_Query(
			[
				'post_type'    => 'shop_coupon',
				's'            => 'test-coupon',
				'ep_integrate' => true,
			]
		);

		$this->assertNull( $query->elasticsearch_success );
		$this->assertEquals( 1, $query->post_count );
	}

	/**
	 * Test all the product attributes are synced.
	 *
	 * @since 4.5.0
	 */
	public function testWoocommerceAttributeTaxonomiesAreSync() {
		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		$product_id = $this->ep_factory->product->create_variation_product();

		$post     = new \ElasticPress\Indexable\Post\Post();
		$document = $post->prepare_document( $product_id );

		$this->assertArrayHasKey( 'pa_size', $document['terms'] );
		$this->assertArrayHasKey( 'pa_colour', $document['terms'] );
		$this->assertArrayHasKey( 'pa_number', $document['terms'] );
	}

	/**
	 * Data provider for the testProductQueryOrder method.
	 *
	 * @return array
	 */
	public function productQueryOrderDataProvider() : array {
		return [
			[
				'total_sales',
				[ 'meta_key' => 'total_sales' ],
				false,
				[
					0 => [ 'meta.total_sales.double' => [ 'order' => 'desc' ] ],
					1 => [ 'post_date' => [ 'order' => 'desc' ] ],
				],
			],
			[
				'average_rating',
				[ 'meta_key' => '_wc_average_rating' ],
				false,
				[
					0 => [ 'meta._wc_average_rating.double' => [ 'order' => 'desc' ] ],
					1 => [ 'post_date' => [ 'order' => 'desc' ] ],
				],
			],
			[
				'regular_price',
				[
					'orderby' => 'price',
					'order'   => 'DESC',
				],
				false,
				[
					0 => [ 'meta._price.double' => [ 'order' => 'desc' ] ],
					1 => [ 'post_date' => [ 'order' => 'desc' ] ],
				],
			],
			[
				'total_sales',
				[
					'orderby' => 'popularity',
					'order'   => 'DESC',
				],
				false,
				[
					0 => [ 'meta.total_sales.double' => [ 'order' => 'desc' ] ],
					1 => [ 'post_date' => [ 'order' => 'desc' ] ],
				],
			],
			[
				'total_sales',
				[],
				'popularity',
				[
					0 => [ 'meta.total_sales.double' => [ 'order' => 'desc' ] ],
					1 => [ 'post_date' => [ 'order' => 'desc' ] ],
				],
			],
			[
				'regular_price',
				[],
				'price-desc',
				[
					0 => [ 'meta._price.double' => [ 'order' => 'desc' ] ],
					1 => [ 'post_date' => [ 'order' => 'desc' ] ],
				],
			],
			[
				'average_rating',
				[],
				'rating',
				[
					0 => [ 'meta._wc_average_rating.double' => [ 'order' => 'desc' ] ],
					1 => [ 'post_date' => [ 'order' => 'desc' ] ],
				],
			],
			[
				'regular_price',
				[],
				'price',
				[
					0 => [ 'meta._price.double' => [ 'order' => 'asc' ] ],
					1 => [ 'post_date' => [ 'order' => 'asc' ] ],
				],
				'asc',
			],
			[
				'sku',
				[],
				'sku',
				[
					0 => [ 'meta._sku.value.sortable' => [ 'order' => 'asc' ] ],
					1 => [ 'post_date' => [ 'order' => 'asc' ] ],
				],
				'asc',
			],
			[
				'name',
				[],
				'title',
				[
					0 => [ 'post_title.sortable' => [ 'order' => 'asc' ] ],
					1 => [ 'post_date' => [ 'order' => 'asc' ] ],
				],
				'asc',
			],
			[
				'',
				[],
				'default',
				[
					0 => [ 'menu_order' => [ 'order' => 'asc' ] ],
					1 => [ 'post_title.sortable' => [ 'order' => 'asc' ] ],
					2 => [ 'post_date' => [ 'order' => 'asc' ] ],
				],
			],
			[ '', [], '', [ 0 => [ 'post_date' => [ 'order' => 'desc' ] ] ] ],
		];
	}

	/**
	 *  Test the product query order.
	 *
	 * @param string $product_arg_key Field slug
	 * @param array  $query_args      Query array
	 * @param bool   $query_string    Query string
	 * @param array  $expected        Value expected
	 * @param string $order           Order
	 * @dataProvider productQueryOrderDataProvider
	 * @since 4.5.0
	 */
	public function testProductQueryOrder( $product_arg_key, $query_args, $query_string, $expected, $order = '' ) {
		global $wp_the_query;

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		$product_1 = $this->ep_factory->product->create(
			array(
				$product_arg_key => 200,
			)
		);

		$product_2 = $this->ep_factory->product->create(
			array(
				$product_arg_key => 100,
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		if ( $query_string ) {
			parse_str( 'orderby=' . $query_string, $_GET );

			// mock the query as post type archive
			add_action(
				'parse_query',
				function( \WP_Query $query ) {
					$query->is_post_type_archive = true;
				}
			);
		}

		$args  = array(
			'post_type' => 'product',
		);
		$args  = array_merge( $args, $query_args );
		$query = new \WP_Query( $args );

		// mock the query as main query
		$wp_the_query = $query;

		add_filter(
			'ep_post_formatted_args',
			function ( $formatted_args ) use ( $expected ) {
				$this->assertEquals( $expected, $formatted_args['sort'] );
				return $formatted_args;
			}
		);

		$query = $query->query( $args );

		$this->assertTrue( $wp_the_query->elasticsearch_success, 'Elasticsearch query failed' );
		$this->assertEquals( 2, count( $query ) );

		if ( 'asc' === $order ) {
			$this->assertEquals( $product_2, $query[0]->ID );
			$this->assertEquals( $product_1, $query[1]->ID );
		} elseif ( 'desc' === $order ) {
			$this->assertEquals( $product_1, $query[0]->ID );
			$this->assertEquals( $product_2, $query[1]->ID );
		}

		\WC_Query::reset_chosen_attributes();
	}

	/**
	 * Test the product query not use Elasticsearch if preview.
	 *
	 * @since 4.5.0
	 */
	public function testQueryShouldNotUseElasticsearchIfPreview() {
		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		$args = array(
			'post_type' => 'product',
			'preview'   => true,
		);

		$query = new \WP_Query( $args );

		$this->assertNull( $query->elasticsearch_success );
	}

	/**
	 * Test that on Admin Product List use Elasticsearch.
	 *
	 * @since 4.5.0
	 */
	public function testProductListInAdminUseElasticSearch() {
		global $typenow, $wc_list_table;

		set_current_screen( 'edit.php' );
		$this->assertTrue( is_admin() );

		// load required files
		include_once ABSPATH . 'wp-admin/includes/class-wp-posts-list-table.php';
		include_once WC()->plugin_path() . '/includes/admin/list-tables/class-wc-admin-list-table-products.php';

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		// mock the global variables
		$typenow       = 'product';
		$wc_list_table = new \WC_Admin_List_Table_Products();

		add_filter(
			'ep_post_filters',
			function( $filters, $args, $query ) {
				$expected_result = array(
					'terms' => array(
						'post_type.raw' => array(
							'product',
						),
					),
				);

				$this->assertEquals( $expected_result, $filters['post_type'] );
				return $filters;
			},
			10,
			3
		);

		parse_str( 'post_type=product&s=product', $_GET );

		$wp_list_table = new \WP_Posts_List_Table();
		$wp_list_table->prepare_items();
	}

	/**
	 * Test that Search in Admin Product List use Elasticsearch.
	 *
	 * @since 4.5.0
	 */
	public function testProductListSearchInAdminUseElasticSearch() {
		global $typenow, $wc_list_table;

		set_current_screen( 'edit.php' );
		$this->assertTrue( is_admin() );

		// load required files
		include_once ABSPATH . 'wp-admin/includes/class-wp-posts-list-table.php';
		include_once WC()->plugin_path() . '/includes/admin/list-tables/class-wc-admin-list-table-products.php';

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		// mock the global variables
		$typenow       = 'product';
		$wc_list_table = new \WC_Admin_List_Table_Products();

		add_filter(
			'ep_post_formatted_args',
			function ( $formatted_args, $args, $wp_query ) {
				$this->assertEquals( 'findme', $formatted_args['query']['function_score']['query']['bool']['should'][0]['multi_match']['query'] );
				$this->assertEquals(
					$args['search_fields'],
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

				return $formatted_args;
			},
			10,
			3
		);

		parse_str( 'post_type=product&s=findme', $_GET );

		$wp_list_table = new \WP_Posts_List_Table();
		$wp_list_table->prepare_items();
	}

	/**
	 * Test the product query when price filter is set.
	 *
	 * @since 4.5.0
	 */
	public function testPriceFilter() {
		global $wp_the_query, $wp_query;

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		$this->ep_factory->product->create(
			[
				'name'          => 'Cap 1',
				'regular_price' => 100,
			]
		);
		$this->ep_factory->product->create(
			[
				'name'          => 'Cap 2',
				'regular_price' => 800,
			]
		);
		$this->ep_factory->product->create(
			[
				'name'          => 'Cap 3',
				'regular_price' => 10000,
			]
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		parse_str( 'min_price=1&max_price=999', $_GET );

		$args  = array(
			'post_type' => 'product',
		);
		$query = new \WP_Query( $args );

		// mock the query as main query and is_search
		$wp_the_query        = $query;
		$wp_query->is_search = true;

		add_filter(
			'ep_post_formatted_args',
			function ( $formatted_args ) {

				$expected_result = array(
					'range' => array(
						'meta._price.long' => array(
							'gte'   => 1,
							'lte'   => 999,
							'boost' => 2,
						),
					),
				);

				$this->assertEquals( $expected_result, $formatted_args['query'] );
				return $formatted_args;
			},
			15
		);

		$query = $query->query( $args );

		$this->assertTrue( $wp_the_query->elasticsearch_success, 'Elasticsearch query failed' );
		$this->assertEquals( 2, count( $query ) );
	}

	/**
	 * Test the product search query when price filter is set.
	 *
	 * @since 4.5.0
	 */
	public function testPriceFilterWithSearchQuery() {
		global $wp_the_query, $wp_query;

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		$this->ep_factory->product->create(
			[
				'name'          => 'Cap 1',
				'regular_price' => 100,
			]
		);

		$this->ep_factory->product->create(
			[
				'name'          => 'Cap 2',
				'regular_price' => 1000,
			]
		);

		$this->ep_factory->product->create(
			[
				'name'          => 'Cap 3',
				'regular_price' => 10000,
			]
		);

		$this->ep_factory->product->create(
			[
				'name'          => 'Cap 4',
				'regular_price' => 800,
			]
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		parse_str( 'min_price=1&max_price=999', $_GET );

		$args  = array(
			's'         => 'Cap',
			'post_type' => 'product',
		);
		$query = new \WP_Query( $args );

		// mock the query as main query and is_search
		$wp_the_query        = $query;
		$wp_query->is_search = true;

		$query = $query->query( $args );

		$this->assertTrue( $wp_the_query->elasticsearch_success, 'Elasticsearch query failed' );
		$this->assertEquals( 2, count( $query ) );
	}

	/**
	 * Tests that attributes filter uses Elasticsearch.
	 *
	 * @since 4.5.0
	 */
	public function testAttributesFilterUseES() {
		global $wp_the_query;

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		$this->ep_factory->product->create_variation_product(
			[
				'name' => 'Cap',
			]
		);

		$this->ep_factory->product->create(
			[
				'name' => 'Shoes',
			]
		);

		$this->ep_factory->product->create(
			[
				'name' => 'T-Shirt',
			]
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		// mock the query as post type archive
		add_action(
			'parse_query',
			function( \WP_Query $query ) {
				$query->is_post_type_archive = true;
			}
		);

		parse_str( 'filter_colour=blue', $_GET );

		$args  = array(
			'post_type' => 'product',
		);
		$query = new \WP_Query( $args );

		// mock the query as main query
		$wp_the_query = $query;

		$query = $query->query( $args );

		$this->assertTrue( $wp_the_query->elasticsearch_success );
		$this->assertEquals( 1, count( $query ) );
		$this->assertEquals( 'Cap', $query[0]->post_title );
	}

	/**
	 * Tests that get_posts() uses Elasticsearch when ep_integrate is true.
	 *
	 * @since 4.5.0
	 */
	public function testGetPosts() {
		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		$this->ep_factory->product->create();

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$posts = get_posts(
			[
				'post_type'    => 'product',
				'ep_integrate' => true,
			]
		);

		$this->assertTrue( $posts[0]->elasticsearch );
	}

	/**
	 * Tests that get_posts() does not use Elasticsearch when ep_integrate is not set.
	 *
	 * @since 4.5.0
	 */
	public function testGetPostQueryDoesNotUseElasticSearchByDefault() {
		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		$this->ep_factory->product->create();

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$posts = get_posts(
			[
				'post_type' => 'product',
			]
		);

		$properties = get_object_vars( $posts[0] );
		$this->assertArrayNotHasKey( 'elasticsearch', $properties );
	}

	/**
	 * Tests that Weighting dashboard shows SKU and Variation SKUs option.
	 *
	 * @since 4.5.0
	 */
	public function testSkuOptionAddInWeightDashboard() {
		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		$search = ElasticPress\Features::factory()->get_registered_feature( 'search' );
		$fields = $search->weighting->get_weightable_fields_for_post_type( 'product' );

		$this->assertArrayHasKey( 'meta._sku.value', $fields['attributes']['children'] );
		$this->assertArrayHasKey( 'meta._variations_skus.value', $fields['attributes']['children'] );

		$this->assertEquals( 'meta._sku.value', $fields['attributes']['children']['meta._sku.value']['key'] );
		$this->assertEquals( 'SKU', $fields['attributes']['children']['meta._sku.value']['label'] );

		$this->assertEquals( 'meta._variations_skus.value', $fields['attributes']['children']['meta._variations_skus.value']['key'] );
		$this->assertEquals( 'Variations SKUs', $fields['attributes']['children']['meta._variations_skus.value']['label'] );
	}

	/**
	 * Test the `is_orders_autosuggest_available` method
	 *
	 * @since 4.5.0
	 */
	public function testIsOrdersAutosuggestAvailable() {
		$woocommerce_feature = ElasticPress\Features::factory()->get_registered_feature( 'woocommerce' );

		$this->assertSame( $woocommerce_feature->is_orders_autosuggest_available(), \ElasticPress\Utils\is_epio() );

		/**
		 * Test the `ep_woocommerce_orders_autosuggest_available` filter
		 */
		add_filter( 'ep_woocommerce_orders_autosuggest_available', '__return_true' );
		$this->assertTrue( $woocommerce_feature->is_orders_autosuggest_available() );

		add_filter( 'ep_woocommerce_orders_autosuggest_available', '__return_false' );
		$this->assertFalse( $woocommerce_feature->is_orders_autosuggest_available() );
	}

	/**
	 * Test the `is_orders_autosuggest_available` method
	 *
	 * @since 4.5.0
	 */
	public function testIsOrdersAutosuggestEnabled() {
		$woocommerce_feature = ElasticPress\Features::factory()->get_registered_feature( 'woocommerce' );

		$this->assertFalse( $woocommerce_feature->is_orders_autosuggest_enabled() );

		/**
		 * Make it available but it won't be enabled
		 */
		add_filter( 'ep_woocommerce_orders_autosuggest_available', '__return_true' );
		$this->assertFalse( $woocommerce_feature->is_orders_autosuggest_enabled() );

		/**
		 * Enable it
		 */
		$filter = function() {
			return [
				'woocommerce' => [
					'orders' => '1',
				],
			];
		};
		add_filter( 'pre_site_option_ep_feature_settings', $filter );
		add_filter( 'pre_option_ep_feature_settings', $filter );
		$this->assertTrue( $woocommerce_feature->is_orders_autosuggest_enabled() );

		/**
		 * Make it unavailable. Even activated, it should not be considered enabled if not available anymore.
		 */
		remove_filter( 'ep_woocommerce_orders_autosuggest_available', '__return_true' );
		$this->assertFalse( $woocommerce_feature->is_orders_autosuggest_enabled() );
	}

	/**
	 * Test if decaying is disabled on products.
	 *
	 * @since 4.6.0
	 * @dataProvider decayingDisabledOnProductsProvider
	 * @group woocommerce
	 *
	 * @param string       $setting   Value for `decaying_enabled`
	 * @param array|string $post_type Post types to be queried
	 * @param string       $assert    Assert method name (`assertDecayDisabled` or `assertDecayEnabled`)
	 */
	public function testDecayingDisabledOnProducts( $setting, $post_type, $assert ) {
		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		// Test decaying for product query when disabled_only_products is enabled
		ElasticPress\Features::factory()->update_feature(
			'search',
			[
				'active'           => true,
				'decaying_enabled' => $setting,
			]
		);

		$query          = new \WP_Query();
		$query_args     = [
			's'         => 'test',
			'post_type' => $post_type,
		];
		$formatted_args = \ElasticPress\Indexables::factory()->get( 'post' )->format_args( $query_args, $query );

		$this->$assert( $formatted_args['query'] );
	}

	/**
	 * Data provider for the testDecayingDisabledOnProducts method.
	 *
	 * @since 4.6.0
	 * @return array
	 */
	public function decayingDisabledOnProductsProvider() : array {
		return [
			[
				'disabled_only_products',
				'product',
				'assertDecayDisabled',
			],
			[
				'disabled_only_products',
				[ 'product' ],
				'assertDecayDisabled',
			],
			[
				'disabled_only_products',
				[ 'product', 'post' ],
				'assertDecayEnabled',
			],
			[
				'disabled_includes_products',
				'product',
				'assertDecayDisabled',
			],
			[
				'disabled_includes_products',
				[ 'product' ],
				'assertDecayDisabled',
			],
			[
				'disabled_includes_products',
				[ 'product', 'post' ],
				'assertDecayDisabled',
			],
			[
				'disabled_includes_products',
				[ 'post', 'page' ],
				'assertDecayEnabled',
			],
		];
	}
}
