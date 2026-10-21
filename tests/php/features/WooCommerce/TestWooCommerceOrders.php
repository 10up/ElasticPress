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

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$orders = wc_get_orders(
			[
				'customer_email' => 'test@example.com',
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
				'customer' => [
					[ null, 'test1@example.com' ],
					[ null, 'test2@example.com' ],
				],
				'orderby'  => 'ID',
				'order'    => 'ASC',
			]
		);
		$this->assertTrue( $this->orders->order_has_elasticsearch_success( $orders[0] ) );
		$this->assertCount( 2, $orders );
		$this->assertEquals( $shop_order_id_1, $orders[0]->get_id() );
		$this->assertEquals( $shop_order_id_2, $orders[1]->get_id() );
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
		$this->assertCount( 2, $orders );

		$this->assertEquals( $order_1_id, $orders[0]->get_id() );
		$this->assertEquals( $order_3_id, $orders[1]->get_id() );
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
}
