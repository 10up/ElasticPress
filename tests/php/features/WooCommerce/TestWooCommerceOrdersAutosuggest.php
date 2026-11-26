<?php
/**
 * Test woocommerce orders feature
 *
 * @since 4.5.0
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;

/**
 * WC Orders test class
 */
class TestWooCommerceOrdersAutosuggest extends BaseTestCase {
	/**
	 * Instance of the feature
	 *
	 * @var ElasticPress\Feature\WooCommerce\WooCommerce
	 */
	public $woocommerce_feature;

	/**
	 * Orders instance
	 *
	 * @var \ElasticPress\Feature\WooCommerce\OrdersAutosuggest
	 */
	public $orders_autosuggest;

	/**
	 * Setup each test.
	 *
	 * @group woocommerce
	 * @group woocommerce-orders-autosuggest
	 */
	public function set_up() {
		parent::set_up();
		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );

		$this->woocommerce_feature = ElasticPress\Features::factory()->get_registered_feature( 'woocommerce' );
		if ( empty( $this->woocommerce_feature->orders ) ) {
			$this->woocommerce_feature->orders = new \ElasticPress\Feature\WooCommerce\Orders();
		}

		ElasticPress\Features::factory()->setup_features();

		ElasticPress\Elasticsearch::factory()->delete_all_indices();
		ElasticPress\Indexables::factory()->get( 'post' )->put_mapping();

		ElasticPress\Indexables::factory()->get( 'post' )->sync_manager->reset_sync_queue();

		$this->orders_autosuggest = $this->woocommerce_feature->orders_autosuggest;
	}

	/**
	 * Test the `filter_term_suggest` method
	 *
	 * @group woocommerce
	 * @group woocommerce-orders-autosuggest
	 */
	public function test_filter_term_suggest() {
		$order = [
			'ID'        => 123,
			'post_type' => 'shop_order',
			'meta'      => [
				'_billing_email'      => [
					[ 'value' => '_billing_email_example' ],
				],
				'_billing_last_name'  => [
					[ 'value' => '_billing_last_name_example' ],
				],
				'_billing_first_name' => [
					[ 'value' => '_billing_first_name_example' ],
				],
			],
		];

		$order_with_suggest = $this->orders_autosuggest->filter_term_suggest( $order );

		$this->assertArrayHasKey( 'term_suggest', $order_with_suggest );
		$this->assertContains( '_billing_email_example', $order_with_suggest['term_suggest'] );
		$this->assertContains( '_billing_last_name_example', $order_with_suggest['term_suggest'] );
		$this->assertContains( '_billing_first_name_example', $order_with_suggest['term_suggest'] );

		$this->assertSame(
			[
				'raw'   => 123,
				'value' => 123,
			],
			$order_with_suggest['meta']['order_number'][0]
		);

		unset( $order['post_type'] );
		$order_with_suggest = $this->orders_autosuggest->filter_term_suggest( $order );
		$this->assertArrayNotHasKey( 'term_suggest', $order_with_suggest );

		$order['post_type'] = 'not_shop_order';
		$order_with_suggest = $this->orders_autosuggest->filter_term_suggest( $order );
		$this->assertArrayNotHasKey( 'term_suggest', $order_with_suggest );

		$order['post_type'] = 'shop_order';
		unset( $order['meta'] );
		$order_with_suggest = $this->orders_autosuggest->filter_term_suggest( $order );
		$this->assertArrayNotHasKey( 'term_suggest', $order_with_suggest );
	}

	/**
	 * Test the `filter_term_suggest` method with some Order Id changes
	 *
	 * This method steps into WooCommerce functionality a bit.
	 *
	 * @group woocommerce
	 * @group woocommerce-orders-autosuggest
	 */
	public function test_filter_term_suggest_with_custom_order_id() {
		$shop_order_1 = new \WC_Order();
		$shop_order_1->set_billing_email( 'test@domain.com' );
		$shop_order_1->set_billing_first_name( 'John' );
		$shop_order_1->set_billing_last_name( 'Doe' );
		$shop_order_1->save();
		$shop_order_id_1 = (string) $shop_order_1->get_id();

		$prepared_shop_order = ElasticPress\Indexables::factory()->get( 'post' )->prepare_document( $shop_order_id_1 );
		$order_with_suggest  = $this->orders_autosuggest->filter_term_suggest( $prepared_shop_order );

		$this->assertSame(
			[
				'raw'   => $shop_order_id_1,
				'value' => $shop_order_id_1,
			],
			$order_with_suggest['meta']['order_number'][0]
		);

		/**
		 * Set a custom Order Number
		 */
		$set_custom_order_id = function ( $order_id ) {
			return 'custom-' . $order_id;
		};
		add_filter( 'woocommerce_order_number', $set_custom_order_id );

		$order_with_suggest = $this->orders_autosuggest->filter_term_suggest( $prepared_shop_order );

		$this->assertSame(
			[
				'raw'   => 'custom-' . $shop_order_id_1,
				'value' => 'custom-' . $shop_order_id_1,
			],
			$order_with_suggest['meta']['order_number'][0]
		);
	}

	/**
	 * Test the `mapping` method with the ES 7 mapping
	 *
	 * @group woocommerce
	 * @group woocommerce-orders-autosuggest
	 */
	public function test_mapping_es7() {
		$original_mapping = [
			'mappings' => [
				'properties' => [
					'post_content' => [ 'type' => 'text' ],
				],
			],
		];
		$changed_mapping  = $this->orders_autosuggest->mapping( $original_mapping );

		$expected_mapping = [
			'mappings' => [
				'properties' => [
					'post_content' => [ 'type' => 'text' ],
					'term_suggest' => [
						'type'            => 'text',
						'analyzer'        => 'edge_ngram_analyzer',
						'search_analyzer' => 'standard',
					],
				],
			],
			'settings' => [
				'analysis' => [
					'analyzer' => [
						'edge_ngram_analyzer' => [
							'type'      => 'custom',
							'tokenizer' => 'standard',
							'filter'    => [
								'lowercase',
								'edge_ngram',
							],
						],
					],
				],
			],
		];

		$this->assertSame( $expected_mapping, $changed_mapping );
	}

	/**
	 * Test the `mapping` method with the ES 5 mapping
	 *
	 * @group woocommerce
	 * @group woocommerce-orders-autosuggest
	 */
	public function test_mapping_es5() {
		$change_es_version = function () {
			return '5.6';
		};
		add_filter( 'ep_elasticsearch_version', $change_es_version );

		$original_mapping = [
			'mappings' => [
				'post' => [
					'properties' => [
						'post_content' => [ 'type' => 'text' ],
					],
				],
			],
		];

		$changed_mapping = $this->orders_autosuggest->mapping( $original_mapping );

		$expected_mapping = [
			'mappings' => [
				'post' => [
					'properties' => [
						'post_content' => [ 'type' => 'text' ],
						'term_suggest' => [
							'type'            => 'text',
							'analyzer'        => 'edge_ngram_analyzer',
							'search_analyzer' => 'standard',
						],
					],
				],
			],
			'settings' => [
				'analysis' => [
					'analyzer' => [
						'edge_ngram_analyzer' => [
							'type'      => 'custom',
							'tokenizer' => 'standard',
							'filter'    => [
								'lowercase',
								'edge_ngram',
							],
						],
					],
				],
			],
		];

		$this->assertSame( $expected_mapping, $changed_mapping );
	}

	/**
	 * Test the `set_search_fields` method
	 *
	 * @group woocommerce
	 * @group woocommerce-orders-autosuggest
	 */
	public function test_set_search_fields() {
		$original_search_fields = [ 'old_search_field' ];

		$wp_query = new \WP_Query(
			[
				'ep_integrate'             => true,
				'ep_order_search_template' => false,
				'post_type'                => 'shop_order',
				's'                        => '{{ep_placeholder}}',
			]
		);

		$changed_search_fields = $this->orders_autosuggest->set_search_fields( $original_search_fields, $wp_query );

		$this->assertSame( $original_search_fields, $changed_search_fields );

		$wp_query = new \WP_Query(
			[
				'ep_integrate'             => true,
				'ep_order_search_template' => true,
				'post_type'                => 'shop_order',
				's'                        => '{{ep_placeholder}}',
			]
		);

		$changed_search_fields = $this->orders_autosuggest->set_search_fields( $original_search_fields, $wp_query );

		$expected_fields = [
			'meta.order_number.value',
			'term_suggest',
			'meta' => [
				'_billing_email',
				'_billing_last_name',
				'_billing_first_name',
			],
		];

		$this->assertSame( $expected_fields, $changed_search_fields );
	}

	/**
	 * Test the `is_available` method
	 *
	 * @since 5.1.0
	 * @group woocommerce
	 * @group woocommerce-orders-autosuggest
	 */
	public function test_is_available() {
		$this->assertSame( $this->orders_autosuggest->is_available(), \ElasticPress\Utils\is_epio() );

		/**
		 * Test the `ep_woocommerce_orders_autosuggest_available` filter
		 */
		add_filter( 'ep_woocommerce_orders_autosuggest_available', '__return_true' );
		$this->assertTrue( $this->orders_autosuggest->is_available() );

		add_filter( 'ep_woocommerce_orders_autosuggest_available', '__return_false' );
		$this->assertFalse( $this->orders_autosuggest->is_available() );
	}

	/**
	 * Test the `is_enabled` method
	 *
	 * @since 5.1.0
	 * @group woocommerce
	 * @group woocommerce-orders-autosuggest
	 */
	public function test_is_enabled() {
		$this->assertFalse( $this->orders_autosuggest->is_enabled() );

		/**
		 * Make it available but it won't be enabled
		 */
		add_filter( 'ep_woocommerce_orders_autosuggest_available', '__return_true' );
		$this->assertFalse( $this->orders_autosuggest->is_enabled() );

		/**
		 * Enable it
		 */
		$filter = function () {
			return [
				'woocommerce' => [
					'orders' => '1',
				],
			];
		};
		add_filter( 'pre_site_option_ep_feature_settings', $filter );
		add_filter( 'pre_option_ep_feature_settings', $filter );
		$this->assertTrue( $this->orders_autosuggest->is_enabled() );

		/**
		 * Make it unavailable. Even activated, it should not be considered enabled if not available anymore.
		 */
		remove_filter( 'ep_woocommerce_orders_autosuggest_available', '__return_true' );
		$this->assertFalse( $this->orders_autosuggest->is_enabled() );
	}

	/**
	 * Test the `is_hpos_compatible` method
	 *
	 * @since 5.1.0
	 * @group woocommerce
	 * @group woocommerce-orders-autosuggest
	 */
	public function test_is_hpos_compatible() {
		$this->assertTrue( $this->orders_autosuggest->is_hpos_compatible() );

		// Turn HPOS on
		$custom_orders_table        = \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::CUSTOM_ORDERS_TABLE_USAGE_ENABLED_OPTION;
		$change_custom_orders_table = function () {
			return 'yes';
		};
		add_filter( 'pre_option_' . $custom_orders_table, $change_custom_orders_table );

		// Disable legacy mode
		$legacy_mode        = \Automattic\WooCommerce\Internal\DataStores\Orders\DataSynchronizer::ORDERS_DATA_SYNC_ENABLED_OPTION;
		$change_legacy_mode = function () {
			return 'no';
		};
		add_filter( 'pre_option_' . $legacy_mode, $change_legacy_mode );

		$this->assertFalse( $this->orders_autosuggest->is_hpos_compatible() );
	}

	/**
	 * Test the `add_settings_schema` method
	 *
	 * @since 5.1.0
	 * @group woocommerce
	 * @group woocommerce-orders-autosuggest
	 */
	public function test_add_settings_schema() {
		$new_settings_schema = $this->orders_autosuggest->add_settings_schema( [] );
		$this->assertSame( 'orders', $new_settings_schema[0]['key'] );
	}

	/**
	 * Test the `get_setting_help_message` method when the feature is available
	 *
	 * @since 5.1.0
	 * @group woocommerce
	 * @group woocommerce-orders-autosuggest
	 */
	public function test_get_setting_help_message_feature_available() {
		/**
		 * Make it available but it won't be enabled
		 */
		add_filter( 'ep_woocommerce_orders_autosuggest_available', '__return_true' );

		$new_settings_schema = $this->orders_autosuggest->add_settings_schema( [] );
		$this->assertStringContainsString( 'You are directly connected to', $new_settings_schema[0]['help'] );
	}

	/**
	 * Test the `get_setting_help_message` method when incompatible with HPOS
	 *
	 * @since 5.1.0
	 * @group woocommerce
	 * @group woocommerce-orders-autosuggest
	 */
	public function test_get_setting_help_message_feature_hpos_incompatible() {
		// Turn HPOS on
		$custom_orders_table        = \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::CUSTOM_ORDERS_TABLE_USAGE_ENABLED_OPTION;
		$change_custom_orders_table = function () {
			return 'yes';
		};
		add_filter( 'pre_option_' . $custom_orders_table, $change_custom_orders_table );

		$new_settings_schema = $this->orders_autosuggest->add_settings_schema( [] );
		$this->assertStringContainsString( 'Currently, autosuggest for orders is only available', $new_settings_schema[0]['help'] );
	}

	/**
	 * Test the `get_setting_help_message` method when the feature is not available
	 *
	 * @since 5.1.0
	 * @group woocommerce
	 * @group woocommerce-orders-autosuggest
	 */
	public function test_get_setting_help_message_feature_not_available() {
		$new_settings_schema = $this->orders_autosuggest->add_settings_schema( [] );
		$this->assertStringContainsString( 'Due to the sensitive nature of orders', $new_settings_schema[0]['help'] );
	}

	/**
	 * Test shop_order with password is synced without Protected Content feature enabled.
	 *
	 * @since 5.3.3
	 * @group woocommerce
	 * @group woocommerce-orders-autosuggest
	 */
	public function test_order_with_password_is_synced() {
		add_filter( 'ep_woocommerce_orders_autosuggest_available', '__return_true' );

		/**
		 * Enable the orders autosuggest feature.
		 */
		$filter = function () {
			return [
				'woocommerce' => [
					'orders' => '1',
				],
			];
		};
		add_filter( 'pre_site_option_ep_feature_settings', $filter );
		add_filter( 'pre_option_ep_feature_settings', $filter );

		$this->orders_autosuggest->setup();

		$order = new \WC_Order();
		$order->set_order_key( '1234567890' ); // save in the post_password field
		$order->save();

		$order_id = $order->get_id();

		ElasticPress\Indexables::factory()->get( 'post' )->index( $order_id );
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$order = ElasticPress\Indexables::factory()->get( 'post' )->get( $order_id );
		$this->assertNotEmpty( $order );

		remove_filter( 'ep_woocommerce_orders_autosuggest_available', '__return_true' );
		remove_filter( 'pre_option_ep_feature_settings', $filter );
		remove_filter( 'pre_site_option_ep_feature_settings', $filter );
	}

	/**
	 * Endpoints follow the store after an instance has already been constructed.
	 *
	 * @group orders-completion
	 */
	public function test_template_endpoint_follows_current_store() {
		$site_id = self::factory()->blog->create();
		$origin  = get_current_blog_id();
		switch_to_blog( $site_id );
		$index = ElasticPress\Indexables::factory()->get( 'post' )->get_index_name();

		try {
			$this->assertSame( "api/v1/search/orders/{$index}/template", $this->orders_autosuggest->get_template_endpoint() );
			$this->assertSame( "api/v1/search/orders/{$index}", $this->orders_autosuggest->get_search_endpoint() );
		} finally {
			restore_current_blog();
		}

		$this->assertSame( $origin, get_current_blog_id() );
	}

	/**
	 * The marked template retains order fields under CLI without integrating normal queries.
	 *
	 * @group orders-completion
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_cli_template_contains_order_search_fields() {
		define( 'WP_CLI', true );
		add_filter( 'ep_woocommerce_orders_autosuggest_available', '__return_true' );
		$settings                          = ElasticPress\Utils\get_option( 'ep_feature_settings', [] );
		$settings['woocommerce']['orders'] = '1';
		ElasticPress\Utils\update_option( 'ep_feature_settings', $settings );
		$this->woocommerce_feature->orders->setup();
		$this->orders_autosuggest->setup();
		$template = $this->orders_autosuggest->get_search_template();

		foreach ( [ 'meta.order_number.value', 'term_suggest', 'meta._billing_email.value', 'meta._billing_last_name.value', 'meta._billing_first_name.value' ] as $field ) {
			$this->assertStringContainsString( $field, $template );
		}

		$this->assertStringContainsString( 'shop_order', $template );
		$this->assertStringNotContainsString( 'post_password', $template );
		$query = new \WP_Query();
		$query->set( 'post_type', 'shop_order' );
		$query->set( 's', 'ordinary CLI query' );
		$this->assertFalse( $this->woocommerce_feature->should_integrate_with_query( $query ) );
	}


	/**
	 * Enable template generation and capture actual template writes.
	 *
	 * @param array $writes Captured requests.
	 */
	private function capture_template_writes( array &$writes ): void {
		add_filter( 'ep_woocommerce_orders_autosuggest_available', '__return_true' );
		$settings                          = ElasticPress\Utils\get_option( 'ep_feature_settings', [] );
		$settings['woocommerce']['orders'] = '1';
		ElasticPress\Utils\update_option( 'ep_feature_settings', $settings );
		update_option( 'active_plugins', [ 'woocommerce/woocommerce.php' ] );
		$this->woocommerce_feature->setup();
		add_filter( 'pre_transient_wc_attribute_taxonomies', '__return_empty_array' );
		add_filter(
			'pre_http_request',
			static function ( $response, $args, $url ) use ( &$writes ) {
				$is_template = 'PUT' === $args['method'] && false !== strpos( $url, '/api/v1/search/orders/' );

				if ( $is_template ) {
					$writes[] = [
						'url'  => $url,
						'body' => $args['body'],
						'site' => get_current_blog_id(),
					];
					return [
						'response' => [ 'code' => 200 ],
						'body'     => '{}',
						'headers'  => [],
					];
				}

				return $response;
			},
			10,
			3
		);
	}

	/**
	 * Non-native completion uses selected scope and restores the caller.
	 *
	 * @group orders-completion
	 */
	public function test_selected_completion_only_writes_target_template() {
		$target = self::factory()->blog->create();
		update_blog_option( $target, 'active_plugins', [ 'woocommerce/woocommerce.php' ] );
		$writes = [];
		$this->capture_template_writes( $writes );
		$origin = get_current_blog_id();
		do_action(
			'ep_after_sync_index',
			[
				'site_id'      => $target,
				'network_wide' => 0,
			]
		);
		$this->assertSame( [ $target ], array_column( $writes, 'site' ) );
		$this->assertStringContainsString( ElasticPress\Indexables::factory()->get( 'post' )->get_index_name( $target ) . '/template', $writes[0]['url'] );
		$this->assertStringContainsString( 'meta.order_number.value', $writes[0]['body'] );
		$this->assertSame( $origin, get_current_blog_id() );
	}

	/**
	 * Network completion excludes stores without WooCommerce and ineligible sites.
	 *
	 * @group orders-completion
	 */
	public function test_network_completion_only_writes_eligible_store_templates() {
		remove_filter( 'option_active_plugins', __NAMESPACE__ . '\\add_woocommerce_to_active_plugins' );
		$target = self::factory()->blog->create();
		// This eligible site intentionally has no active WooCommerce plugin.
		self::factory()->blog->create();
		$excluded = self::factory()->blog->create();
		update_blog_option( $target, 'active_plugins', [ 'woocommerce/woocommerce.php' ] );
		update_blog_option( $excluded, 'active_plugins', [ 'woocommerce/woocommerce.php' ] );
		update_site_meta( $excluded, 'ep_indexable', 'no' );
		$writes = [];
		$this->capture_template_writes( $writes );
		$origin = get_current_blog_id();
		$this->assertContains( $target, array_map( 'intval', wp_list_pluck( ElasticPress\Utils\get_sites( 0, true ), 'blog_id' ) ) );
		$this->assertContains( 'woocommerce/woocommerce.php', get_blog_option( $target, 'active_plugins' ) );
		do_action( 'ep_after_sync_index', [ 'network_wide' => 0 ] );
		$this->assertEqualsCanonicalizing( [ $origin, $target ], array_column( $writes, 'site' ) );
		$this->assertSame( $origin, get_current_blog_id() );
	}

	/**
	 * Native jobs save once at the store's last active task, not again at origin.
	 *
	 * @group orders-completion
	 */
	public function test_native_completion_waits_for_last_store_task() {
		$writes = [];
		$this->capture_template_writes( $writes );
		$site_id = get_current_blog_id();
		$item    = [
			'blog_id'   => $site_id,
			'indexable' => 'post',
		];
		$meta    = [
			'site_context' => true,
			'sync_stack'   => [ $item ],
		];
		do_action( 'ep_after_sync_item', $item, $meta );
		$this->assertSame( [], $writes );
		$meta['sync_stack'] = [];
		do_action( 'ep_after_sync_item', $item, $meta );
		$this->assertSame( [ $site_id ], array_column( $writes, 'site' ) );
		do_action(
			'ep_after_sync_index',
			[
				'site_context' => true,
				'network_wide' => 0,
			]
		);
		$this->assertSame( [ $site_id ], array_column( $writes, 'site' ) );
	}

	/**
	 * A store that no longer supports Orders does not receive a network template.
	 *
	 * @group orders-completion
	 */
	public function test_network_completion_skips_unavailable_store() {
		$target = self::factory()->blog->create();
		$writes = [];
		$this->capture_template_writes( $writes );
		add_filter(
			'ep_woocommerce_orders_autosuggest_available',
			static function ( $available ) use ( $target ) {
				return get_current_blog_id() === $target ? false : $available;
			},
			20
		);
		do_action( 'ep_after_sync_index', [ 'network_wide' => 0 ] );
		$this->assertSame( [ get_current_blog_id() ], array_column( $writes, 'site' ) );
	}
}
