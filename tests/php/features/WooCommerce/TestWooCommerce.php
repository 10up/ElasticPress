<?php
/**
 * Test woocommerce feature
 *
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;

require_once __DIR__ . '/WooCommerceBaseTestCase.php';

/**
 * WC test class
 */
class TestWooCommerce extends WooCommerceBaseTestCase {
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
	 * Tests the search query for a shop_coupon.
	 *
	 * @since 4.4.1
	 * @group woocommerce
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
	 * @group woocommerce
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
	 * @group woocommerce
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
	 * Test the `is_orders_autosuggest_available` method
	 *
	 * @since 4.5.0
	 * @group woocommerce
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
	 * @group woocommerce
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
	 * Test WooCommerce settings schema
	 *
	 * @since 5.0.0
	 * @group woocommerce
	 */
	public function test_get_settings_schema() {
		$woocommerce_feature = ElasticPress\Features::factory()->get_registered_feature( 'woocommerce' );
		$settings_schema     = $woocommerce_feature->get_settings_schema();

		$settings_keys = wp_list_pluck( $settings_schema, 'key' );

		$this->assertSame(
			[ 'active', 'orders' ],
			$settings_keys
		);
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
}
