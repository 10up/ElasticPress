<?php
/**
 * Test woocommerce orders class
 *
 * @since 4.7.0
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;

require_once __DIR__ . '/WooCommerceBaseTestCase.php';

/**
 * WC orders test class
 */
class TestWooCommerceOrders extends WooCommerceBaseTestCase {
	/**
	 * Orders instance
	 *
	 * @var Orders
	 */
	protected $orders;

	/**
	 * Setup each test.
	 *
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function set_up() {
		parent::set_up();
		$this->orders = ElasticPress\Features::factory()->get_registered_feature( 'woocommerce' )->orders;
	}

	/**
	 * Test search integration is on for shop orders
	 *
	 * @group woocommerce
	 * @group woocommerce-orders
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
	 * @group woocommerce
	 * @group woocommerce-orders
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
	 * @group woocommerce
	 * @group woocommerce-orders
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
	 * @group woocommerce
	 * @group woocommerce-orders
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
	 * @group woocommerce
	 * @group woocommerce-orders
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
	 * @group woocommerce
	 * @group woocommerce-orders
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
	 * Test the `get_admin_searchable_post_types` method
	 *
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function testGetAdminSearchablePostTypes() {
		$default_post_types = $this->orders->get_admin_searchable_post_types();
		$this->assertSame( $default_post_types, [ 'shop_order' ] );

		/**
		 * Test the `ep_woocommerce_admin_searchable_post_types` filter
		 */
		$add_post_type = function ( $post_types ) {
			$post_types[] = 'shop_order_custom';
			return $post_types;
		};
		add_filter( 'ep_woocommerce_admin_searchable_post_types', $add_post_type );

		$new_post_types = $this->orders->get_admin_searchable_post_types();
		$this->assertSame( $new_post_types, [ 'shop_order', 'shop_order_custom' ] );
	}

	/**
	 * Test the `get_supported_post_types` method
	 *
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function testGetSupportedPostTypes() {
		$default_supported = $this->orders->get_supported_post_types();
		$this->assertSame( $default_supported, [] );

		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		$default_supported = $this->orders->get_supported_post_types();
		$this->assertSame( $default_supported, [ 'shop_order', 'shop_order_refund' ] );

		/**
		 * Test the `ep_woocommerce_orders_supported_post_types` filter
		 */
		$add_post_type = function( $post_types ) {
			$post_types[] = 'shop_order_custom';
			return $post_types;
		};
		add_filter( 'ep_woocommerce_orders_supported_post_types', $add_post_type );

		$custom_supported = $this->orders->get_supported_post_types();
		$this->assertSame( $custom_supported, [ 'shop_order', 'shop_order_refund' ] );
	}

	/**
	 * Test if methods moved to OrdersAutosuggest are correctly flagged
	 *
	 * @param string $method The method name
	 * @param array  $args   Method arguments
	 * @dataProvider ordersAutosuggestMethodsDataProvider
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function testOrdersAutosuggestMethods( $method, $args ) {
		$this->setExpectedDeprecated( "\ElasticPress\Feature\WooCommerce\WooCommerce\Orders::{$method}" );
		$this->orders->$method( ...$args );
	}

	/**
	 * Data provider for the testOrdersAutosuggestMethods method.
	 *
	 * @return array
	 */
	public function ordersAutosuggestMethodsDataProvider() : array {
		return [
			[ 'after_update_feature', [ 'test', [], [] ] ],
			[ 'check_token_permission', [] ],
			[ 'enqueue_admin_assets', [ '' ] ],
			[ 'epio_delete_search_template', [] ],
			[ 'epio_get_search_template', [] ],
			[ 'epio_save_search_template', [] ],
			[ 'filter_term_suggest', [ [] ] ],
			[ 'get_args_schema', [] ],
			[ 'get_search_endpoint', [] ],
			[ 'get_search_template', [] ],
			[ 'get_template_endpoint', [] ],
			[ 'get_token', [] ],
			[ 'get_token_endpoint', [] ],
			[ 'intercept_search_request', [ (object) [] ] ],
			[ 'is_integrated_request', [ true, [] ] ],
			[ 'post_statuses', [ [] ] ],
			[ 'post_types', [ [] ] ],
			[ 'mapping', [ [] ] ],
			[ 'maybe_query_password_protected_posts', [ [] ] ],
			[ 'maybe_set_posts_where', [ '', new \WP_Query( [] ) ] ],
			[ 'refresh_token', [] ],
			[ 'rest_api_init', [] ],
			[ 'set_search_fields', [] ],
		];
	}
}
