<?php
/**
 * Test woocommerce orders class
 *
 * @since 4.7.0
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;
use Automattic\WooCommerce\Enums\OrderStatus;

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
		ElasticPress\Elasticsearch::factory()->delete_all_indices();
		ElasticPress\Indexables::factory()->get( 'post' )->put_mapping();

		ElasticPress\Indexables::factory()->get( 'post' )->sync_manager->reset_sync_queue();

		$this->setup_test_post_type();

		$this->orders = ElasticPress\Features::factory()->get_registered_feature( 'woocommerce' )->orders;

		$this->orders->clear_elasticsearch_success_order_ids();
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
			[
				'post_content' => 'findme',
				'post_type'    => 'shop_order',
			]
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		// mock the pagenow to bypass the search_order checks
		global $pagenow;
		$pagenow = 'edit.php';

		parse_str( 's=findme', $_GET );
		$args = [
			's'         => 'findme',
			'post_type' => 'shop_order',
		];

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
			[
				'post_type' => 'shop_order',
			]
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args  = [
			'post_type' => 'shop_order',
		];
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
			[
				'post_type' => 'shop_order',
			]
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args  = [
			'post_type' => 'shop_order',
		];
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
			[
				'post_type' => 'shop_order',
			]
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args  = [
			'post_type'    => 'shop_order',
			'ep_integrate' => false,
		];
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
			[
				'post_type' => 'shop_order',
			]
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = [
			's'         => (string) $shop_order_id,
			'post_type' => 'shop_order',
		];

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

		$args = [
			's'           => (string) $shop_order_id_1,
			'post_type'   => 'shop_order',
			'post_status' => 'any',
		];

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
		$add_post_type = function ( $post_types ) {
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
	public function ordersAutosuggestMethodsDataProvider(): array {
		return [
			[ 'after_update_feature', [ 'test', [], [] ] ],
			[ 'enqueue_admin_assets', [ '' ] ],
			[ 'epio_delete_search_template', [] ],
			[ 'epio_get_search_template', [] ],
			[ 'epio_save_search_template', [] ],
			[ 'filter_term_suggest', [ [] ] ],
			[ 'get_args_schema', [] ],
			[ 'get_search_endpoint', [] ],
			[ 'get_search_template', [] ],
			[ 'get_template_endpoint', [] ],
			[ 'intercept_search_request', [ (object) [] ] ],
			[ 'is_integrated_request', [ true, [] ] ],
			[ 'post_statuses', [ [] ] ],
			[ 'post_types', [ [] ] ],
			[ 'mapping', [ [] ] ],
			[ 'maybe_query_password_protected_posts', [ [] ] ],
			[ 'maybe_set_posts_where', [ '', new \WP_Query( [] ) ] ],
			[ 'rest_api_init', [] ],
			[ 'set_search_fields', [] ],
		];
	}

	/**
	 * Test the `hpos_compatibility_notice` method
	 *
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_compatibility_notice() {
		$notices = [
			'test' => [],
		];
		$this->assertCount( 1, $this->orders->hpos_compatibility_notice( $notices ) );

		\set_current_screen( 'woocommerce_page_wc-orders' );
		$this->assertCount( 1, $this->orders->hpos_compatibility_notice( $notices ) );

		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		$this->assertCount( 1, $this->orders->hpos_compatibility_notice( $notices ) );

		// Force an unsupported WooCommerce version requirement.
		$change_min_version = function () {
			return '99.0.0';
		};
		add_filter( 'ep_woocommerce_hpos_min_version', $change_min_version );

		// While orders are stored as posts there is nothing to warn about.
		$this->assertCount( 1, $this->orders->hpos_compatibility_notice( $notices ) );

		$this->enable_hpos();

		$new_notices = $this->orders->hpos_compatibility_notice( $notices );
		$this->assertCount( 2, $new_notices );
		$this->assertArrayHasKey( 'wc_orders_incompatible', $new_notices );
		$this->assertStringContainsString( 'requires WooCommerce 99.0.0 or greater', $new_notices['wc_orders_incompatible']['html'] );

		/**
		 * Test if the notice is hidden when the user already dismissed it
		 */
		$change_hide_option = function () {
			return 1;
		};
		add_filter( 'pre_option_ep_hide_wc_orders_incompatible_notice', $change_hide_option );
		add_filter( 'pre_site_option_ep_hide_wc_orders_incompatible_notice', $change_hide_option );
		$new_notices = $this->orders->hpos_compatibility_notice( $notices );
		$this->assertCount( 1, $new_notices );
		$this->assertArrayNotHasKey( 'wc_orders_incompatible', $new_notices );
	}

	/**
	 * Test the notice displayed when HPOS query integration is disabled.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_query_integration_disabled_notice() {
		$notices = [
			'test' => [],
		];

		set_current_screen( 'woocommerce_page_wc-orders' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		$this->enable_hpos();

		add_filter(
			'ep_woocommerce_hpos_min_version',
			function () {
				return '0.0.0';
			}
		);

		$disable_hpos_query_integration = function () {
			return [
				'protected_content' => [
					'active' => true,
				],
				'woocommerce'       => [
					'active'       => true,
					'disable_hpos' => '1',
				],
			];
		};
		add_filter( 'pre_option_ep_feature_settings', $disable_hpos_query_integration );
		add_filter( 'pre_site_option_ep_feature_settings', $disable_hpos_query_integration );

		$new_notices = $this->orders->hpos_compatibility_notice( $notices );

		$this->assertArrayHasKey( 'wc_orders_hpos_query_integration_disabled', $new_notices );
		$this->assertStringContainsString(
			'orders are not being retrieved from Elasticsearch',
			$new_notices['wc_orders_hpos_query_integration_disabled']['html']
		);

		$change_hide_option = function () {
			return 1;
		};
		add_filter(
			'pre_option_ep_hide_wc_orders_hpos_query_integration_disabled_notice',
			$change_hide_option
		);
		add_filter(
			'pre_site_option_ep_hide_wc_orders_hpos_query_integration_disabled_notice',
			$change_hide_option
		);

		$new_notices = $this->orders->hpos_compatibility_notice( $notices );
		$this->assertArrayNotHasKey( 'wc_orders_hpos_query_integration_disabled', $new_notices );
	}

	/**
	 * Test the `is_hpos_enabled` method
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_is_hpos_enabled() {
		$this->assertFalse( $this->orders->is_hpos_enabled() );

		$this->enable_hpos();

		$this->assertTrue( $this->orders->is_hpos_enabled() );
	}

	/**
	 * Test the `is_hpos_query_integration_disabled` method.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_is_hpos_query_integration_disabled() {
		$this->assertFalse( $this->orders->is_hpos_query_integration_disabled() );

		$disable_hpos_query_integration = function () {
			return [
				'woocommerce' => [
					'disable_hpos' => '1',
				],
			];
		};
		add_filter( 'pre_option_ep_feature_settings', $disable_hpos_query_integration );
		add_filter( 'pre_site_option_ep_feature_settings', $disable_hpos_query_integration );

		$this->assertTrue( $this->orders->is_hpos_query_integration_disabled() );
	}

	/**
	 * Test the HPOS settings schema.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_add_hpos_settings_schema() {
		$settings_schema = $this->orders->add_settings_schema( [] );

		$this->assertSame( 'disable_hpos', $settings_schema[0]['key'] );
		$this->assertSame( '0', $settings_schema[0]['default'] );
		$this->assertTrue( $settings_schema[0]['disabled'] );
		$this->assertFalse( $settings_schema[0]['requires_sync'] );

		$this->enable_hpos();

		$settings_schema = $this->orders->add_settings_schema( [] );
		$this->assertFalse( $settings_schema[0]['disabled'] );
	}

	/**
	 * Test HPOS settings appear after the orders autosuggest setting.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_settings_schema_comes_after_orders_autosuggest() {
		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		$woocommerce = ElasticPress\Features::factory()->get_registered_feature( 'woocommerce' );
		$woocommerce->reset_settings_schema();
		$settings_schema = $woocommerce->get_settings_schema();

		$settings_keys = array_values(
			array_filter(
				array_column( $settings_schema, 'key' ),
				function ( $key ) {
					return in_array( $key, [ 'orders', 'disable_hpos' ], true );
				}
			)
		);

		$this->assertSame( [ 'orders', 'disable_hpos' ], $settings_keys );
	}

	/**
	 * Test HPOS query integration can be disabled while sync hooks remain active.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_query_integration_can_be_disabled() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$shop_order = new \WC_Order();
		$shop_order->save();
		$shop_order_id = $shop_order->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id, true );
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$orders = wc_get_orders( [] );
		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertEquals( $shop_order_id, $orders[0]->get_id() );

		$this->orders->clear_elasticsearch_success_order_ids();

		$disable_hpos_query_integration = function () {
			return [
				'protected_content' => [
					'active' => true,
				],
				'woocommerce'       => [
					'active'       => true,
					'disable_hpos' => '1',
				],
			];
		};
		add_filter( 'pre_option_ep_feature_settings', $disable_hpos_query_integration );
		add_filter( 'pre_site_option_ep_feature_settings', $disable_hpos_query_integration );

		$orders_hpos_property = new \ReflectionProperty( $this->orders, 'orders_hpos' );
		$orders_hpos_property->setAccessible( true );
		$orders_hpos = $orders_hpos_property->getValue( $this->orders );

		$this->assertSame(
			10,
			has_filter( 'woocommerce_hpos_pre_query', [ $orders_hpos, 'maybe_intercept_wc_orders_query' ] )
		);
		$this->assertSame( 10, has_action( 'woocommerce_new_order', [ $orders_hpos, 'sync_order' ] ) );

		$orders = wc_get_orders( [] );
		$this->assertFalse( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertEquals( $shop_order_id, $orders[0]->get_id() );
		$this->assertCount( 1, $orders );
	}

	/**
	 * Test HPOS query integration is skipped while posts-table data sync is enabled.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_query_integration_skips_when_data_sync_enabled() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$shop_order = new \WC_Order();
		$shop_order->save();
		$shop_order_id = $shop_order->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id, true );
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$data_sync_option = \Automattic\WooCommerce\Internal\DataStores\Orders\DataSynchronizer::ORDERS_DATA_SYNC_ENABLED_OPTION;
		$enable_data_sync = function () {
			return 'yes';
		};
		add_filter( 'pre_option_' . $data_sync_option, $enable_data_sync );

		$orders = wc_get_orders( [ 'id' => $shop_order_id ] );

		remove_filter( 'pre_option_' . $data_sync_option, $enable_data_sync );

		$this->assertCount( 1, $orders );
		$this->assertEquals( $shop_order_id, $orders[0]->get_id() );
		$this->assertFalse( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
	}

	/**
	 * Test HPOS compatibility notice is hidden when query integration is disabled.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_compatibility_notice_hidden_when_query_integration_disabled() {

		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		$notices = [
			'test' => [],
		];

		set_current_screen( 'woocommerce_page_wc-orders' );

		add_filter(
			'ep_woocommerce_hpos_min_version',
			function () {
				return '99.0.0';
			}
		);
		$this->enable_hpos();

		$new_notices = $this->orders->hpos_compatibility_notice( $notices );
		$this->assertArrayHasKey( 'wc_orders_incompatible', $new_notices );

		$disable_hpos_query_integration = function () {
			return [
				'protected_content' => [
					'active' => true,
				],
				'woocommerce'       => [
					'active'       => true,
					'disable_hpos' => '1',
				],
			];
		};
		add_filter( 'pre_option_ep_feature_settings', $disable_hpos_query_integration );
		add_filter( 'pre_site_option_ep_feature_settings', $disable_hpos_query_integration );

		$new_notices = $this->orders->hpos_compatibility_notice( $notices );
		$this->assertArrayNotHasKey( 'wc_orders_incompatible', $new_notices );
	}

	/**
	 * Utility function to enable WooCommerce HPOS
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	protected function enable_hpos() {
		$option_name = \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::CUSTOM_ORDERS_TABLE_USAGE_ENABLED_OPTION;
		add_filter(
			'pre_option_' . $option_name,
			function () {
				return 'yes';
			}
		);
	}

	/**
	 * Test if Order and Order Refunds index correctly
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_data_is_indexed() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$shop_order_1 = new \WC_Order();
		$shop_order_1->save();
		$shop_order_id_1 = $shop_order_1->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_1, true );

		$refund_order = new \WC_Order_Refund();

		$refund_order->set_parent_id( $shop_order_id_1 );
		$refund_order->set_amount( 20 );
		$refund_order->set_reason( 'Full refund example' );
		$refund_order->set_refunded_by( get_current_user_id() );
		$refund_order->set_status( 'completed' );

		$refund_order->save();
		$refund_order_id = $refund_order->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $refund_order_id, true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$orders = wc_get_orders(
			[
				'post_type' => [ 'shop_order_refund' ],
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertEquals( $refund_order_id, $orders[0]->get_id() );
		$this->assertEquals( $shop_order_id_1, $orders[0]->get_parent_id() );
		$this->assertEquals( 20, $orders[0]->get_amount() );
		$this->assertEquals( 'Full refund example', $orders[0]->get_reason() );
		$this->assertEquals( get_current_user_id(), $orders[0]->get_refunded_by() );
		$this->assertEquals( 'completed', $orders[0]->get_status() );
		$this->assertCount( 1, $orders );
	}

	/**
	 * Test simple HPOS search
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_order_search() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$shop_order_1 = new \WC_Order();
		$shop_order_1->save();
		$shop_order_id_1 = $shop_order_1->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_1, true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$orders = wc_get_orders( [] );

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertEquals( $shop_order_id_1, $orders[0]->get_id() );
		$this->assertCount( 1, $orders );
	}

	/**
	 * Test HPOS search with post type filter.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_search_with_post_type() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$shop_order_1 = new \WC_Order();
		$shop_order_1->save();
		$shop_order_id_1 = $shop_order_1->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_1, true );

		$refund_order = new \WC_Order_Refund();
		$refund_order->set_parent_id( $shop_order_id_1 );
		$refund_order->save();
		$refund_order_id = $refund_order->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $refund_order_id, true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$orders = wc_get_orders(
			[
				'post_type' => [ 'shop_order_refund' ],
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertEquals( $refund_order_id, $orders[0]->get_id() );
		$this->assertEquals( $shop_order_id_1, $orders[0]->get_parent_id() );
		$this->assertCount( 1, $orders );

		// Test if all the orders are returned.
		$orders = wc_get_orders( [] );
		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 2, $orders );
	}

	/**
	 * Test HPOS query filter by status.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_filter_by_status() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$shop_order_1 = new \WC_Order();
		$shop_order_1->set_status( OrderStatus::COMPLETED );
		$shop_order_1->save();
		$shop_order_id_1 = $shop_order_1->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_1, true );

		$shop_order_2 = new \WC_Order();
		$shop_order_2->set_status( OrderStatus::PENDING );
		$shop_order_2->save();
		$shop_order_id_2 = $shop_order_2->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_2, true );

		$shop_order_3 = new \WC_Order();
		$shop_order_3->set_status( OrderStatus::ON_HOLD );
		$shop_order_3->save();
		$shop_order_id_3 = $shop_order_3->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_3, true );

		$shop_order_4 = new \WC_Order();
		$shop_order_4->set_status( OrderStatus::PROCESSING );
		$shop_order_4->save();
		$shop_order_id_4 = $shop_order_4->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_4, true );

		$shop_order_5 = new \WC_Order();
		$shop_order_5->set_status( OrderStatus::CHECKOUT_DRAFT );
		$shop_order_5->save();
		$shop_order_id_5 = $shop_order_5->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_5, true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		// Return All the orders
		$orders = wc_get_orders( [] );
		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 5, $orders );

		// Return only the orders with the status "completed"
		$orders = wc_get_orders( [ 'status' => OrderStatus::COMPLETED ] );
		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 1, $orders );

		// Return only the orders with the status "pending"
		$orders = wc_get_orders( [ 'status' => OrderStatus::PENDING ] );
		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 1, $orders );

		// Return only the orders with the status "on-hold"
		$orders = wc_get_orders( [ 'status' => OrderStatus::ON_HOLD ] );
		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 1, $orders );

		// Return only the orders with the status "processing"
		$orders = wc_get_orders( [ 'status' => OrderStatus::PROCESSING ] );
		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 1, $orders );

		// Return only the orders with the status "checkout-draft"
		$orders = wc_get_orders( [ 'status' => OrderStatus::CHECKOUT_DRAFT ] );
		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 1, $orders );
	}

	/**
	 * Test HPOS limit.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_order_limit() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$shop_order_1 = new \WC_Order();
		$shop_order_1->save();
		$shop_order_id_1 = $shop_order_1->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_1, true );

		$shop_order_2 = new \WC_Order();
		$shop_order_2->save();
		$shop_order_id_2 = $shop_order_2->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_2, true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$orders = wc_get_orders(
			[
				'limit'   => 1,
				'orderby' => 'ID',
				'order'   => 'ASC',
			]
		);
		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 1, $orders );
		$this->assertEquals( $shop_order_id_1, $orders[0]->get_id() );
	}

	/**
	 * Test HPOS paged.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_paged() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$shop_order_1 = new \WC_Order();
		$shop_order_1->save();
		$shop_order_id_1 = $shop_order_1->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_1, true );

		$shop_order_2 = new \WC_Order();
		$shop_order_2->save();
		$shop_order_id_2 = $shop_order_2->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_2, true );

		$shop_order_3 = new \WC_Order();
		$shop_order_3->save();
		$shop_order_id_3 = $shop_order_3->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_3, true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$orders = wc_get_orders(
			[
				'paged'   => 2,
				'limit'   => 1,
				'orderby' => 'date',
				'order'   => 'ASC',
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 1, $orders );
		$this->assertEquals( $shop_order_id_2, $orders[0]->get_id() );

		$orders = wc_get_orders(
			[
				'paged'   => 3,
				'limit'   => 1,
				'orderby' => 'date',
				'order'   => 'ASC',
			]
		);
		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 1, $orders );
		$this->assertEquals( $shop_order_id_3, $orders[0]->get_id() );
	}

	/**
	 * Test HPOS order by date. By default, the orders are ordered by date in Descending order.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_order_by_date() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$shop_order_1 = new \WC_Order();
		$shop_order_1->set_date_created( new \WC_DateTime( '2026-01-01 12:00:00' ) );
		$shop_order_1->save();
		$shop_order_id_1 = $shop_order_1->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_1, true );

		$shop_order_2 = new \WC_Order();
		$shop_order_2->set_date_created( new \WC_DateTime( '2026-01-02 12:00:00' ) );
		$shop_order_2->save();
		$shop_order_id_2 = $shop_order_2->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_2, true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$orders = wc_get_orders(
			[
				'orderby' => 'date',
			]
		);
		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 2, $orders );
		$this->assertEquals( $shop_order_id_2, $orders[0]->get_id() );
		$this->assertEquals( $shop_order_id_1, $orders[1]->get_id() );
	}

	/**
	 * Test HPOS order by date in Ascending order.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_order_by_date_asc() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$shop_order_1 = new \WC_Order();
		$shop_order_1->set_date_created( new \WC_DateTime( '2026-01-01 12:00:00' ) );
		$shop_order_1->save();
		$shop_order_id_1 = $shop_order_1->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_1, true );

		$shop_order_2 = new \WC_Order();
		$shop_order_2->set_date_created( new \WC_DateTime( '2026-01-02 12:00:00' ) );
		$shop_order_2->save();
		$shop_order_id_2 = $shop_order_2->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_2, true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$orders = wc_get_orders(
			[
				'orderby' => 'date',
				'order'   => 'ASC',

			]
		);
		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 2, $orders );
		$this->assertEquals( $shop_order_id_1, $orders[0]->get_id() );
		$this->assertEquals( $shop_order_id_2, $orders[1]->get_id() );
	}

	/**
	 * Test HPOS orderby as an array of field => direction pairs.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_order_by_array() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$shop_order_1 = new \WC_Order();
		$shop_order_1->set_date_created( new \WC_DateTime( '2026-01-01 12:00:00' ) );
		$shop_order_1->save();
		$shop_order_id_1 = $shop_order_1->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_1, true );

		$shop_order_2 = new \WC_Order();
		$shop_order_2->set_date_created( new \WC_DateTime( '2026-01-01 12:00:00' ) );
		$shop_order_2->save();
		$shop_order_id_2 = $shop_order_2->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_2, true );

		$shop_order_3 = new \WC_Order();
		$shop_order_3->set_date_created( new \WC_DateTime( '2026-01-02 12:00:00' ) );
		$shop_order_3->save();
		$shop_order_id_3 = $shop_order_3->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_3, true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$orders = wc_get_orders(
			[
				'orderby' => [
					'date_created' => 'ASC',
					'id'           => 'DESC',
				],
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 3, $orders );
		$this->assertEquals( $shop_order_id_2, $orders[0]->get_id() );
		$this->assertEquals( $shop_order_id_1, $orders[1]->get_id() );
		$this->assertEquals( $shop_order_id_3, $orders[2]->get_id() );
	}

	/**
	 * Test HPOS order by parent and type.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_order_by_parent_and_type() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$shop_order_1 = new \WC_Order();
		$shop_order_1->save();
		$shop_order_id_1 = $shop_order_1->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_1, true );

		$shop_order_2 = new \WC_Order();
		$shop_order_2->save();
		$shop_order_id_2 = $shop_order_2->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_2, true );

		$refund_1 = new \WC_Order_Refund();
		$refund_1->set_parent_id( $shop_order_id_2 );
		$refund_1->save();
		$refund_id_1 = $refund_1->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $refund_id_1, true );

		$refund_2 = new \WC_Order_Refund();
		$refund_2->set_parent_id( $shop_order_id_1 );
		$refund_2->save();
		$refund_id_2 = $refund_2->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $refund_id_2, true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$orders = wc_get_orders(
			[
				'type'    => 'shop_order_refund',
				'orderby' => 'parent',
				'order'   => 'ASC',
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 2, $orders );
		$this->assertEquals( $refund_id_2, $orders[0]->get_id() );
		$this->assertEquals( $refund_id_1, $orders[1]->get_id() );

		$orders = wc_get_orders(
			[
				'type'    => [ 'shop_order', 'shop_order_refund' ],
				'orderby' => 'type',
				'order'   => 'ASC',
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 4, $orders );
		$this->assertEquals( 'shop_order', $orders[0]->get_type() );
		$this->assertEquals( 'shop_order_refund', $orders[3]->get_type() );
	}

	/**
	 * Test HPOS created via query.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_created_via_query() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$shop_order_1 = new \WC_Order();
		$shop_order_1->set_created_via( 'web' );
		$shop_order_1->save();
		$shop_order_id_1 = $shop_order_1->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_1, true );

		$shop_order_2 = new \WC_Order();
		$shop_order_2->set_created_via( 'api' );
		$shop_order_2->save();
		$shop_order_id_2 = $shop_order_2->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_2, true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$orders = wc_get_orders(
			[
				'created_via' => 'web',
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 1, $orders );
		$this->assertEquals( $shop_order_id_1, $orders[0]->get_id() );

		$orders = wc_get_orders(
			[
				'created_via' => 'api',
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 1, $orders );
		$this->assertEquals( $shop_order_id_2, $orders[0]->get_id() );

		$orders = wc_get_orders(
			[
				'created_via' => [ 'web', 'api' ],
				'orderby'     => 'date_created',
				'order'       => 'ASC',
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 2, $orders );
		$this->assertEquals( $shop_order_id_1, $orders[0]->get_id() );
		$this->assertEquals( $shop_order_id_2, $orders[1]->get_id() );

		// Test with empty created_via
		$orders = wc_get_orders(
			[
				'created_via' => '',
				'orderby'     => 'date_created',
				'order'       => 'ASC',
			]
		);
		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 2, $orders );
	}

	/**
	 * Test HPOS customer email query.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_customer_email_query() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$shop_order_1 = new \WC_Order();
		$shop_order_1->set_billing_email( 'test@example.com' );
		$shop_order_1->save();
		$shop_order_id_1 = $shop_order_1->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_1, true );

		$shop_order_2 = new \WC_Order();
		$shop_order_2->set_billing_email( 'other@example.com' );
		$shop_order_2->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_2->get_id(), true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$orders = wc_get_orders(
			[
				'billing_email' => 'test@example.com',
			]
		);
		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 1, $orders );
		$this->assertEquals( $shop_order_id_1, $orders[0]->get_id() );
	}

	/**
	 * Test HPOS customer ID query.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_customer_id_query() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$shop_order_1 = new \WC_Order();
		$shop_order_1->set_customer_id( 1 );
		$shop_order_1->save();
		$shop_order_id_1 = $shop_order_1->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_1, true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$orders = wc_get_orders(
			[
				'customer_id' => 1,
			]
		);
		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 1, $orders );
		$this->assertEquals( $shop_order_id_1, $orders[0]->get_id() );
	}

	/**
	 * Test HPOS customer query.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_customer_query() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$shop_order_1 = new \WC_Order();
		$shop_order_1->set_billing_email( 'test1@example.com' );
		$shop_order_1->set_customer_id( 1 );
		$shop_order_1->save();
		$shop_order_id_1 = $shop_order_1->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_1, true );

		$shop_order_2 = new \WC_Order();
		$shop_order_2->set_billing_email( 'test2@example.com' );
		$shop_order_2->set_customer_id( 2 );
		$shop_order_2->save();
		$shop_order_id_2 = $shop_order_2->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_2, true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		// Search by customer ID and email
		$orders = wc_get_orders(
			[
				'customer' => [
					[ 1, 'test1@example.com' ],
				],
			]
		);
		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 1, $orders );
		$this->assertEquals( $shop_order_id_1, $orders[0]->get_id() );

		// Search by invalid combination.
		$orders = wc_get_orders(
			[
				'customer' => [
					[ 1, 'test2@example.com' ],
				],
			]
		);
		$this->assertCount( 0, $orders );

		// Search by only customer ID.
		$orders = wc_get_orders(
			[
				'customer' => [
					[ 1 ],
				],
			]
		);
		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 1, $orders );
		$this->assertEquals( $shop_order_id_1, $orders[0]->get_id() );

		// Search by only emails and OR relation.
		$orders = wc_get_orders(
			[
				'customer' => [ 'test1@example.com', 'test2@example.com' ],
				'orderby'  => 'ID',
				'order'    => 'ASC',
			]
		);
		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 2, $orders );
		$this->assertEquals( $shop_order_id_1, $orders[0]->get_id() );
		$this->assertEquals( $shop_order_id_2, $orders[1]->get_id() );

		// Like WooCommerce, invalid values, like null, match no orders.
		$orders = wc_get_orders(
			[
				'customer' => [
					[ null, 'test1@example.com' ],
					[ null, 'test2@example.com' ],
				],
			]
		);
		$this->assertCount( 0, $orders );
	}

	/**
	 * Test HPOS date query.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_date_query() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$shop_order_1 = new \WC_Order();
		$shop_order_1->set_date_created( new \WC_DateTime( '2026-01-01 12:00:00' ) );
		$shop_order_1->set_date_modified( new \WC_DateTime( '2026-01-03 12:00:00' ) );
		$shop_order_1->save();
		$shop_order_id_1 = $shop_order_1->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_1, true );

		$shop_order_2 = new \WC_Order();
		$shop_order_2->set_date_created( new \WC_DateTime( '2026-01-02 12:00:00' ) );
		$shop_order_2->set_date_modified( new \WC_DateTime( '2026-01-04 12:00:00' ) );
		$shop_order_2->save();
		$shop_order_id_2 = $shop_order_2->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_2, true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$orders = wc_get_orders(
			[
				'date_created' => '2026-01-01 12:00:00...2026-01-02 12:00:00',
			]
		);
		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 2, $orders );
		$this->assertEquals( $shop_order_id_2, $orders[0]->get_id() );
		$this->assertEquals( $shop_order_id_1, $orders[1]->get_id() );

		$orders = wc_get_orders( [ 'date_created' => '>2026-01-01 23:59:59' ] );
		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 1, $orders );
		$this->assertEquals( $shop_order_id_2, $orders[0]->get_id() );

		$orders = wc_get_orders( [ 'date_modified' => '<=2026-01-03' ] );
		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 1, $orders );
		$this->assertEquals( $shop_order_id_1, $orders[0]->get_id() );
	}

	/**
	 * Test HPOS order ID inclusion and exclusion queries.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_order_id_queries() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$shop_order_1 = new \WC_Order();
		$shop_order_1->save();
		$shop_order_id_1 = $shop_order_1->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_1, true );

		$shop_order_2 = new \WC_Order();
		$shop_order_2->save();
		$shop_order_id_2 = $shop_order_2->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_2, true );

		$shop_order_3 = new \WC_Order();
		$shop_order_3->save();
		$shop_order_id_3 = $shop_order_3->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_3, true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$orders = wc_get_orders( [ 'id' => [ $shop_order_id_2 ] ] );

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 1, $orders );
		$this->assertEquals( $shop_order_id_2, $orders[0]->get_id() );

		$orders = wc_get_orders(
			[
				'exclude' => [ $shop_order_id_1, $shop_order_id_3 ],
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 1, $orders );
		$this->assertEquals( $shop_order_id_2, $orders[0]->get_id() );
	}

	/**
	 * Test HPOS parent inclusion and exclusion queries.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_parent_queries() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$shop_order_1 = new \WC_Order();
		$shop_order_1->save();
		$shop_order_id_1 = $shop_order_1->get_id();

		$shop_order_2 = new \WC_Order();
		$shop_order_2->save();
		$shop_order_id_2 = $shop_order_2->get_id();

		$refund_1 = new \WC_Order_Refund();
		$refund_1->set_parent_id( $shop_order_id_1 );
		$refund_1->save();
		$refund_id_1 = $refund_1->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $refund_id_1, true );

		$refund_2 = new \WC_Order_Refund();
		$refund_2->set_parent_id( $shop_order_id_2 );
		$refund_2->save();
		$refund_id_2 = $refund_2->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $refund_id_2, true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$orders = wc_get_orders(
			[
				'type'   => 'shop_order_refund',
				'parent' => $shop_order_id_1,
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 1, $orders );
		$this->assertEquals( $refund_id_1, $orders[0]->get_id() );

		$orders = wc_get_orders(
			[
				'type'           => 'shop_order_refund',
				'parent_exclude' => [ $shop_order_id_1 ],
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 1, $orders );
		$this->assertEquals( $refund_id_2, $orders[0]->get_id() );
	}

	/**
	 * Test HPOS order offset.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_order_offset() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$order_ids = [];
		foreach ( [ '2026-01-01', '2026-01-02', '2026-01-03' ] as $date_created ) {
			$order = new \WC_Order();
			$order->set_date_created( new \WC_DateTime( $date_created ) );
			$order->save();
			$order_ids[] = $order->get_id();
			ElasticPress\Indexables::factory()->get( 'post' )->index( $order->get_id(), true );
		}

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$orders = wc_get_orders(
			[
				'limit'   => 1,
				'offset'  => 1,
				'orderby' => 'date',
				'order'   => 'ASC',
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 1, $orders );
		$this->assertEquals( $order_ids[1], $orders[0]->get_id() );
	}

	/**
	 * Test HPOS paid and completed date queries.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_paid_and_completed_date_queries() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$shop_order_1 = new \WC_Order();
		$shop_order_1->set_date_paid( new \WC_DateTime( '2026-01-02 12:00:00' ) );
		$shop_order_1->set_date_completed( new \WC_DateTime( '2026-01-03 12:00:00' ) );
		$shop_order_1->save();
		$shop_order_id_1 = $shop_order_1->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_1, true );

		$shop_order_2 = new \WC_Order();
		$shop_order_2->set_date_paid( new \WC_DateTime( '2026-02-02 12:00:00' ) );
		$shop_order_2->set_date_completed( new \WC_DateTime( '2026-02-03 12:00:00' ) );
		$shop_order_2->save();
		$shop_order_id_2 = $shop_order_2->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_2, true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$orders = wc_get_orders(
			[
				'date_paid' => '2026-01-01...2026-01-31',
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 1, $orders );
		$this->assertEquals( $shop_order_id_1, $orders[0]->get_id() );

		$orders = wc_get_orders(
			[
				'date_completed' => '2026-02-01...2026-02-28',
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 1, $orders );
		$this->assertEquals( $shop_order_id_2, $orders[0]->get_id() );

		$orders = wc_get_orders(
			[
				'date_paid' => '2026-01-02',
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 1, $orders );
		$this->assertEquals( $shop_order_id_1, $orders[0]->get_id() );

		$orders = wc_get_orders(
			[
				'date_completed' => '>2026-01-31',
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 1, $orders );
		$this->assertEquals( $shop_order_id_2, $orders[0]->get_id() );
	}

	/**
	 * Test HPOS advanced date query.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_advanced_date_query() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$shop_order_1 = new \WC_Order();
		$shop_order_1->set_date_created( new \WC_DateTime( '2026-01-01 12:00:00' ) );
		$shop_order_1->set_date_paid( new \WC_DateTime( '2026-01-02 12:00:00' ) );
		$shop_order_1->set_date_completed( new \WC_DateTime( '2026-01-03 12:00:00' ) );
		$shop_order_1->save();
		$shop_order_id_1 = $shop_order_1->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_1, true );

		$shop_order_2 = new \WC_Order();
		$shop_order_2->set_date_created( new \WC_DateTime( '2026-02-01 12:00:00' ) );
		$shop_order_2->set_date_paid( new \WC_DateTime( '2026-02-02 12:00:00' ) );
		$shop_order_2->set_date_completed( new \WC_DateTime( '2026-02-03 12:00:00' ) );
		$shop_order_2->save();
		$shop_order_id_2 = $shop_order_2->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_2, true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$orders = wc_get_orders(
			[
				'date_query' => [
					[
						'column'    => 'date_created',
						'after'     => '2026-01-01 00:00:00',
						'before'    => '2026-01-31 23:59:59',
						'inclusive' => true,
					],
				],
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 1, $orders );
		$this->assertEquals( $shop_order_id_1, $orders[0]->get_id() );

		$orders = wc_get_orders(
			[
				'date_query' => [
					[
						'after'     => '2026-01-01 00:00:00',
						'before'    => '2026-01-31 23:59:59',
						'inclusive' => true,
					],
				],
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 1, $orders );
		$this->assertEquals( $shop_order_id_1, $orders[0]->get_id() );

		$orders = wc_get_orders(
			[
				'date_query' => [
					[
						'column'    => 'date_paid',
						'after'     => '2026-02-01',
						'before'    => '2026-02-28',
						'inclusive' => true,
					],
				],
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 1, $orders );
		$this->assertEquals( $shop_order_id_2, $orders[0]->get_id() );

		$orders = wc_get_orders(
			[
				'date_query' => [
					[
						'column'    => 'date_completed',
						'after'     => '2026-01-03',
						'before'    => '2026-01-03',
						'inclusive' => true,
					],
				],
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 1, $orders );
		$this->assertEquals( $shop_order_id_1, $orders[0]->get_id() );
	}

	/**
	 * Test HPOS currency, version, and prices_include_tax queries.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_currency_version_and_prices_include_tax_queries() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$shop_order_1 = new \WC_Order();
		$shop_order_1->set_currency( 'USD' );
		$shop_order_1->set_prices_include_tax( true );
		$shop_order_1->save();
		$shop_order_id_1 = $shop_order_1->get_id();

		$shop_order_2 = new \WC_Order();
		$shop_order_2->set_currency( 'EUR' );
		$shop_order_2->set_prices_include_tax( false );
		$shop_order_2->save();
		$shop_order_id_2 = $shop_order_2->get_id();

		// WooCommerce overwrites version on save, so force distinct versions in the index.
		$force_versions = function ( $meta, $post ) use ( $shop_order_id_1, $shop_order_id_2 ) {
			if ( (int) $post->ID === (int) $shop_order_id_1 ) {
				$meta['_order_version'] = [ '9.8.0' ];
			}
			if ( (int) $post->ID === (int) $shop_order_id_2 ) {
				$meta['_order_version'] = [ '10.0.0' ];
			}
			return $meta;
		};
		add_filter( 'ep_prepared_post_meta', $force_versions, 20, 2 );

		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_1, true );
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_2, true );
		remove_filter( 'ep_prepared_post_meta', $force_versions, 20 );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$orders = wc_get_orders( [ 'currency' => 'USD' ] );
		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 1, $orders );
		$this->assertEquals( $shop_order_id_1, $orders[0]->get_id() );

		$orders = wc_get_orders( [ 'version' => '10.0.0' ] );
		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 1, $orders );
		$this->assertEquals( $shop_order_id_2, $orders[0]->get_id() );

		$orders = wc_get_orders( [ 'prices_include_tax' => 'yes' ] );
		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 1, $orders );
		$this->assertEquals( $shop_order_id_1, $orders[0]->get_id() );
	}

	/**
	 * Test HPOS customer note, IP address, and user agent queries.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_customer_note_ip_and_user_agent_queries() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$shop_order_1 = new \WC_Order();
		$shop_order_1->set_customer_note( 'Leave at door' );
		$shop_order_1->set_customer_ip_address( '203.0.113.10' );
		$shop_order_1->set_customer_user_agent( 'Mozilla/5.0 EP-Test' );
		$shop_order_1->save();
		$shop_order_id_1 = $shop_order_1->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_1, true );

		$shop_order_2 = new \WC_Order();
		$shop_order_2->set_customer_note( '' );
		$shop_order_2->set_customer_ip_address( '198.51.100.20' );
		$shop_order_2->set_customer_user_agent( 'curl/8.0' );
		$shop_order_2->save();
		$shop_order_id_2 = $shop_order_2->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_2, true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$orders = wc_get_orders( [ 'customer_note' => 'Leave at door' ] );
		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 1, $orders );
		$this->assertEquals( $shop_order_id_1, $orders[0]->get_id() );

		$orders = wc_get_orders( [ 'customer_ip_address' => '198.51.100.20' ] );
		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 1, $orders );
		$this->assertEquals( $shop_order_id_2, $orders[0]->get_id() );

		$orders = wc_get_orders( [ 'customer_user_agent' => 'Mozilla/5.0 EP-Test' ] );
		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 1, $orders );
		$this->assertEquals( $shop_order_id_1, $orders[0]->get_id() );

		$orders = wc_get_orders( [ 'customer_note' => '' ] );
		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 1, $orders );
		$this->assertEquals( $shop_order_id_2, $orders[0]->get_id() );
	}

	/**
	 * Test HPOS tax amount queries.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_tax_amount_queries() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$shop_order_1 = new \WC_Order();
		$shop_order_1->set_discount_tax( 1.25 );
		$shop_order_1->set_shipping_tax( 2.50 );
		$shop_order_1->set_cart_tax( 3.75 );
		$shop_order_1->save();
		$shop_order_id_1 = $shop_order_1->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_1, true );

		$shop_order_2 = new \WC_Order();
		$shop_order_2->set_discount_tax( 5.00 );
		$shop_order_2->set_shipping_tax( 6.00 );
		$shop_order_2->set_cart_tax( 7.00 );
		$shop_order_2->save();
		$shop_order_id_2 = $shop_order_2->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_2, true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$orders = wc_get_orders( [ 'discount_tax' => 1.25 ] );
		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 1, $orders );
		$this->assertEquals( $shop_order_id_1, $orders[0]->get_id() );

		$orders = wc_get_orders( [ 'shipping_tax' => 6.00 ] );
		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 1, $orders );
		$this->assertEquals( $shop_order_id_2, $orders[0]->get_id() );

		$orders = wc_get_orders( [ 'cart_tax' => 3.75 ] );
		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 1, $orders );
		$this->assertEquals( $shop_order_id_1, $orders[0]->get_id() );
	}

	/**
	 * Test HPOS total queries with comparison operators.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_total_operator_query() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$shop_order_1 = new \WC_Order();
		$shop_order_1->set_total( 25.00 );
		$shop_order_1->save();
		$shop_order_id_1 = $shop_order_1->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_1, true );

		$shop_order_2 = new \WC_Order();
		$shop_order_2->set_total( 75.00 );
		$shop_order_2->save();
		$shop_order_id_2 = $shop_order_2->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_2, true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$orders = wc_get_orders(
			[
				'total' => [
					'value'    => 50,
					'operator' => '>',
				],
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 1, $orders );
		$this->assertEquals( $shop_order_id_2, $orders[0]->get_id() );

		$orders = wc_get_orders(
			[
				'total' => [
					'value'    => [ 20, 30 ],
					'operator' => 'BETWEEN',
				],
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 1, $orders );
		$this->assertEquals( $shop_order_id_1, $orders[0]->get_id() );

		$orders = wc_get_orders(
			[
				'orderby' => 'total',
				'order'   => 'ASC',
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 2, $orders );
		$this->assertEquals( $shop_order_id_1, $orders[0]->get_id() );
		$this->assertEquals( $shop_order_id_2, $orders[1]->get_id() );
	}

	/**
	 * Test HPOS search query.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_search_query() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$shop_order_1 = new \WC_Order();
		$shop_order_1->set_billing_first_name( 'John' );
		$shop_order_1->set_billing_last_name( 'Doe' );
		$shop_order_1->save();
		$shop_order_id_1 = $shop_order_1->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_1, true );

		$shop_order_2 = new \WC_Order();
		$shop_order_2->set_billing_first_name( 'Example' );
		$shop_order_2->set_billing_last_name( 'Customer' );
		$shop_order_2->save();
		$shop_order_id_2 = $shop_order_2->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_2, true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$orders = wc_get_orders(
			[
				's' => 'john',
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 1, $orders );
		$this->assertEquals( $shop_order_id_1, $orders[0]->get_id() );
	}

	/**
	 * Test HPOS search query with transaction ID.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_search_query_with_transaction_id() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$shop_order_1 = new \WC_Order();
		$shop_order_1->set_billing_first_name( 'John' );
		$shop_order_1->set_billing_last_name( 'Doe' );
		$shop_order_1->save();
		$shop_order_id_1 = $shop_order_1->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_1, true );

		$shop_order_2 = new \WC_Order();
		$shop_order_2->set_billing_first_name( 'Example' );
		$shop_order_2->set_billing_last_name( 'Customer' );
		$shop_order_2->set_transaction_id( '1234567890' );
		$shop_order_2->save();
		$shop_order_id_2 = $shop_order_2->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_2, true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$orders = wc_get_orders(
			[
				's'             => 1234567890,
				'search_filter' => 'transaction_id',
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 1, $orders );
		$this->assertEquals( $shop_order_id_2, $orders[0]->get_id() );
	}

	/**
	 * Test HPOS search query with order customer email.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_search_query_with_order_customer_email() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$shop_order_1 = new \WC_Order();
		$shop_order_1->set_billing_email( 'test1@example.com' );
		$shop_order_1->save();
		$shop_order_id_1 = $shop_order_1->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_1, true );

		$shop_order_2 = new \WC_Order();
		$shop_order_2->set_billing_email( 'dev@elasticpress.io' );
		$shop_order_2->save();
		$shop_order_id_2 = $shop_order_2->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_2, true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$orders = wc_get_orders(
			[
				's'             => 'test1@example.com',
				'search_filter' => 'customer_email',
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 1, $orders );
		$this->assertEquals( $shop_order_id_1, $orders[0]->get_id() );
	}

	/**
	 * Test HPOS search query with order customers.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_search_query_with_order_customers() {
		$this->enable_hpos();
		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$shop_order_1 = new \WC_Order();
		$shop_order_1->set_billing_email( 'test1@example.com' );
		$shop_order_1->save();
		$shop_order_id_1 = $shop_order_1->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_1, true );

		$shop_order_2 = new \WC_Order();
		$shop_order_2->set_billing_email( 'dev@elasticpress.io' );
		$shop_order_2->save();
		$shop_order_id_2 = $shop_order_2->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_2, true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$orders = wc_get_orders(
			[
				's'             => 'test1@example.com',
				'search_filter' => 'customers',
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 1, $orders );
		$this->assertEquals( $shop_order_id_1, $orders[0]->get_id() );
	}

	/**
	 * Test HPOS meta_query for color EXISTS and size LIKE.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_meta_query_color_and_size_like() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$matching = new \WC_Order();
		$matching->update_meta_data( 'color', 'blue' );
		$matching->update_meta_data( 'size', 'extra-small' );
		$matching->save();
		$matching_id = $matching->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $matching_id, true );

		$color_only = new \WC_Order();
		$color_only->update_meta_data( 'color', 'red' );
		$color_only->update_meta_data( 'size', 'large' );
		$color_only->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $color_only->get_id(), true );

		$size_only = new \WC_Order();
		$size_only->update_meta_data( 'size', 'small' );
		$size_only->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $size_only->get_id(), true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$orders = wc_get_orders(
			[
				'meta_query' => [
					[
						'key' => 'color',
					],
					[
						'key'     => 'size',
						'value'   => 'small',
						'compare' => 'LIKE',
					],
				],
			]
		);

		$this->assertNotEmpty( $orders );
		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 1, $orders );
		$this->assertEquals( $matching_id, $orders[0]->get_id() );
	}

	/**
	 * Test HPOS top-level billing_first_name and order_key filters.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_billing_first_name_and_order_key() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$matching = new \WC_Order();
		$matching->set_billing_first_name( 'Lauren' );
		$matching->set_order_key( 'my_order_key' );
		$matching->save();
		$matching_id = $matching->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $matching_id, true );

		$other = new \WC_Order();
		$other->set_billing_first_name( 'Lauren' );
		$other->set_order_key( 'other_key' );
		$other->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $other->get_id(), true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$orders = wc_get_orders(
			[
				'billing_first_name' => 'Lauren',
				'order_key'          => 'my_order_key',
			]
		);

		$this->assertNotEmpty( $orders );
		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 1, $orders );
		$this->assertEquals( $matching_id, $orders[0]->get_id() );
	}

	/**
	 * Test HPOS field_query for billing_first_name and order_key.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_field_query_billing_first_name_and_order_key() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$matching = new \WC_Order();
		$matching->set_billing_first_name( 'Lauren' );
		$matching->set_order_key( 'my_order_key' );
		$matching->save();
		$matching_id = $matching->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $matching_id, true );

		$other = new \WC_Order();
		$other->set_billing_first_name( 'John' );
		$other->set_order_key( 'my_order_key' );
		$other->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $other->get_id(), true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$orders = wc_get_orders(
			[
				'field_query' => [
					[
						'field' => 'billing_first_name',
						'value' => 'Lauren',
					],
					[
						'field' => 'order_key',
						'value' => 'my_order_key',
					],
				],
			]
		);

		$this->assertNotEmpty( $orders );
		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 1, $orders );
		$this->assertEquals( $matching_id, $orders[0]->get_id() );
	}

	/**
	 * Test HPOS field_query with OR on total or shipping_total less than 5.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_field_query_total_or_shipping_total_less_than() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$low_total = new \WC_Order();
		$low_total->set_total( 3.50 );
		$low_total->set_shipping_total( 10.00 );
		$low_total->save();
		$low_total_id = $low_total->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $low_total_id, true );

		$low_shipping = new \WC_Order();
		$low_shipping->set_total( 20.00 );
		$low_shipping->set_shipping_total( 2.00 );
		$low_shipping->save();
		$low_shipping_id = $low_shipping->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $low_shipping_id, true );

		$high_both = new \WC_Order();
		$high_both->set_total( 20.00 );
		$high_both->set_shipping_total( 10.00 );
		$high_both->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $high_both->get_id(), true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$orders = wc_get_orders(
			[
				'field_query' => [
					'relation' => 'OR',
					[
						'field'   => 'total',
						'value'   => '5.0',
						'compare' => '<',
					],
					[
						'field'   => 'shipping_total',
						'value'   => '5.0',
						'compare' => '<',
					],
				],
			]
		);

		$this->assertNotEmpty( $orders );
		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 2, $orders );

		$ids = array_map(
			function ( $order ) {
				return $order->get_id();
			},
			$orders
		);
		$this->assertContains( $low_total_id, $ids );
		$this->assertContains( $low_shipping_id, $ids );
	}

	/**
	 * Test HPOS nested field_query with LIKE and numeric comparisons.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_field_query_billing_name_like_with_total_and_discount() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$order_1 = new \WC_Order();
		$order_1->set_billing_first_name( 'Lauren' );
		$order_1->set_total( 8.00 );
		$order_1->set_discount_total( 5.00 );
		$order_1->save();
		$order_1_id = $order_1->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $order_1_id, true );

		$order_2 = new \WC_Order();
		$order_2->set_billing_first_name( 'Lauren' );
		$order_2->set_total( 15.00 );
		$order_2->set_discount_total( 5.00 );
		$order_2->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $order_2->get_id(), true );

		$order_3 = new \WC_Order();
		$order_3->set_billing_first_name( 'Lauren' );
		$order_3->set_total( 9.50 );
		$order_3->set_discount_total( 6.00 );
		$order_3->save();
		$order_3_id = $order_3->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $order_3_id, true );

		$order_4 = new \WC_Order();
		$order_4->set_billing_first_name( 'John' );
		$order_4->set_total( 8.00 );
		$order_4->set_discount_total( 5.00 );
		$order_4->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $order_4->get_id(), true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$orders = wc_get_orders(
			[
				'field_query' => [
					[
						'field' => 'billing_first_name',
						'value' => 'Lauren',
					],
					[
						'relation' => 'AND',
						[
							'field'   => 'total',
							'value'   => '10.0',
							'compare' => '<',
							'type'    => 'NUMERIC',
						],
						[
							'field'   => 'discount_total',
							'value'   => '5.0',
							'compare' => '>=',
							'type'    => 'NUMERIC',
						],
					],
				],
				'orderby'     => 'ID',
				'order'       => 'ASC',
			]
		);

		$this->assertNotEmpty( $orders );
		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );

		$this->assertCount( 1, $orders );
		$this->assertEquals( $order_1_id, $orders[0]->get_id() );
	}

	/**
	 * Test HPOS queries with unsupported orderby do not use Elasticsearch.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_query_with_unsupported_orderby_does_not_use_ep() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$shop_order = new \WC_Order();
		$shop_order->save();
		$shop_order_id = $shop_order->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id, true );
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$unsupported_orderby = [
			'post__in',
			'post__in date',
			[
				'post__in' => 'DESC',
				'date'     => 'ASC',
			],
			[ 'post__in', 'date' ],
			'post_name__in',
			'post_parent__in',
		];

		foreach ( $unsupported_orderby as $orderby ) {
			$this->orders->clear_elasticsearch_success_order_ids();

			$orders = wc_get_orders(
				[
					'id'      => [ $shop_order_id ],
					'orderby' => $orderby,
				]
			);

			$this->assertCount( 1, $orders );
			$this->assertEquals( $shop_order_id, $orders[0]->get_id() );
			$this->assertFalse( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		}

		// Supported orderby still uses Elasticsearch.
		$this->orders->clear_elasticsearch_success_order_ids();

		$orders = wc_get_orders(
			[
				'id'      => [ $shop_order_id ],
				'orderby' => 'parent',
			]
		);

		$this->assertCount( 1, $orders );
		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
	}

	/**
	 * Test HPOS queries fall back to HPOS when Elasticsearch is skipped.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_query_falls_back_when_elasticsearch_is_skipped() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$shop_order = new \WC_Order();
		$shop_order->save();
		$shop_order_id = $shop_order->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id, true );
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		add_filter( 'ep_skip_query_integration', '__return_true' );

		$orders = wc_get_orders( [ 'id' => $shop_order_id ] );

		remove_filter( 'ep_skip_query_integration', '__return_true' );

		$this->assertCount( 1, $orders );
		$this->assertEquals( $shop_order_id, $orders[0]->get_id() );
		$this->assertFalse( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
	}

	/**
	 * Test HPOS query integration is skipped during a full sync.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_query_integration_skips_during_full_sync() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$shop_order = new \WC_Order();
		$shop_order->save();
		$shop_order_id = $shop_order->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id, true );
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		add_filter( 'ep_is_full_reindexing_post', '__return_true' );

		$orders = wc_get_orders( [] );

		$this->assertCount( 1, $orders );
		$this->assertEquals( $shop_order_id, $orders[0]->get_id() );
		$this->assertFalse( $this->orders->order_has_elasticsearch_success( $orders[0] ) );

		$this->orders->clear_elasticsearch_success_order_ids();

		add_filter( 'ep_enable_query_integration_during_indexing', '__return_true' );

		$orders = wc_get_orders( [] );

		remove_filter( 'ep_enable_query_integration_during_indexing', '__return_true' );
		remove_filter( 'ep_is_full_reindexing_post', '__return_true' );

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
	}

	/**
	 * Test the HPOS skip query integration filter.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_skip_query_integration_filter() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$shop_order = new \WC_Order();
		$shop_order->save();
		$shop_order_id = $shop_order->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id, true );
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$filtered_query = null;
		$skip           = function ( $skip, $query ) use ( &$filtered_query ) {
			$filtered_query = $query;
			return true;
		};
		add_filter( 'ep_woocommerce_skip_hpos_query_integration', $skip, 10, 2 );

		$orders = wc_get_orders( [] );

		remove_filter( 'ep_woocommerce_skip_hpos_query_integration', $skip );

		$this->assertInstanceOf( \Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableQuery::class, $filtered_query );
		$this->assertCount( 1, $orders );
		$this->assertFalse( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
	}

	/**
	 * Test HPOS date shorthand matches WooCommerce for `>` dates and timestamps.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_date_shorthand_matches_woocommerce() {
		$this->enable_hpos();

		update_option( 'timezone_string', 'America/New_York' );

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$shop_order_1 = new \WC_Order();
		$shop_order_1->set_date_created( '2026-01-01 22:00:00' );
		$shop_order_1->save();
		$shop_order_id_1 = $shop_order_1->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_1, true );

		$shop_order_2 = new \WC_Order();
		$shop_order_2->set_date_created( '2026-01-02 12:00:00' );
		$shop_order_2->save();
		$shop_order_id_2 = $shop_order_2->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_2, true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$timestamp_2 = $shop_order_2->get_date_created()->getTimestamp();

		$queries = [
			'>2026-01-01'      => [ $shop_order_id_2 ],
			'>=2026-01-01'     => [ $shop_order_id_2, $shop_order_id_1 ],
			'<2026-01-02'      => [ $shop_order_id_1 ],
			'<=2026-01-02'     => [ $shop_order_id_2, $shop_order_id_1 ],
			'2026-01-01'       => [ $shop_order_id_1 ],
			"<{$timestamp_2}"  => [ $shop_order_id_1 ],
			">={$timestamp_2}" => [ $shop_order_id_2 ],
		];

		foreach ( $queries as $date_created => $expected ) {
			$args = [
				'date_created' => $date_created,
				'return'       => 'ids',
			];

			$this->assertSame( $expected, array_map( 'absint', wc_get_orders( $args ) ), "ES: {$date_created}" );
			$this->assertSame( $expected, array_map( 'absint', wc_get_orders( $args + [ 'ep_integrate' => false ] ) ), "HPOS: {$date_created}" );
		}
	}

	/**
	 * Test deleted HPOS refunds are removed from the index.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_deleted_refunds_are_removed_from_index() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$post_indexable = ElasticPress\Indexables::factory()->get( 'post' );

		$shop_order = new \WC_Order();
		$shop_order->save();
		$shop_order_id = $shop_order->get_id();

		$refund_1 = new \WC_Order_Refund();
		$refund_1->set_parent_id( $shop_order_id );
		$refund_1->save();
		$refund_id_1 = $refund_1->get_id();

		$refund_2 = new \WC_Order_Refund();
		$refund_2->set_parent_id( $shop_order_id );
		$refund_2->save();
		$refund_id_2 = $refund_2->get_id();

		$post_indexable->index( $shop_order_id, true );
		$post_indexable->index( $refund_id_1, true );
		$post_indexable->index( $refund_id_2, true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		// Delete a only one refund.
		$refund_1->delete( true );
		do_action( 'woocommerce_refund_deleted', $refund_id_1, $shop_order_id );
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$this->assertEmpty( $post_indexable->get( $refund_id_1 ) );
		$this->assertNotEmpty( $post_indexable->get( $refund_id_2 ) );

		// Deleting an order deletes its refunds and the order itself.
		wc_get_order( $shop_order_id )->delete( true );
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$this->assertEmpty( $post_indexable->get( $refund_id_2 ) );
		$this->assertEmpty( $post_indexable->get( $shop_order_id ) );
	}

	/**
	 * Test HPOS GMT date shorthand parameters match WooCommerce.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_gmt_date_shorthand_parameters() {
		$this->enable_hpos();

		update_option( 'timezone_string', 'America/New_York' );

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		// 2026-01-01 22:00:00 in New York is 2026-01-02 03:00:00 GMT.
		$shop_order_1 = new \WC_Order();
		$shop_order_1->set_date_created( '2026-01-01 22:00:00' );
		$shop_order_1->set_date_paid( '2026-01-01 22:00:00' );
		$shop_order_1->set_date_completed( '2026-01-01 22:00:00' );
		$shop_order_1->save();
		$shop_order_id_1 = $shop_order_1->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_1, true );

		// 2026-01-02 22:00:00 in New York is 2026-01-03 03:00:00 GMT.
		$shop_order_2 = new \WC_Order();
		$shop_order_2->set_date_created( '2026-01-02 22:00:00' );
		$shop_order_2->set_date_paid( '2026-01-02 22:00:00' );
		$shop_order_2->set_date_completed( '2026-01-02 22:00:00' );
		$shop_order_2->save();
		$shop_order_id_2 = $shop_order_2->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_2, true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$orders = wc_get_orders( [ 'date_created_gmt' => '2026-01-02' ] );
		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 1, $orders );
		$this->assertEquals( $shop_order_id_1, $orders[0]->get_id() );

		$queries = [
			[ 'date_created_gmt' => '2026-01-02' ],
			[ 'date_created_gmt' => '<2026-01-03' ],
			[ 'date_created_gmt' => '2026-01-01...2026-01-02' ],
			[ 'date_created' => '2026-01-01' ],
			[ 'date_modified_gmt' => '>2026-01-01' ],
			[ 'date_paid_gmt' => '2026-01-02' ],
			[ 'date_paid_gmt' => '<=2026-01-02' ],
			[ 'date_paid' => '2026-01-01' ],
			[ 'date_completed_gmt' => '2026-01-02' ],
		];

		foreach ( $queries as $query ) {
			$args     = $query + [ 'return' => 'ids' ];
			$expected = isset( $query['date_modified_gmt'] ) ? [ $shop_order_id_2, $shop_order_id_1 ] : [ $shop_order_id_1 ];

			$this->assertSame( $expected, array_map( 'absint', wc_get_orders( $args ) ), 'ES: ' . wp_json_encode( $query ) );
			$this->assertSame( $expected, array_map( 'absint', wc_get_orders( $args + [ 'ep_integrate' => false ] ) ), 'HPOS: ' . wp_json_encode( $query ) );
		}
	}

	/**
	 * Test HPOS date_query exclusive `after` values match WooCommerce.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_date_query_exclusive_after() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$shop_order_1 = new \WC_Order();
		$shop_order_1->set_date_created( '2026-01-01 12:00:00' );
		$shop_order_1->save();
		$shop_order_id_1 = $shop_order_1->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_1, true );

		$shop_order_2 = new \WC_Order();
		$shop_order_2->set_date_created( '2026-01-02 12:00:00' );
		$shop_order_2->save();
		$shop_order_id_2 = $shop_order_2->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_2, true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$orders = wc_get_orders(
			[
				'date_query' => [
					[
						'column' => 'date_created_gmt',
						'after'  => [
							'year'  => 2026,
							'month' => 1,
							'day'   => 1,
						],
					],
				],
			]
		);
		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 1, $orders );
		$this->assertEquals( $shop_order_id_2, $orders[0]->get_id() );

		$queries = [
			// Exclusive `after` starts at the end of the day.
			[
				[
					'column' => 'date_created_gmt',
					'after'  => '2026-01-01',
				],
			],
			// WooCommerce uses the GMT created date when no column is set.
			[
				[
					'after' => '2026-01-01',
				],
			],
			// Exclusive `after` for a month starts at the end of the month.
			[
				[
					'column' => 'date_created_gmt',
					'after'  => [
						'year'  => 2025,
						'month' => 12,
					],
				],
			],
		];

		foreach ( $queries as $date_query ) {
			$args = [
				'date_query' => $date_query,
				'return'     => 'ids',
			];

			$expected = isset( $date_query[0]['after']['month'] ) ? [ $shop_order_id_2, $shop_order_id_1 ] : [ $shop_order_id_2 ];

			$this->assertSame( $expected, array_map( 'absint', wc_get_orders( $args ) ), 'ES: ' . wp_json_encode( $date_query ) );
			$this->assertSame( $expected, array_map( 'absint', wc_get_orders( $args + [ 'ep_integrate' => false ] ) ), 'HPOS: ' . wp_json_encode( $date_query ) );
		}

		// Inclusive `after` still includes the whole day.
		$orders = wc_get_orders(
			[
				'date_query' => [
					[
						'column'    => 'date_created_gmt',
						'after'     => '2026-01-01',
						'inclusive' => true,
					],
				],
				'return'     => 'ids',
			]
		);
		$this->assertSame( [ $shop_order_id_2, $shop_order_id_1 ], array_map( 'absint', $orders ) );
	}

	/**
	 * Test HPOS date arguments passed as WC_DateTime objects.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_date_wc_datetime_values() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$shop_order_1 = new \WC_Order();
		$shop_order_1->set_date_created( '2026-01-01 12:00:00' );
		$shop_order_1->set_date_paid( '2026-01-01 13:00:00' );
		$shop_order_1->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_1->get_id(), true );

		$shop_order_2 = new \WC_Order();
		$shop_order_2->set_date_created( '2026-01-02 12:00:00' );
		$shop_order_2->set_date_paid( '2026-01-02 13:00:00' );
		$shop_order_2->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_2->get_id(), true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$date = new \WC_DateTime( '2026-01-02 00:00:00', new \DateTimeZone( 'UTC' ) );

		$orders = wc_get_orders( [ 'date_created' => $date ] );
		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertSame( [ $shop_order_2->get_id() ], array_map( fn( $order ) => $order->get_id(), $orders ) );

		$orders = wc_get_orders( [ 'date_paid' => $date ] );
		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertSame( [ $shop_order_2->get_id() ], array_map( fn( $order ) => $order->get_id(), $orders ) );
	}

	/**
	 * Test HPOS top-level date arguments, like `year`.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_top_level_date_args() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$shop_order_1 = new \WC_Order();
		$shop_order_1->set_date_created( '2025-03-01 12:00:00' );
		$shop_order_1->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_1->get_id(), true );

		$shop_order_2 = new \WC_Order();
		$shop_order_2->set_date_created( '2026-03-01 12:00:00' );
		$shop_order_2->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_2->get_id(), true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$orders = wc_get_orders(
			[
				'year'     => 2026,
				'monthnum' => 3,
			]
		);
		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertSame( [ $shop_order_2->get_id() ], array_map( fn( $order ) => $order->get_id(), $orders ) );
	}

	/**
	 * Test HPOS date_query with an OR relation between created and paid dates.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_date_query_or_relation_across_columns() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$shop_order_1 = new \WC_Order();
		$shop_order_1->set_date_created( '2025-01-01 12:00:00' );
		$shop_order_1->set_date_paid( '2025-01-01 13:00:00' );
		$shop_order_1->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_1->get_id(), true );

		$shop_order_2 = new \WC_Order();
		$shop_order_2->set_date_created( '2025-06-01 12:00:00' );
		$shop_order_2->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_2->get_id(), true );

		$shop_order_3 = new \WC_Order();
		$shop_order_3->set_date_created( '2026-01-01 12:00:00' );
		$shop_order_3->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_3->get_id(), true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$orders = wc_get_orders(
			[
				'date_query' => [
					'relation' => 'OR',
					[
						'column' => 'date_paid_gmt',
						'before' => '2025-02-01',
					],
					[
						'column' => 'date_created_gmt',
						'after'  => '2025-12-31',
					],
				],
				'orderby'    => 'id',
				'order'      => 'ASC',
			]
		);
		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertSame( [ $shop_order_1->get_id(), $shop_order_3->get_id() ], array_map( fn( $order ) => $order->get_id(), $orders ) );
	}

	/**
	 * Test HPOS date queries that cannot be matched exactly fall back to WooCommerce.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_unsupported_date_query_falls_back() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$shop_order_1 = new \WC_Order();
		$shop_order_1->set_date_created( '2025-03-01 12:00:00' );
		$shop_order_1->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_1->get_id(), true );

		$shop_order_2 = new \WC_Order();
		$shop_order_2->set_date_created( '2026-04-01 15:00:00' );
		$shop_order_2->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_2->get_id(), true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$queries = [
			// Week numbers.
			[ 'date_query' => [ [ 'week' => 14 ] ] ],
			// A month in any year.
			[ 'date_query' => [ [ 'month' => 4 ] ] ],
			// Comparisons other than `=`.
			[
				'date_query' => [
					[
						'year'    => 2025,
						'compare' => '>',
					],
				],
			],
			// An hour of any day.
			[ 'hour' => 15 ],
		];

		foreach ( $queries as $args ) {
			$orders = wc_get_orders( $args );
			$this->assertFalse( $this->orders->order_has_elasticsearch_success( $orders[0] ), wp_json_encode( $args ) );
			$this->assertSame( [ $shop_order_2->get_id() ], array_map( fn( $order ) => $order->get_id(), $orders ), wp_json_encode( $args ) );
		}
	}

	/**
	 * Test HPOS `meta_key` and `meta_value` shortcut arguments.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_meta_query_shortcut_args() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$shop_order_1 = new \WC_Order();
		$shop_order_1->update_meta_data( 'color', 'red' );
		$shop_order_1->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_1->get_id(), true );

		$shop_order_2 = new \WC_Order();
		$shop_order_2->update_meta_data( 'color', 'blue' );
		$shop_order_2->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_2->get_id(), true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$orders = wc_get_orders(
			[
				'meta_key'   => 'color', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => 'red', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			]
		);
		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertSame( [ $shop_order_1->get_id() ], array_map( fn( $order ) => $order->get_id(), $orders ) );
	}

	/**
	 * Test HPOS `all` status query returns orders of any status.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_status_all_query() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$shop_order_1 = new \WC_Order();
		$shop_order_1->set_status( 'processing' );
		$shop_order_1->save();
		$shop_order_id_1 = $shop_order_1->get_id();

		$shop_order_2 = new \WC_Order();
		$shop_order_2->set_status( 'completed' );
		$shop_order_2->save();
		$shop_order_id_2 = $shop_order_2->get_id();

		// Trashed orders are only returned by the `all` status.
		$shop_order_3 = new \WC_Order();
		$shop_order_3->save();
		$shop_order_id_3 = $shop_order_3->get_id();
		$shop_order_3->delete();

		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_1, true );
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_2, true );
		ElasticPress\Indexables::factory()->get( 'post' )->index( $shop_order_id_3, true );
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = [
			'status'  => 'all',
			'orderby' => 'id',
			'order'   => 'ASC',
		];

		$orders = wc_get_orders( $args );

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertSame( [ $shop_order_id_1, $shop_order_id_2, $shop_order_id_3 ], array_map( fn( $order ) => $order->get_id(), $orders ) );

		$this->assertSame(
			[ $shop_order_id_1, $shop_order_id_2, $shop_order_id_3 ],
			array_map(
				'absint',
				wc_get_orders(
					$args + [
						'return'       => 'ids',
						'ep_integrate' => false,
					]
				)
			)
		);
	}

	/**
	 * Test HPOS guest customer query.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_guest_customer_query() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$guest_order = new \WC_Order();
		$guest_order->set_customer_id( 0 );
		$guest_order->save();
		$guest_order_id = $guest_order->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $guest_order_id, true );

		$customer_order = new \WC_Order();
		$customer_order->set_customer_id( 1 );
		$customer_order->save();
		$customer_order_id = $customer_order->get_id();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $customer_order_id, true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		foreach ( [ 0, '0', [ 0 ] ] as $customer ) {
			$this->orders->clear_elasticsearch_success_order_ids();

			$orders = wc_get_orders( [ 'customer' => $customer ] );

			$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ), wp_json_encode( $customer ) );
			$this->assertCount( 1, $orders, wp_json_encode( $customer ) );
			$this->assertEquals( $guest_order_id, $orders[0]->get_id(), wp_json_encode( $customer ) );
		}

		// Without a customer filter, both orders are returned.
		$this->assertCount( 2, wc_get_orders( [] ) );
	}

	/**
	 * Test HPOS field_query on the order status column.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_field_query_status() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$processing = new \WC_Order();
		$processing->set_status( 'processing' );
		$processing->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $processing->get_id(), true );

		$completed = new \WC_Order();
		$completed->set_status( 'completed' );
		$completed->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $completed->get_id(), true );

		$on_hold = new \WC_Order();
		$on_hold->set_status( 'on-hold' );
		$on_hold->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $on_hold->get_id(), true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$orders = wc_get_orders(
			[
				'field_query' => [
					[
						'field' => 'status',
						'value' => 'wc-completed',
					],
				],
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertSame( [ $completed->get_id() ], array_map( fn( $order ) => $order->get_id(), $orders ) );

		$orders = wc_get_orders(
			[
				'field_query' => [
					[
						'field'   => 'orders.status',
						'value'   => [ 'wc-processing', 'wc-on-hold' ],
						'compare' => 'IN',
					],
				],
				'orderby'     => 'id',
				'order'       => 'ASC',
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertSame( [ $processing->get_id(), $on_hold->get_id() ], array_map( fn( $order ) => $order->get_id(), $orders ) );
	}

	/**
	 * Test HPOS field_query compares strings case insensitively, like MySQL.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_field_query_status_is_case_insensitive() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$processing = new \WC_Order();
		$processing->set_status( 'processing' );
		$processing->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $processing->get_id(), true );

		$completed = new \WC_Order();
		$completed->set_status( 'completed' );
		$completed->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $completed->get_id(), true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$orders = wc_get_orders(
			[
				'field_query' => [
					[
						'field'   => 'wc_orders.status',
						'value'   => 'WC-COMPLETED',
						'compare' => '!=',
					],
				],
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertSame( [ $processing->get_id() ], array_map( fn( $order ) => $order->get_id(), $orders ) );
	}

	/**
	 * Test HPOS field_query on the order ID column.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_field_query_id() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$order_ids = [];
		for ( $i = 0; $i < 3; $i++ ) {
			$order = new \WC_Order();
			$order->save();
			ElasticPress\Indexables::factory()->get( 'post' )->index( $order->get_id(), true );

			$order_ids[] = $order->get_id();
		}

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		// Like in WooCommerce, IN values can be a comma separated string.
		$orders = wc_get_orders(
			[
				'field_query' => [
					[
						'field'   => 'id',
						'value'   => "{$order_ids[0]}, {$order_ids[2]}",
						'compare' => 'IN',
					],
				],
				'orderby'     => 'id',
				'order'       => 'ASC',
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertSame( [ $order_ids[0], $order_ids[2] ], array_map( fn( $order ) => $order->get_id(), $orders ) );

		$orders = wc_get_orders(
			[
				'field_query' => [
					[
						'field'   => 'id',
						'value'   => [ $order_ids[0] ],
						'compare' => 'NOT IN',
					],
				],
				'orderby'     => 'id',
				'order'       => 'ASC',
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertSame( [ $order_ids[1], $order_ids[2] ], array_map( fn( $order ) => $order->get_id(), $orders ) );
	}

	/**
	 * Test HPOS field_query on the order type and parent columns.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_field_query_type_and_parent() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$order = new \WC_Order();
		$order->set_total( 10 );
		$order->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $order->get_id(), true );

		$refund = wc_create_refund(
			[
				'order_id' => $order->get_id(),
				'amount'   => 1,
			]
		);
		ElasticPress\Indexables::factory()->get( 'post' )->index( $refund->get_id(), true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$orders = wc_get_orders(
			[
				'type'        => 'shop_order_refund',
				'field_query' => [
					[
						'field' => 'type',
						'value' => 'shop_order_refund',
					],
					[
						'field' => 'parent_id',
						'value' => $order->get_id(),
					],
				],
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertSame( [ $refund->get_id() ], array_map( fn( $order ) => $order->get_id(), $orders ) );
	}

	/**
	 * Test HPOS field_query on the order created date column.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_field_query_date_created() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$order_ids = [];
		foreach ( [ '2026-01-01', '2026-01-02', '2026-01-03' ] as $date ) {
			$order = new \WC_Order();
			$order->set_date_created( strtotime( "{$date} 10:00:00 UTC" ) );
			$order->save();
			ElasticPress\Indexables::factory()->get( 'post' )->index( $order->get_id(), true );

			$order_ids[] = $order->get_id();
		}

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$orders = wc_get_orders(
			[
				'field_query' => [
					[
						'field'   => 'orders.date_created_gmt',
						'value'   => '2026-01-02 10:00:00',
						'compare' => '>',
					],
				],
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertSame( [ $order_ids[2] ], array_map( fn( $order ) => $order->get_id(), $orders ) );

		$orders = wc_get_orders(
			[
				'field_query' => [
					[
						'field'   => 'date_created',
						'value'   => [ '2026-01-01', '2026-01-02 10:00:00' ],
						'compare' => 'BETWEEN',
					],
				],
				'orderby'     => 'id',
				'order'       => 'ASC',
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertSame( [ $order_ids[0], $order_ids[1] ], array_map( fn( $order ) => $order->get_id(), $orders ) );
	}

	/**
	 * Test HPOS field_query compares dates cast to DATE by day, like MySQL.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_field_query_date_cast() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$order = new \WC_Order();
		$order->set_date_created( strtotime( '2026-01-01 10:00:00 UTC' ) );
		$order->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $order->get_id(), true );

		$other_day = new \WC_Order();
		$other_day->set_date_created( strtotime( '2026-01-02 10:00:00 UTC' ) );
		$other_day->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $other_day->get_id(), true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$orders = wc_get_orders(
			[
				'field_query' => [
					[
						'field' => 'date_created',
						'value' => '2026-01-01',
						'type'  => 'DATE',
					],
				],
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertSame( [ $order->get_id() ], array_map( fn( $order ) => $order->get_id(), $orders ) );

		// The date is compared at midnight, so a value with a time never equals it.
		$orders = wc_get_orders(
			[
				'field_query' => [
					[
						'field' => 'date_created',
						'value' => '2026-01-01 10:00:00',
						'type'  => 'DATE',
					],
				],
			]
		);

		$this->assertEmpty( $orders );
	}

	/**
	 * Test HPOS field_query on the paid date, where unpaid orders are NULL.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_field_query_date_paid() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$paid = new \WC_Order();
		$paid->set_date_paid( strtotime( '2026-01-02 10:00:00 UTC' ) );
		$paid->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $paid->get_id(), true );

		$unpaid = new \WC_Order();
		$unpaid->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $unpaid->get_id(), true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		// As in SQL, NULL values never match, even with negative comparisons.
		$orders = wc_get_orders(
			[
				'field_query' => [
					[
						'field'   => 'date_paid',
						'value'   => '2026-01-05',
						'compare' => '!=',
					],
				],
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertSame( [ $paid->get_id() ], array_map( fn( $order ) => $order->get_id(), $orders ) );

		$orders = wc_get_orders(
			[
				'field_query' => [
					[
						'field'   => 'operational_data.date_paid_gmt',
						'compare' => 'NOT EXISTS',
					],
				],
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertSame( [ $unpaid->get_id() ], array_map( fn( $order ) => $order->get_id(), $orders ) );
	}

	/**
	 * Test HPOS field_query combines order table columns and other fields with OR.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_field_query_columns_with_or() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$john = new \WC_Order();
		$john->set_status( 'processing' );
		$john->set_billing_first_name( 'John' );
		$john->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $john->get_id(), true );

		$completed = new \WC_Order();
		$completed->set_status( 'completed' );
		$completed->set_billing_first_name( 'Lauren' );
		$completed->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $completed->get_id(), true );

		$other = new \WC_Order();
		$other->set_status( 'processing' );
		$other->set_billing_first_name( 'Lauren' );
		$other->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $other->get_id(), true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$orders = wc_get_orders(
			[
				'field_query' => [
					'relation' => 'OR',
					[
						'field' => 'status',
						'value' => 'wc-completed',
					],
					[
						'field' => 'billing_first_name',
						'value' => 'John',
					],
				],
				'orderby'     => 'id',
				'order'       => 'ASC',
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertSame( [ $john->get_id(), $completed->get_id() ], array_map( fn( $order ) => $order->get_id(), $orders ) );
	}

	/**
	 * Test HPOS field_query with nested groups and mixed relations.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_field_query_nested_relations() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$big_processing = new \WC_Order();
		$big_processing->set_status( 'processing' );
		$big_processing->set_total( 50 );
		$big_processing->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $big_processing->get_id(), true );

		$small_processing = new \WC_Order();
		$small_processing->set_status( 'processing' );
		$small_processing->set_total( 5 );
		$small_processing->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $small_processing->get_id(), true );

		$eur = new \WC_Order();
		$eur->set_status( 'completed' );
		$eur->set_currency( 'EUR' );
		$eur->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $eur->get_id(), true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$orders = wc_get_orders(
			[
				'field_query' => [
					'relation' => 'OR',
					[
						'relation' => 'AND',
						[
							'field' => 'status',
							'value' => 'wc-processing',
						],
						[
							'field'   => 'total',
							'value'   => '10',
							'compare' => '>',
						],
					],
					[
						'field' => 'currency',
						'value' => 'EUR',
					],
				],
				'orderby'     => 'id',
				'order'       => 'ASC',
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertSame( [ $big_processing->get_id(), $eur->get_id() ], array_map( fn( $order ) => $order->get_id(), $orders ) );
	}

	/**
	 * Test HPOS field_query skips clauses with an empty array value, like WooCommerce.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_field_query_empty_array_value_is_skipped() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$order = new \WC_Order();
		$order->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $order->get_id(), true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$orders = wc_get_orders(
			[
				'field_query' => [
					[
						'field'   => 'status',
						'value'   => [],
						'compare' => 'IN',
					],
				],
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertSame( [ $order->get_id() ], array_map( fn( $order ) => $order->get_id(), $orders ) );
	}

	/**
	 * Test HPOS field_query returns no orders for unknown fields, like WooCommerce.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_field_query_unknown_field() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$order = new \WC_Order();
		$order->set_status( 'processing' );
		$order->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $order->get_id(), true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		// `parent_order_id` is a column name, so it needs the table prefix.
		$orders = wc_get_orders(
			[
				'field_query' => [
					'relation' => 'OR',
					[
						'field' => 'status',
						'value' => 'wc-processing',
					],
					[
						'field' => 'parent_order_id',
						'value' => 0,
					],
				],
			]
		);

		$this->assertEmpty( $orders );
	}

	/**
	 * Test HPOS field_query compares strings case and accent insensitively, unless cast as BINARY.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_field_query_string_comparison() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$accented = new \WC_Order();
		$accented->set_billing_first_name( 'Élan' );
		$accented->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $accented->get_id(), true );

		$plain = new \WC_Order();
		$plain->set_billing_first_name( 'Elan' );
		$plain->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $plain->get_id(), true );

		$other = new \WC_Order();
		$other->set_billing_first_name( 'John' );
		$other->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $other->get_id(), true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$orders = wc_get_orders(
			[
				'field_query' => [
					[
						'field' => 'billing_first_name',
						'value' => 'elan',
					],
				],
				'orderby'     => 'id',
				'order'       => 'ASC',
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertSame( [ $accented->get_id(), $plain->get_id() ], array_map( fn( $order ) => $order->get_id(), $orders ) );

		$orders = wc_get_orders(
			[
				'field_query' => [
					[
						'field' => 'billing_first_name',
						'value' => 'Elan',
						'type'  => 'BINARY',
					],
				],
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertSame( [ $plain->get_id() ], array_map( fn( $order ) => $order->get_id(), $orders ) );
	}

	/**
	 * Test HPOS field_query LIKE and NOT LIKE match substrings.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_field_query_like() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$lauren = new \WC_Order();
		$lauren->set_billing_first_name( 'Lauren' );
		$lauren->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $lauren->get_id(), true );

		$john = new \WC_Order();
		$john->set_billing_first_name( 'John' );
		$john->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $john->get_id(), true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$orders = wc_get_orders(
			[
				'field_query' => [
					[
						'field'   => 'billing_first_name',
						'value'   => 'AURE',
						'compare' => 'LIKE',
					],
				],
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertSame( [ $lauren->get_id() ], array_map( fn( $order ) => $order->get_id(), $orders ) );

		$orders = wc_get_orders(
			[
				'field_query' => [
					[
						'field'   => 'billing_first_name',
						'value'   => 'aure',
						'compare' => 'NOT LIKE',
					],
				],
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertSame( [ $john->get_id() ], array_map( fn( $order ) => $order->get_id(), $orders ) );
	}

	/**
	 * Test HPOS field_query matches values with quotes.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_field_query_value_with_quotes() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$order = new \WC_Order();
		$order->set_billing_company( "O'Brien & Sons" );
		$order->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $order->get_id(), true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$orders = wc_get_orders(
			[
				'field_query' => [
					[
						'field' => 'billing_company',
						'value' => "O'Brien & Sons",
					],
				],
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertSame( [ $order->get_id() ], array_map( fn( $order ) => $order->get_id(), $orders ) );

		$orders = wc_get_orders(
			[
				'field_query' => [
					[
						'field'   => 'billing_address.company',
						'value'   => "o'brien",
						'compare' => 'LIKE',
					],
				],
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertSame( [ $order->get_id() ], array_map( fn( $order ) => $order->get_id(), $orders ) );
	}

	/**
	 * Test HPOS field_query leaves regular expressions to WooCommerce, as MySQL and Lucene interpret them differently.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_field_query_regexp_falls_back() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$lauren = new \WC_Order();
		$lauren->set_billing_first_name( 'Lauren' );
		$lauren->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $lauren->get_id(), true );

		$john = new \WC_Order();
		$john->set_billing_first_name( 'John' );
		$john->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $john->get_id(), true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$orders = wc_get_orders(
			[
				'field_query' => [
					[
						'field'   => 'billing_first_name',
						'value'   => '^LAU',
						'compare' => 'REGEXP',
					],
				],
			]
		);

		$this->assertFalse( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertSame( [ $lauren->get_id() ], array_map( fn( $order ) => $order->get_id(), $orders ) );

		$orders = wc_get_orders(
			[
				'field_query' => [
					[
						'field'   => 'billing_first_name',
						'value'   => 'en$',
						'compare' => 'NOT REGEXP',
					],
				],
			]
		);

		$this->assertFalse( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertSame( [ $john->get_id() ], array_map( fn( $order ) => $order->get_id(), $orders ) );
	}

	/**
	 * Test HPOS field_query rounds decimals cast to integers, like MySQL.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_field_query_numeric_cast_rounds_decimals() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$order_1 = new \WC_Order();
		$order_1->set_total( 9.50 );
		$order_1->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $order_1->get_id(), true );

		$order_2 = new \WC_Order();
		$order_2->set_total( 8.00 );
		$order_2->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $order_2->get_id(), true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		// 9.50 is rounded to 10.
		$orders = wc_get_orders(
			[
				'field_query' => [
					[
						'field'   => 'total',
						'value'   => '10',
						'compare' => '<',
						'type'    => 'NUMERIC',
					],
				],
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertSame( [ $order_2->get_id() ], array_map( fn( $order ) => $order->get_id(), $orders ) );

		$orders = wc_get_orders(
			[
				'field_query' => [
					[
						'field'   => 'total',
						'value'   => '10',
						'compare' => '<',
					],
				],
				'orderby'     => 'id',
				'order'       => 'ASC',
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertSame( [ $order_1->get_id(), $order_2->get_id() ], array_map( fn( $order ) => $order->get_id(), $orders ) );
	}

	/**
	 * Test HPOS field_query leaves DECIMAL casts to WooCommerce, as MySQL limits them to their precision.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_field_query_decimal_cast_falls_back() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$order = new \WC_Order();
		$order->set_total( 1234.56 );
		$order->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $order->get_id(), true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		// MySQL casts 1234.56 as 999.9.
		$orders = wc_get_orders(
			[
				'field_query' => [
					[
						'field' => 'total',
						'value' => '999.9',
						'type'  => 'DECIMAL(4,1)',
					],
				],
			]
		);

		$this->assertFalse( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertSame( [ $order->get_id() ], array_map( fn( $order ) => $order->get_id(), $orders ) );
	}

	/**
	 * Test HPOS field_query falls back to WooCommerce for comparisons Elasticsearch cannot do like MySQL.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_field_query_unsupported_comparison_falls_back() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$order = new \WC_Order();
		$order->set_total( 15 );
		$order->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $order->get_id(), true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		// MySQL compares the total as a string.
		$orders = wc_get_orders(
			[
				'field_query' => [
					[
						'field'   => 'total',
						'value'   => '15',
						'compare' => 'LIKE',
					],
				],
			]
		);

		$this->assertFalse( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertSame( [ $order->get_id() ], array_map( fn( $order ) => $order->get_id(), $orders ) );

		// MySQL turns negative decimals into huge numbers.
		$orders = wc_get_orders(
			[
				'field_query' => [
					[
						'field'   => 'total',
						'value'   => '10',
						'compare' => '>',
						'type'    => 'UNSIGNED',
					],
				],
			]
		);

		$this->assertFalse( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertSame( [ $order->get_id() ], array_map( fn( $order ) => $order->get_id(), $orders ) );
	}

	/**
	 * Test HPOS field_query falls back to WooCommerce for dates MySQL does not parse like PHP.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_field_query_unsupported_date_falls_back() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$order = new \WC_Order();
		$order->set_date_created( strtotime( '2026-01-01 10:00:00 UTC' ) );
		$order->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $order->get_id(), true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$orders = wc_get_orders(
			[
				'field_query' => [
					[
						'field'   => 'date_created',
						'value'   => '2026-01-01T09:00:00',
						'compare' => '>',
					],
				],
			]
		);

		$this->assertFalse( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertSame( [ $order->get_id() ], array_map( fn( $order ) => $order->get_id(), $orders ) );
	}

	/**
	 * Test HPOS field_query on boolean columns, where unset values are stored as 0.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_field_query_boolean_columns() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$with_tax = new \WC_Order();
		$with_tax->set_prices_include_tax( true );
		$with_tax->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $with_tax->get_id(), true );

		$without_tax = new \WC_Order();
		$without_tax->set_prices_include_tax( false );
		$without_tax->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $without_tax->get_id(), true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$orders = wc_get_orders(
			[
				'field_query' => [
					[
						'field' => 'prices_include_tax',
						'value' => 1,
					],
				],
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertSame( [ $with_tax->get_id() ], array_map( fn( $order ) => $order->get_id(), $orders ) );

		$orders = wc_get_orders(
			[
				'field_query' => [
					[
						'field' => 'order_stock_reduced',
						'value' => 0,
					],
				],
				'orderby'     => 'id',
				'order'       => 'ASC',
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertSame( [ $with_tax->get_id(), $without_tax->get_id() ], array_map( fn( $order ) => $order->get_id(), $orders ) );
	}

	/**
	 * Test HPOS field_query on address fields, where empty values are stored as NULL.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_field_query_empty_address_field_is_null() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$with_last_name = new \WC_Order();
		$with_last_name->set_billing_first_name( 'John' );
		$with_last_name->set_billing_last_name( 'Doe' );
		$with_last_name->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $with_last_name->get_id(), true );

		$without_last_name = new \WC_Order();
		$without_last_name->set_billing_first_name( 'Ann' );
		$without_last_name->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $without_last_name->get_id(), true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$orders = wc_get_orders(
			[
				'field_query' => [
					[
						'field'   => 'billing_last_name',
						'value'   => 'Smith',
						'compare' => '!=',
					],
				],
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertSame( [ $with_last_name->get_id() ], array_map( fn( $order ) => $order->get_id(), $orders ) );

		$orders = wc_get_orders(
			[
				'field_query' => [
					[
						'field'   => 'billing_address.last_name',
						'compare' => 'NOT EXISTS',
					],
				],
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertSame( [ $without_last_name->get_id() ], array_map( fn( $order ) => $order->get_id(), $orders ) );
	}

	/**
	 * Test HPOS field_query on address fields never matches orders without that address, even with OR.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_field_query_address_field_requires_address() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$without_shipping = new \WC_Order();
		$without_shipping->set_status( 'pending' );
		$without_shipping->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $without_shipping->get_id(), true );

		$with_shipping = new \WC_Order();
		$with_shipping->set_status( 'processing' );
		$with_shipping->set_shipping_country( 'FR' );
		$with_shipping->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $with_shipping->get_id(), true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$orders = wc_get_orders(
			[
				'field_query' => [
					'relation' => 'OR',
					[
						'field' => 'status',
						'value' => 'wc-pending',
					],
					[
						'field' => 'shipping_country',
						'value' => 'FR',
					],
				],
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertSame( [ $with_shipping->get_id() ], array_map( fn( $order ) => $order->get_id(), $orders ) );
	}

	/**
	 * Test HPOS field_query on refund totals.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_field_query_refund_total() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$order = new \WC_Order();
		$order->set_total( 10 );
		$order->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $order->get_id(), true );

		$refund = wc_create_refund(
			[
				'order_id' => $order->get_id(),
				'amount'   => 4,
			]
		);
		ElasticPress\Indexables::factory()->get( 'post' )->index( $refund->get_id(), true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$orders = wc_get_orders(
			[
				'field_query' => [
					[
						'field'   => 'total',
						'value'   => '-4',
						'compare' => '<=',
					],
				],
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertSame( [ $refund->get_id() ], array_map( fn( $order ) => $order->get_id(), $orders ) );
	}

	/**
	 * Test HPOS field_query combined with other query arguments.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_field_query_with_other_args() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$matching = new \WC_Order();
		$matching->set_status( 'processing' );
		$matching->set_currency( 'EUR' );
		$matching->update_meta_data( 'color', 'red' );
		$matching->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $matching->get_id(), true );

		$other_color = new \WC_Order();
		$other_color->set_status( 'processing' );
		$other_color->set_currency( 'EUR' );
		$other_color->update_meta_data( 'color', 'blue' );
		$other_color->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $other_color->get_id(), true );

		$other_status = new \WC_Order();
		$other_status->set_status( 'completed' );
		$other_status->set_currency( 'EUR' );
		$other_status->update_meta_data( 'color', 'red' );
		$other_status->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $other_status->get_id(), true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$orders = wc_get_orders(
			[
				'status'      => 'processing',
				'meta_query'  => [
					[
						'key'   => 'color',
						'value' => 'red',
					],
				],
				'field_query' => [
					[
						'field' => 'currency',
						'value' => 'EUR',
					],
				],
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertSame( [ $matching->get_id() ], array_map( fn( $order ) => $order->get_id(), $orders ) );
	}

	/**
	 * Test HPOS field_query EXISTS comparisons and `=` with an array value.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_field_query_exists_comparisons() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$paid = new \WC_Order();
		$paid->set_billing_first_name( 'John' );
		$paid->set_date_paid( strtotime( '2026-01-01 10:00:00 UTC' ) );
		$paid->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $paid->get_id(), true );

		$unpaid = new \WC_Order();
		$unpaid->set_billing_first_name( 'Jane' );
		$unpaid->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $unpaid->get_id(), true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$queries = [
			[
				'clause'   => [
					'field'   => 'date_paid',
					'compare' => 'EXISTS',
				],
				'expected' => [ $paid->get_id() ],
			],
			// EXISTS with a value is interpreted as `=`.
			[
				'clause'   => [
					'field'   => 'billing_first_name',
					'value'   => 'Jane',
					'compare' => 'EXISTS',
				],
				'expected' => [ $unpaid->get_id() ],
			],
			// `=` with an array value is interpreted as IN.
			[
				'clause'   => [
					'field' => 'billing_first_name',
					'value' => [ 'John', 'Jane' ],
				],
				'expected' => [ $paid->get_id(), $unpaid->get_id() ],
			],
		];

		foreach ( $queries as $query ) {
			$args = [
				'field_query' => [ $query['clause'] ],
				'orderby'     => 'id',
				'order'       => 'ASC',
			];

			$orders = wc_get_orders( $args );
			$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ), wp_json_encode( $query['clause'] ) );
			$this->assertSame( $query['expected'], array_map( fn( $order ) => $order->get_id(), $orders ), wp_json_encode( $query['clause'] ) );
			$this->assertSame(
				$query['expected'],
				wc_get_orders(
					$args + [
						'return'       => 'ids',
						'ep_integrate' => false,
					]
				),
				wp_json_encode( $query['clause'] )
			);
		}
	}

	/**
	 * Test HPOS field_query comparisons on decimals, with and without the NUMERIC cast.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_field_query_numeric_comparisons() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$order_ids = [];
		foreach ( [ 9.4, 9.6, 10.5, 12 ] as $total ) {
			$order = new \WC_Order();
			$order->set_total( $total );
			$order->save();
			ElasticPress\Indexables::factory()->get( 'post' )->index( $order->get_id(), true );
			$order_ids[] = $order->get_id();
		}

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		// With the NUMERIC cast, MySQL rounds the totals to 9, 10, 11 and 12.
		$queries = [
			[
				'clause'   => [
					'value'   => '10.5',
					'compare' => '>=',
				],
				'expected' => [ $order_ids[2], $order_ids[3] ],
			],
			[
				'clause'   => [
					'value' => '10',
					'type'  => 'NUMERIC',
				],
				'expected' => [ $order_ids[1] ],
			],
			[
				'clause'   => [
					'value'   => [ '9', '11' ],
					'compare' => 'IN',
					'type'    => 'NUMERIC',
				],
				'expected' => [ $order_ids[0], $order_ids[2] ],
			],
			[
				'clause'   => [
					'value'   => [ '10', '11' ],
					'compare' => 'BETWEEN',
					'type'    => 'NUMERIC',
				],
				'expected' => [ $order_ids[1], $order_ids[2] ],
			],
			[
				'clause'   => [
					'value'   => '10',
					'compare' => '>',
					'type'    => 'NUMERIC',
				],
				'expected' => [ $order_ids[2], $order_ids[3] ],
			],
			[
				'clause'   => [
					'value'   => '10',
					'compare' => '<=',
					'type'    => 'NUMERIC',
				],
				'expected' => [ $order_ids[0], $order_ids[1] ],
			],
		];

		foreach ( $queries as $query ) {
			$args = [
				'field_query' => [ $query['clause'] + [ 'field' => 'total' ] ],
				'orderby'     => 'id',
				'order'       => 'ASC',
			];

			$orders = wc_get_orders( $args );
			$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ), wp_json_encode( $query['clause'] ) );
			$this->assertSame( $query['expected'], array_map( fn( $order ) => $order->get_id(), $orders ), wp_json_encode( $query['clause'] ) );
			$this->assertSame(
				$query['expected'],
				wc_get_orders(
					$args + [
						'return'       => 'ids',
						'ep_integrate' => false,
					]
				),
				wp_json_encode( $query['clause'] )
			);
		}
	}

	/**
	 * Test HPOS field_query comparisons on dates cast to DATE.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_field_query_date_cast_comparisons() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$order_ids = [];
		foreach ( [ '2026-01-01', '2026-01-02', '2026-01-03' ] as $date ) {
			$order = new \WC_Order();
			$order->set_date_created( strtotime( "{$date} 10:00:00 UTC" ) );
			$order->save();
			ElasticPress\Indexables::factory()->get( 'post' )->index( $order->get_id(), true );
			$order_ids[] = $order->get_id();
		}

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$queries = [
			[
				'clause'   => [
					'value'   => [ '2026-01-01', '2026-01-03' ],
					'compare' => 'IN',
				],
				'expected' => [ $order_ids[0], $order_ids[2] ],
			],
			// The DATE cast drops the time, so 2026-01-01 is before 2026-01-01 12:00:00.
			[
				'clause'   => [
					'value'   => [ '2026-01-01 12:00:00', '2026-01-02' ],
					'compare' => 'BETWEEN',
				],
				'expected' => [ $order_ids[1] ],
			],
			[
				'clause'   => [
					'value'   => '2026-01-01',
					'compare' => '>',
				],
				'expected' => [ $order_ids[1], $order_ids[2] ],
			],
			[
				'clause'   => [
					'value'   => '2026-01-02',
					'compare' => '>=',
				],
				'expected' => [ $order_ids[1], $order_ids[2] ],
			],
			[
				'clause'   => [
					'value'   => '2026-01-02',
					'compare' => '<',
				],
				'expected' => [ $order_ids[0] ],
			],
			[
				'clause'   => [
					'value'   => '2026-01-02',
					'compare' => '<=',
				],
				'expected' => [ $order_ids[0], $order_ids[1] ],
			],
		];

		foreach ( $queries as $query ) {
			$args = [
				'field_query' => [
					$query['clause'] + [
						'field' => 'date_created',
						'type'  => 'DATE',
					],
				],
				'orderby'     => 'id',
				'order'       => 'ASC',
			];

			$orders = wc_get_orders( $args );
			$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ), wp_json_encode( $query['clause'] ) );
			$this->assertSame( $query['expected'], array_map( fn( $order ) => $order->get_id(), $orders ), wp_json_encode( $query['clause'] ) );
			$this->assertSame(
				$query['expected'],
				wc_get_orders(
					$args + [
						'return'       => 'ids',
						'ep_integrate' => false,
					]
				),
				wp_json_encode( $query['clause'] )
			);
		}
	}

	/**
	 * Test HPOS field_query comparisons on boolean columns.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_field_query_boolean_comparisons() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$with_tax = new \WC_Order();
		$with_tax->set_prices_include_tax( true );
		$with_tax->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $with_tax->get_id(), true );

		$without_tax = new \WC_Order();
		$without_tax->set_prices_include_tax( false );
		$without_tax->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $without_tax->get_id(), true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		// Booleans are stored as 1 and 0.
		$queries = [
			[
				'clause'   => [
					'value'   => [ 1 ],
					'compare' => 'IN',
				],
				'expected' => [ $with_tax->get_id() ],
			],
			[
				'clause'   => [
					'value'   => [ 0, 0 ],
					'compare' => 'BETWEEN',
				],
				'expected' => [ $without_tax->get_id() ],
			],
			[
				'clause'   => [
					'value'   => 0,
					'compare' => '>',
				],
				'expected' => [ $with_tax->get_id() ],
			],
			[
				'clause'   => [
					'value'   => 1,
					'compare' => '>=',
				],
				'expected' => [ $with_tax->get_id() ],
			],
			[
				'clause'   => [
					'value'   => 1,
					'compare' => '<',
				],
				'expected' => [ $without_tax->get_id() ],
			],
			[
				'clause'   => [
					'value'   => 0,
					'compare' => '<=',
				],
				'expected' => [ $without_tax->get_id() ],
			],
		];

		foreach ( $queries as $query ) {
			$args = [
				'field_query' => [ $query['clause'] + [ 'field' => 'prices_include_tax' ] ],
			];

			$orders = wc_get_orders( $args );
			$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ), wp_json_encode( $query['clause'] ) );
			$this->assertSame( $query['expected'], array_map( fn( $order ) => $order->get_id(), $orders ), wp_json_encode( $query['clause'] ) );
			$this->assertSame(
				$query['expected'],
				wc_get_orders(
					$args + [
						'return'       => 'ids',
						'ep_integrate' => false,
					]
				),
				wp_json_encode( $query['clause'] )
			);
		}

		$this->orders->clear_elasticsearch_success_order_ids();

		// LIKE on a boolean is left to WooCommerce.
		$orders = wc_get_orders(
			[
				'field_query' => [
					[
						'field'   => 'prices_include_tax',
						'value'   => 'yes',
						'compare' => 'NOT LIKE',
					],
				],
				'orderby'     => 'id',
				'order'       => 'ASC',
			]
		);

		$this->assertFalse( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertSame( [ $with_tax->get_id(), $without_tax->get_id() ], array_map( fn( $order ) => $order->get_id(), $orders ) );
	}

	/**
	 * Test HPOS field_query casts that do not change the compared values.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_field_query_casts_without_conversion() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$order_1 = new \WC_Order();
		$order_1->set_date_created( strtotime( '2026-01-01 10:00:00 UTC' ) );
		$order_1->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $order_1->get_id(), true );

		$order_2 = new \WC_Order();
		$order_2->set_date_created( strtotime( '2026-01-02 10:00:00 UTC' ) );
		$order_2->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $order_2->get_id(), true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$clauses = [
			// Integer columns are not changed by NUMERIC casts.
			[
				'field'   => 'id',
				'value'   => $order_2->get_id(),
				'compare' => '>=',
				'type'    => 'NUMERIC',
			],
			// Date columns are not changed by DATETIME casts.
			[
				'field'   => 'date_created',
				'value'   => '2026-01-02 00:00:00',
				'compare' => '>',
				'type'    => 'DATETIME',
			],
		];

		foreach ( $clauses as $clause ) {
			$args = [ 'field_query' => [ $clause ] ];

			$orders = wc_get_orders( $args );
			$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ), wp_json_encode( $clause ) );
			$this->assertSame( [ $order_2->get_id() ], array_map( fn( $order ) => $order->get_id(), $orders ), wp_json_encode( $clause ) );
			$this->assertSame(
				[ $order_2->get_id() ],
				wc_get_orders(
					$args + [
						'return'       => 'ids',
						'ep_integrate' => false,
					]
				),
				wp_json_encode( $clause )
			);
		}
	}

	/**
	 * Test the HPOS field_query translation returns null for columns without an order property.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_field_query_unindexed_column_is_not_translated() {
		$query = $this->createMock( \Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableQuery::class );

		// Like the shipping address email, columns without an order property have no field name.
		$query->method( 'get_field_mapping_info' )->willReturn(
			[
				'table'       => 'wp_wc_order_addresses',
				'mapping_id'  => 'shipping_address',
				'field_name'  => '',
				'column'      => 'email',
				'column_type' => 'string',
			]
		);

		$field_query = [
			[
				'field' => 'shipping_address.email',
				'value' => 'john@example.com',
			],
		];

		$this->assertNull( ( new ElasticPress\Feature\WooCommerce\OrdersHPOSFieldQuery( $query, $field_query ) )->get_es_filter() );
	}

	/**
	 * Test HPOS date_query columns set on a group, or on a clause passed without a wrapping array.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_date_query_column_inheritance() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		// Created before 2026, but paid after.
		$paid_late = new \WC_Order();
		$paid_late->set_date_created( strtotime( '2025-12-30 10:00:00 UTC' ) );
		$paid_late->set_date_paid( strtotime( '2026-01-02 10:00:00 UTC' ) );
		$paid_late->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $paid_late->get_id(), true );

		// Created after 2026, but paid before.
		$paid_early = new \WC_Order();
		$paid_early->set_date_created( strtotime( '2026-01-02 10:00:00 UTC' ) );
		$paid_early->set_date_paid( strtotime( '2025-12-30 10:00:00 UTC' ) );
		$paid_early->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $paid_early->get_id(), true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$date_queries = [
			// Clauses use the column of their group.
			[
				'column' => 'date_paid_gmt',
				[
					'after' => '2026-01-01',
				],
			],
			// Nested groups use the column of their parent group.
			[
				'column' => 'date_paid_gmt',
				[
					'relation' => 'OR',
					[
						'after' => '2026-01-01',
					],
				],
			],
			// A single clause without a wrapping array.
			[
				'column' => 'date_paid_gmt',
				'after'  => '2026-01-01',
			],
		];

		foreach ( $date_queries as $date_query ) {
			$orders = wc_get_orders( [ 'date_query' => $date_query ] );
			$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ), wp_json_encode( $date_query ) );
			$this->assertSame( [ $paid_late->get_id() ], array_map( fn( $order ) => $order->get_id(), $orders ), wp_json_encode( $date_query ) );
			$this->assertSame(
				[ $paid_late->get_id() ],
				wc_get_orders(
					[
						'date_query'   => $date_query,
						'return'       => 'ids',
						'ep_integrate' => false,
					]
				),
				wp_json_encode( $date_query )
			);
		}
	}

	/**
	 * Test the HPOS date_query translation returns null for clauses it cannot translate.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_date_query_untranslatable_clauses() {
		$date_queries = [
			// Columns that are not indexed.
			[
				[
					'column' => 'wp_posts.post_date',
					'after'  => '2026-01-01',
				],
			],
			// Invalid dates.
			[
				[
					'column' => 'wp_wc_orders.date_created_gmt',
					'year'   => 2026,
					'month'  => 2,
					'day'    => 30,
				],
			],
		];

		foreach ( $date_queries as $date_query ) {
			$query = $this->createMock( \Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableQuery::class );
			$query->method( 'get' )->willReturnMap( [ [ 'date_query', $date_query ] ] );

			$this->assertNull( ( new ElasticPress\Feature\WooCommerce\OrdersHPOSDateQuery( $query ) )->get_es_filter(), wp_json_encode( $date_query ) );
		}
	}

	/**
	 * Test HPOS search with the order ID and products search filters.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_search_query_with_order_id_and_products() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$widget_item = new \WC_Order_Item_Product();
		$widget_item->set_name( 'Blue Widget' );

		$widget_order = new \WC_Order();
		$widget_order->add_item( $widget_item );
		$widget_order->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $widget_order->get_id(), true );

		$mug_item = new \WC_Order_Item_Product();
		$mug_item->set_name( 'Coffee Mug' );

		$mug_order = new \WC_Order();
		$mug_order->add_item( $mug_item );
		$mug_order->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $mug_order->get_id(), true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$searches = [
			[
				's'             => 'Widget',
				'search_filter' => 'products',
			],
			[
				's'             => (string) $widget_order->get_id(),
				'search_filter' => 'order_id',
			],
			// Numeric search terms also match order IDs when searching all fields.
			[
				's'             => (string) $widget_order->get_id(),
				'search_filter' => 'all',
			],
		];

		foreach ( $searches as $search ) {
			$orders = wc_get_orders( $search );
			$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ), wp_json_encode( $search ) );
			$this->assertSame( [ $widget_order->get_id() ], array_map( fn( $order ) => $order->get_id(), $orders ), wp_json_encode( $search ) );
		}
	}

	/**
	 * Test HPOS top-level order fields skip empty values and duplicate aliases.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_top_level_fields_skip_empty_values_and_aliases() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$order_1 = new \WC_Order();
		$order_1->set_total( 20 );
		$order_1->set_billing_city( 'Paris' );
		$order_1->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $order_1->get_id(), true );

		$order_2 = new \WC_Order();
		$order_2->set_total( 30 );
		$order_2->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $order_2->get_id(), true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = [
			// `order_total` is an alias of `total`.
			'total'           => 20,
			'order_total'     => 20,
			'billing_city'    => '',
			'billing_company' => null,
		];

		$orders = wc_get_orders( $args );
		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertSame( [ $order_1->get_id() ], array_map( fn( $order ) => $order->get_id(), $orders ) );
		$this->assertSame(
			[ $order_1->get_id() ],
			wc_get_orders(
				$args + [
					'return'       => 'ids',
					'ep_integrate' => false,
				]
			)
		);
	}

	/**
	 * Test HPOS indexing leaves documents of other post types and missing orders untouched.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_order_data_ignores_other_documents() {
		$this->enable_hpos();

		$orders_hpos = new ElasticPress\Feature\WooCommerce\OrdersHPOS( $this->orders );

		$post_args = [
			'post_type'  => 'post',
			'post_title' => 'Not an order',
		];
		$this->assertSame( $post_args, $orders_hpos->set_order_data( $post_args, 1 ) );

		$post_args = [
			'post_type' => \Automattic\WooCommerce\Internal\DataStores\Orders\DataSynchronizer::PLACEHOLDER_ORDER_POST_TYPE,
		];
		$this->assertSame( $post_args, $orders_hpos->set_order_data( $post_args, PHP_INT_MAX ) );

		$order_meta = [ 'color' => [ 'red' ] ];
		$this->assertSame( $order_meta, $orders_hpos->prepare_meta_data( $order_meta, new \WP_Post( (object) [ 'ID' => PHP_INT_MAX ] ) ) );

		// Only OrdersTableQuery queries are checked for unsupported orderby values.
		$this->assertFalse( $orders_hpos->skip_query_integration( false, new \WP_Query() ) );
	}

	/**
	 * Test HPOS hooks are removed when the orders integration is torn down.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_tear_down_removes_hooks() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		// WooCommerce and other features use the same hooks, so only check for the HPOS callbacks.
		$has_hpos_callback = function ( $hook, $method ) {
			foreach ( $GLOBALS['wp_filter'][ $hook ]->callbacks ?? [] as $callbacks ) {
				foreach ( $callbacks as $callback ) {
					if ( is_array( $callback['function'] ) && $callback['function'][0] instanceof ElasticPress\Feature\WooCommerce\OrdersHPOS && $method === $callback['function'][1] ) {
						return true;
					}
				}
			}
			return false;
		};

		$hooks = [
			'woocommerce_hpos_pre_query' => 'maybe_intercept_wc_orders_query',
			'woocommerce_update_order'   => 'sync_order',
			'woocommerce_delete_order'   => 'delete_order',
			'ep_post_filters'            => 'add_hpos_filters',
		];

		foreach ( $hooks as $hook => $method ) {
			$this->assertTrue( $has_hpos_callback( $hook, $method ), $hook );
		}

		$this->orders->tear_down();

		foreach ( $hooks as $hook => $method ) {
			$this->assertFalse( $has_hpos_callback( $hook, $method ), $hook );
		}
	}

	/**
	 * Test HPOS order fields are compared like MySQL does, e.g. case-insensitively.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_order_fields_are_compared_like_mysql() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$john = new \WC_Order();
		$john->set_billing_first_name( 'John' );
		$john->set_billing_email( 'john@example.com' );
		$john->set_created_via( 'checkout' );
		$john->set_prices_include_tax( true );
		$john->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $john->get_id(), true );

		$jane = new \WC_Order();
		$jane->set_billing_first_name( 'Jane' );
		$jane->set_billing_email( 'jane@example.com' );
		$jane->set_created_via( 'admin' );
		$jane->set_prices_include_tax( false );
		$jane->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $jane->get_id(), true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$queries = [
			[ 'billing_first_name' => 'JOHN' ],
			[ 'billing_email' => 'John@Example.com' ],
			[ 'customer' => 'JOHN@EXAMPLE.COM' ],
			[ 'created_via' => 'Checkout' ],
			// Booleans are formatted like WooCommerce does.
			[ 'prices_include_tax' => 'yes' ],
		];

		foreach ( $queries as $args ) {
			$orders = wc_get_orders( $args );
			$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ), wp_json_encode( $args ) );
			$this->assertSame( [ $john->get_id() ], array_map( fn( $order ) => $order->get_id(), $orders ), wp_json_encode( $args ) );
			$this->assertSame(
				[ $john->get_id() ],
				wc_get_orders(
					$args + [
						'return'       => 'ids',
						'ep_integrate' => false,
					]
				),
				wp_json_encode( $args )
			);
		}
	}

	/**
	 * Test HPOS `customer` values that are neither IDs nor emails match no orders, like in WooCommerce.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_invalid_customer_matches_no_orders() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$order = new \WC_Order();
		$order->set_billing_email( 'john@example.com' );
		$order->save();
		ElasticPress\Indexables::factory()->get( 'post' )->index( $order->get_id(), true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$es_result     = null;
		$get_es_result = function ( $result ) use ( &$es_result ) {
			$es_result = $result;
			return $result;
		};
		add_filter( 'woocommerce_hpos_pre_query', $get_es_result, 20 );

		$this->assertSame( [], wc_get_orders( [ 'customer' => 'not-an-email' ] ) );
		$this->assertSame( [], $es_result[0] );
		$this->assertSame(
			[],
			wc_get_orders(
				[
					'customer'     => 'not-an-email',
					'ep_integrate' => false,
				]
			)
		);

		remove_filter( 'woocommerce_hpos_pre_query', $get_es_result, 20 );
	}

	/**
	 * Test HPOS unlimited queries fall back to WooCommerce when Elasticsearch cannot return all results.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_unlimited_query_falls_back_when_results_are_capped() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$order_ids = [];
		foreach ( range( 1, 2 ) as $i ) {
			$order = new \WC_Order();
			$order->save();
			ElasticPress\Indexables::factory()->get( 'post' )->index( $order->get_id(), true );
			$order_ids[] = $order->get_id();
		}

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = [
			'limit'   => -1,
			'orderby' => 'id',
			'order'   => 'ASC',
		];

		$orders = wc_get_orders( $args );
		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertSame( $order_ids, array_map( fn( $order ) => $order->get_id(), $orders ) );

		$this->orders->clear_elasticsearch_success_order_ids();

		// Elasticsearch returns up to `ep_max_results_window` results.
		$max_results_window = fn() => 1;
		add_filter( 'ep_max_results_window', $max_results_window );

		$orders = wc_get_orders( $args );
		$this->assertFalse( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertSame( $order_ids, array_map( fn( $order ) => $order->get_id(), $orders ) );

		remove_filter( 'ep_max_results_window', $max_results_window );
	}

	/**
	 * Test HPOS order counts exclude orders that are indexed but missing from the database.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_order_counts_exclude_orders_missing_from_database() {
		global $wpdb;

		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$order_ids = [];
		foreach ( range( 1, 3 ) as $i ) {
			$order = new \WC_Order();
			$order->save();
			ElasticPress\Indexables::factory()->get( 'post' )->index( $order->get_id(), true );
			$order_ids[] = $order->get_id();
		}

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		// Delete the first order from the database only, so its document becomes stale.
		$orders_table = \Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore::get_orders_table_name();
		$wpdb->delete( $orders_table, [ 'id' => $order_ids[0] ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		wp_cache_flush();

		$result = wc_get_orders(
			[
				'limit'    => 2,
				'paginate' => true,
				'orderby'  => 'id',
				'order'    => 'ASC',
			]
		);

		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $result->orders[0] ) );
		$this->assertSame( [ $order_ids[1] ], array_map( fn( $order ) => $order->get_id(), $result->orders ) );
		$this->assertSame( 2, $result->total );
		$this->assertSame( 1, $result->max_num_pages );
	}

	/**
	 * Test HPOS queries without a limit, page or order, as when OrdersTableQuery is used directly.
	 *
	 * @since 5.4.0
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function test_hpos_query_without_limit_page_and_order() {
		$this->enable_hpos();

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$order_ids = [];
		foreach ( range( 1, 2 ) as $i ) {
			$order = new \WC_Order();
			$order->save();
			ElasticPress\Indexables::factory()->get( 'post' )->index( $order->get_id(), true );
			$order_ids[] = $order->get_id();
		}

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$es_result     = null;
		$get_es_result = function ( $result ) use ( &$es_result ) {
			$es_result = $result;
			return $result;
		};
		add_filter( 'woocommerce_hpos_pre_query', $get_es_result, 20 );

		$query = new \Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableQuery( [ 'type' => 'shop_order' ] );

		remove_filter( 'woocommerce_hpos_pre_query', $get_es_result, 20 );

		$this->assertNotNull( $es_result );
		$this->assertEqualsCanonicalizing( $order_ids, array_map( 'absint', $query->orders ) );
	}
}
