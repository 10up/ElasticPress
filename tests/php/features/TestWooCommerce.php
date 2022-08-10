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
	public function setUp() {
		global $wpdb;
		parent::setUp();
		$wpdb->suppress_errors();

		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );

		wp_set_current_user( $admin_id );

		ElasticPress\Elasticsearch::factory()->delete_all_indices();
		ElasticPress\Indexables::factory()->get( 'post' )->put_mapping();

		ElasticPress\Indexables::factory()->get( 'post' )->sync_manager->sync_queue = [];

		$this->setup_test_post_type();

		delete_option( 'ep_active_features' );
	}

	/**
	 * Clean up after each test. Reset our mocks
	 *
	 * @since 2.1
	 * @group woocommerce
	 */
	public function tearDown() {
		parent::tearDown();

		// make sure no one attached to this
		remove_filter( 'ep_sync_terms_allow_hierarchy', array( $this, 'ep_allow_multiple_level_terms_sync' ), 100 );
		$this->fired_actions = array();
	}

	/**
	 * Test products post type query does get integrated when the feature is not active
	 *
	 * @since 2.1
	 * @group woocommerce
	 */
	public function testProductsPostTypeQueryOn() {
		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		Functions\create_and_sync_post();
		Functions\create_and_sync_post(
			array(
				'post_content' => 'product 1',
				'post_type'    => 'product',
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		add_action( 'ep_wp_query_search', array( $this, 'action_wp_query_search' ), 10, 0 );

		$args = array(
			'post_type'    => 'product',
			'ep_integrate' => true,
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );

		$this->assertTrue( ! empty( $this->fired_actions['ep_wp_query_search'] ) );
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

		Functions\create_and_sync_post();

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		add_action( 'ep_wp_query_search', array( $this, 'action_wp_query_search' ), 10, 0 );

		$args = array(
			'ep_integrate' => true,
			'tax_query'    => array(
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

		Functions\create_and_sync_post(
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

		$shop_order_id = Functions\create_and_sync_post(
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

		add_action( 'ep_wp_query_search', array( $this, 'action_wp_query_search' ), 10, 0 );

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

		parse_str( 'post_type=product&s=product&product_type=downloadable', $_GET );

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

		$search_fields_function = function() {
			return [ 'post_title', 'post_content' ];
		};
		add_filter( 'ep_woocommerce_admin_products_list_search_fields', $search_fields_function );

		$query = new \WP_Query( $query_args );
		$this->assertEquals(
			$query->query_vars['search_fields'],
			[ 'post_title', 'post_content' ]
		);

		remove_filter( 'ep_woocommerce_admin_products_list_search_fields', $search_fields_function );
	}
}
