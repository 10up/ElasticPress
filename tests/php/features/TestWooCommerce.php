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
	 * Test products post type query does get integrated when the feature is active
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

		$this->assertTrue( $query->elasticsearch_success );
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

		$args = array(
			's'         => 'findme',
			'post_type' => 'shop_order',
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );
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
	 * Test search for shop orders matching field and ID.
	 *
	 * If searching for a number that is an order ID and part of another order's metadata,
	 * both should be returned.
	 *
	 * @since 4.0.0
	 * @group woocommerce
	 */
	public function testSearchShopOrderByMetaFieldAndId() {
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		$this->assertTrue( class_exists( '\WC_Order' ) );

		$shop_order_1 = new \WC_Order();
		$shop_order_1->save();
		$shop_order_id_1 = $shop_order_1->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_1, true );

		$shop_order_2 = new \WC_Order();
		$shop_order_2->set_billing_phone( 'Phone number that matches an order ID: ' . $shop_order_id_1 );
		$shop_order_2->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_2->get_id(), true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'           => (string) $shop_order_id_1,
			'post_type'   => 'shop_order',
			'post_status' => 'any',
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
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
	 *  Test the product query order when total_sales meta key is passed.
	 *
	 * @since 4.5.0
	 */
	public function testProductQueryOrderWhenTotalSalesMetaKeyIsPass() {
		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		$product_1 = $this->ep_factory->product->create(
			array(
				'total_sales' => 200,
			)
		);

		$product_2 = $this->ep_factory->product->create(
			array(
				'total_sales' => 100,
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args  = array(
			'post_type' => 'product',
			'meta_key'  => 'total_sales',
		);
		$query = new \WP_Query( $args );

		// mock the query as main query
		global $wp_the_query;
		$wp_the_query = $query;

		add_filter(
			'ep_post_formatted_args',
			function ( $formatted_args ) {
				$expected_result = array(
					0 => array(
						'meta.total_sales.double' => array(
							'order' => 'desc',
						),
					),
					1 => array(
						'post_date' => array(
							'order' => 'desc',
						),
					),
				);

				$this->assertEquals( $expected_result, $formatted_args['sort'] );
				return $formatted_args;
			}
		);

		$query = $query->query( $args );

		$this->assertTrue( $wp_the_query->elasticsearch_success, 'Elasticsearch query failed' );
		$this->assertEquals( $product_1, $query[0]->ID );
		$this->assertEquals( $product_2, $query[1]->ID );
		$this->assertEquals( 2, count( $query ) );
	}

	/**
	 * Test the product query order when average_rating meta key is passed.
	 *
	 * @since 4.5.0
	 */
	public function testProductQueryOrderWhenAverageRatingMetaKeyIsPass() {
		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		$product_1 = $this->ep_factory->product->create(
			[
				'average_rating' => 2
			]
		);

		$product_2 = $this->ep_factory->product->create(
			[
				'average_rating' => 1
			]
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args  = array(
			'post_type' => 'product',
			'meta_key'  => '_wc_average_rating',
		);
		$query = new \WP_Query( $args );

		// mock the query as main query
		global $wp_the_query;
		$wp_the_query = $query;

		add_filter(
			'ep_post_formatted_args',
			function ( $formatted_args ) {
				$expected_result = array(
					0 => array(
						'meta._wc_average_rating.double' => array(
							'order' => 'desc',
						),
					),
					1 => array(
						'post_date' => array(
							'order' => 'desc',
						),
					),
				);

				$this->assertEquals( $expected_result, $formatted_args['sort'] );
				return $formatted_args;
			}
		);

		$query = $query->query( $args );

		$this->assertTrue( $wp_the_query->elasticsearch_success, 'Elasticsearch query failed' );
		$this->assertEquals( $product_1, $query[0]->ID );
		$this->assertEquals( $product_2, $query[1]->ID );
		$this->assertEquals( 2, count( $query ) );
	}

	/**
	 * Test the product query order when price meta key is passed.
	 *
	 * @since 4.5.0
	 */
	public function testProductQueryOrderByPrice() {
		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		$product_1 = $this->ep_factory->product->create(
			[
				'regular_price' => 2,
			]
		);

		$product_2 = $this->ep_factory->product->create(
			[
				'regular_price' => 1,
			]
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args  = array(
			'post_type' => 'product',
			'orderby'   => 'price',
			'order'     => 'DESC',
		);
		$query = new \WP_Query( $args );

		// mock the query as main query
		global $wp_the_query;
		$wp_the_query = $query;

		add_filter(
			'ep_post_formatted_args',
			function ( $formatted_args ) {

				$expected_result = array(
					0 => array(
						'meta._price.double' => array(
							'order' => 'desc',
						),
					),
					1 => array(
						'post_date' => array(
							'order' => 'desc',
						),
					),
				);

				$this->assertEquals( $expected_result, $formatted_args['sort'] );
				return $formatted_args;
			}
		);

		$query = $query->query( $args );

		$this->assertTrue( $wp_the_query->elasticsearch_success, 'Elasticsearch query failed' );
		$this->assertEquals( $product_1, $query[0]->ID );
		$this->assertEquals( $product_2, $query[1]->ID );
		$this->assertEquals( 2, count( $query ) );
	}

	/**
	 * Test the product query orderby popularity.
	 *
	 * @since 4.5.0
	 */
	public function testProductQueryOrderByPopularity() {
		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		$product_1 = $this->ep_factory->product->create(
			[
				'total_sales' => 2,
			]
		);

		$product_2 = $this->ep_factory->product->create(
			[
				'total_sales' => 1,
			]
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args  = array(
			'post_type' => 'product',
			'orderby'   => 'popularity',
			'order'     => 'DESC',
		);
		$query = new \WP_Query( $args );

		// mock the query as main query
		global $wp_the_query;
		$wp_the_query = $query;

		add_filter(
			'ep_post_formatted_args',
			function ( $formatted_args ) {
				$expected_result = array(
					0 => array(
						'meta.total_sales.double' => array(
							'order' => 'desc',
						),
					),
					1 => array(
						'post_date' => array(
							'order' => 'desc',
						),
					),
				);

				$this->assertEquals( $expected_result, $formatted_args['sort'] );
				return $formatted_args;
			}
		);

		$query = $query->query( $args );

		$this->assertTrue( $wp_the_query->elasticsearch_success, 'Elasticsearch query failed' );
		$this->assertEquals( $product_1, $query[0]->ID );
		$this->assertEquals( $product_2, $query[1]->ID );
		$this->assertEquals( 2, count( $query ) );
	}

	/**
	 * Test the product query orderby popularity when orderby is passed as a query string.
	 *
	 * @since 4.5.0
	 */
	public function testProductQueryOrderByPopularityQueryString() {
		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		$product_1 = $this->ep_factory->product->create(
			[
				'total_sales' => 2
			]
		);

		$product_2 = $this->ep_factory->product->create(
			[
				'total_sales' => 1
			]
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		parse_str( 'orderby=popularity', $_GET );

		$args  = array(
			'post_type' => 'product',
		);
		$query = new \WP_Query( $args );

		// mock the query as main query
		global $wp_the_query;
		$wp_the_query = $query;

		add_filter(
			'ep_post_formatted_args',
			function ( $formatted_args ) {
				$expected_result = array(
					0 => array(
						'meta.total_sales.double' => array(
							'order' => 'desc',
						),
					),
					1 => array(
						'post_date' => array(
							'order' => 'desc',
						),
					),
				);

				$this->assertEquals( $expected_result, $formatted_args['sort'] );

				return $formatted_args;
			}
		);

		$query = $query->query( $args );

		$this->assertTrue( $wp_the_query->elasticsearch_success, 'Elasticsearch query failed' );
		$this->assertEquals( $product_1, $query[0]->ID );
		$this->assertEquals( $product_2, $query[1]->ID );
		$this->assertEquals( 2, count( $query ) );
	}

	/**
	 * Test the product query orderby price when orderby is passed as a query string.
	 *
	 * @since 4.5.0
	 */
	public function testProductQueryOrderByPriceQueryString() {
		 ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		$product_1 = $this->ep_factory->post->create(
			array(
				'post_type'  => 'product',
				'meta_input' => array( '_price' => 2 ),
			)
		);

		$product_2 = $this->ep_factory->post->create(
			array(
				'post_type'  => 'product',
				'meta_input' => array( '_price' => 1 ),
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		parse_str( 'orderby=price', $_GET );

		$args  = array(
			'post_type' => 'product',
		);
		$query = new \WP_Query( $args );

		// mock the query as main query
		global $wp_the_query;
		$wp_the_query = $query;

		add_filter(
			'ep_post_formatted_args',
			function ( $formatted_args ) {
				$expected_result = array(
					0 => array(
						'meta._price.double' => array(
							'order' => 'asc',
						),
					),
					1 => array(
						'post_date' => array(
							'order' => 'asc',
						),
					),
				);

				$this->assertEquals( $expected_result, $formatted_args['sort'] );

				return $formatted_args;
			}
		);

		$query = $query->query( $args );

		$this->assertTrue( $wp_the_query->elasticsearch_success, 'Elasticsearch query failed' );
		$this->assertEquals( $product_2, $query[0]->ID );
		$this->assertEquals( $product_1, $query[1]->ID );
		$this->assertEquals( 2, count( $query ) );
	}

	/**
	 * Test the product query orderby price-desc.
	 *
	 * @since 4.5.0
	 */
	public function testProductQueryOrderByPriceDesc() {
		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		$product_1 = $this->ep_factory->product->create(
			[
				'regular_price' => 2
			]
		);

		$product_2 = $this->ep_factory->product->create(
			[
				'regular_price' => 1
			]
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		parse_str( 'orderby=price-desc', $_GET );

		$args  = array(
			'post_type' => 'product',
		);
		$query = new \WP_Query( $args );

		// mock the query as main query
		global $wp_the_query;
		$wp_the_query = $query;

		add_filter(
			'ep_post_formatted_args',
			function ( $formatted_args ) {
				$expected_result = array(
					0 => array(
						'meta._price.double' => array(
							'order' => 'desc',
						),
					),
					1 => array(
						'post_date' => array(
							'order' => 'desc',
						),
					),
				);

				$this->assertEquals( $expected_result, $formatted_args['sort'] );

				return $formatted_args;
			}
		);

		$query = $query->query( $args );

		$this->assertTrue( $wp_the_query->elasticsearch_success, 'Elasticsearch query failed' );
		$this->assertEquals( $product_1, $query[0]->ID );
		$this->assertEquals( $product_2, $query[1]->ID );
		$this->assertEquals( 2, count( $query ) );
	}

	/**
	 * Test the product query orderby rating.
	 *
	 * @since 4.5.0
	 */
	public function testProductQueryOrderByRating() {
		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		$product_1 = $this->ep_factory->product->create(
			[
				'average_rating' => 2,
			]
		);

		$product_2 = $this->ep_factory->product->create(
			[
				'average_rating' => 1,
			]
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		parse_str( 'orderby=rating', $_GET );

		$args  = array(
			'post_type' => 'product',
		);
		$query = new \WP_Query( $args );

		// mock the query as main query
		global $wp_the_query;
		$wp_the_query = $query;

		add_filter(
			'ep_post_formatted_args',
			function ( $formatted_args ) {
				$expected_result = array(
					0 => array(
						'meta._wc_average_rating.double' => array(
							'order' => 'desc',
						),
					),
					1 => array(
						'post_date' => array(
							'order' => 'desc',
						),
					),
				);

				$this->assertEquals( $expected_result, $formatted_args['sort'] );

				return $formatted_args;
			}
		);

		$query = $query->query( $args );

		$this->assertTrue( $wp_the_query->elasticsearch_success, 'Elasticsearch query failed' );
		$this->assertEquals( $product_1, $query[0]->ID );
		$this->assertEquals( $product_2, $query[1]->ID );
		$this->assertEquals( 2, count( $query ) );
	}

	/**
	 * Test the product query orderby sku query string.
	 *
	 * @since 4.5.0
	 */
	public function testProductQueryOrderBySkuQueryString() {
		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		$product_1 = $this->ep_factory->product->create(
			[
				'sku' => 2,
			]
		);

		$product_2 = $this->ep_factory->product->create(
			[
				'sku' => 1,
			]
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		parse_str( 'orderby=sku', $_GET );

		$args  = array(
			'post_type' => 'product',
		);
		$query = new \WP_Query( $args );

		// mock the query as main query
		global $wp_the_query;
		$wp_the_query = $query;

		add_filter(
			'ep_post_formatted_args',
			function ( $formatted_args ) {
				$expected_result = array(
					0 => array(
						'meta._sku.value.sortable' => array(
							'order' => 'desc',
						),
					),
					1 => array(
						'post_date' => array(
							'order' => 'desc',
						),
					),
				);

				$this->assertEquals( $expected_result, $formatted_args['sort'] );

				return $formatted_args;
			}
		);

		$query = $query->query( $args );

		$this->assertTrue( $wp_the_query->elasticsearch_success, 'Elasticsearch query failed' );
		$this->assertEquals( $product_1, $query[0]->ID );
		$this->assertEquals( $product_2, $query[1]->ID );
		$this->assertEquals( 2, count( $query ) );
	}

	/**
	 * Test the product query orderby title query string.
	 *
	 * @since 4.5.0
	 */
	public function testProductQueryOrderByTitleQueryString() {
		 ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		$product_1 = $this->ep_factory->product->create(
			[
				'name' => 'Banana',
			]
		);

		$product_2 = $this->ep_factory->product->create(
			[
				'name' => 'Apple',
			]
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		parse_str( 'orderby=title', $_GET );

		$args  = array(
			'post_type' => 'product',
		);
		$query = new \WP_Query( $args );

		// mock the query as main query
		global $wp_the_query;
		$wp_the_query = $query;

		add_filter(
			'ep_post_formatted_args',
			function ( $formatted_args ) {
				$expected_result = array(
					0 => array(
						'post_title.sortable' => array(
							'order' => 'desc',
						),
					),
					1 => array(
						'post_date' => array(
							'order' => 'desc',
						),
					),
				);

				$this->assertEquals( $expected_result, $formatted_args['sort'] );

				return $formatted_args;
			}
		);

		$query = $query->query( $args );

		$this->assertTrue( $wp_the_query->elasticsearch_success, 'Elasticsearch query failed' );
		$this->assertEquals( $product_1, $query[0]->ID );
		$this->assertEquals( $product_2, $query[1]->ID );
		$this->assertEquals( 2, count( $query ) );
	}

	/**
	 * Test the product query orderby default query string.
	 *
	 * @since 4.5.0
	 */
	public function testProductQueryOrderByDefault() {
		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		$this->ep_factory->product->create_many( 2 );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		parse_str( 'orderby=default', $_GET );

		$args  = array(
			'post_type' => 'product',
		);
		$query = new \WP_Query( $args );

		// mock the query as main query
		global $wp_the_query;
		$wp_the_query = $query;

		add_filter(
			'ep_post_formatted_args',
			function ( $formatted_args ) {
				$expected_result = array(
					0 => array(
						'menu_order' => array(
							'order' => 'desc',
						),
					),
					1 => array(
						'post_title.sortable' => array(
							'order' => 'desc',
						),
					),
					2 => array(
						'post_date' => array(
							'order' => 'desc',
						),
					),
				);

				$this->assertEquals( $expected_result, $formatted_args['sort'] );

				return $formatted_args;
			}
		);

		$query = $query->query( $args );

		$this->assertTrue( $wp_the_query->elasticsearch_success, 'Elasticsearch query failed' );
		$this->assertEquals( 2, count( $query ) );
	}

	/**
	 * Test the product query orderby date when query string is empty.
	 *
	 * @since 4.5.0
	 */
	public function testProductQueryOrderByDateWhenQueryStringIsEmpty() {
		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		$this->ep_factory->product->create_many( 2 );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		parse_str( 'orderby=', $_GET );

		$args  = array(
			'post_type' => 'product',
		);
		$query = new \WP_Query( $args );

		// mock the query as main query
		global $wp_the_query;
		$wp_the_query = $query;

		add_filter(
			'ep_post_formatted_args',
			function ( $formatted_args ) {
				$expected_result = array(
					0 => array(
						'post_date' => array(
							'order' => 'desc',
						),
					),
				);

				$this->assertEquals( $expected_result, $formatted_args['sort'] );

				return $formatted_args;
			}
		);

		$query = $query->query( $args );

		$this->assertTrue( $wp_the_query->elasticsearch_success, 'Elasticsearch query failed' );
		$this->assertEquals( 2, count( $query ) );
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
		set_current_screen( 'edit.php' );
		$this->assertTrue( is_admin() );

		// load required files
		include_once ABSPATH . 'wp-admin/includes/class-wp-posts-list-table.php';
		include_once WC()->plugin_path() . '/includes/admin/list-tables/class-wc-admin-list-table-products.php';

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		global $typenow, $wc_list_table;

		// mock the global variables
		$typenow       = 'product';
		$wc_list_table = new \WC_Admin_List_Table_Products();

		add_filter( 'ep_post_filters', function( $filters, $args, $query ) {
			$expected_result = array(
				'terms' => array(
					'post_type.raw' => array(
						'product',
					),
				),
			);

			$this->assertEquals( $expected_result, $filters['post_type'] );
			return $filters;
		}, 10, 3 );

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
		set_current_screen( 'edit.php' );
		$this->assertTrue( is_admin() );

		// load required files
		include_once ABSPATH . 'wp-admin/includes/class-wp-posts-list-table.php';
		include_once WC()->plugin_path() . '/includes/admin/list-tables/class-wc-admin-list-table-products.php';

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		global $typenow, $wc_list_table;

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
		global $wp_the_query, $wp_query;
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
		global $wp_the_query, $wp_query;
		$wp_the_query        = $query;
		$wp_query->is_search = true;

		$query = $query->query( $args );

		$this->assertTrue( $wp_the_query->elasticsearch_success, 'Elasticsearch query failed' );
		$this->assertEquals( 2, count( $query ) );
	}


	public function testAttributesFilterUseES() {
		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		$this->ep_factory->product->create_variation_product(
			[
				'name' => 'Cap'
			]
		);

		$this->ep_factory->product->create(
			[
				'name' => 'Shoes'
			]
		);

		$this->ep_factory->product->create(
			[
				'name' => 'T-Shirt'
			]
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		parse_str( 'filter_color=blue', $_GET );

		update_option( 'show_on_front', 'page' );

		$args  = array(
			'post_type' => 'product',

		);
		$query = new \WP_Query( $args );

		// mock the query as main query and is_search
		global $wp_the_query, $wp_query;
		$wp_the_query        = $query;
		$wp_query->is_posts_page = false;
		$wp_query->is_home = true;
		$wp_query->is_post_type_archive = 'product';

		$query = $query->query( $args );



		$this->assertTrue( $wp_the_query->elasticsearch_success, 'Elasticsearch query failed' );







	}
}
