<?php
/**
 * Test woocommerce products class
 *
 * @since 4.7.0
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;

/**
 * WC products test class
 */
class TestWooCommerceProduct extends BaseTestCase {
	/**
	 * Test the addition of variations skus to product meta
	 *
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
			->products
			->add_variations_skus_meta( [], $main_product_as_post );

		$this->assertArrayHasKey( '_variations_skus', $product_meta_to_index );
		$this->assertContains( 'child-sku-1', $product_meta_to_index['_variations_skus'] );
		$this->assertContains( 'child-sku-2', $product_meta_to_index['_variations_skus'] );
	}

	/**
	 * Test the translate_args_admin_products_list method
	 *
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
		add_action( 'pre_get_posts', [ $woocommerce_feature->products, 'translate_args_admin_products_list' ] );

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
		add_action( 'pre_get_posts', [ $woocommerce_feature->products, 'translate_args_admin_products_list' ] );

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
