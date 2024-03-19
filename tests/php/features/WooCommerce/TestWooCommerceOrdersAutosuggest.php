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
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );

		$this->woocommerce_feature = ElasticPress\Features::factory()->get_registered_feature( 'woocommerce' );
		if ( empty( $this->woocommerce_feature->orders ) ) {
			$this->woocommerce_feature->orders = new \ElasticPress\Feature\WooCommerce\Orders();
		}

		ElasticPress\Features::factory()->setup_features();

		$this->orders_autosuggest = $this->woocommerce_feature->orders_autosuggest;
	}

	/**
	 * Test the `filter_term_suggest` method
	 *
	 * @group woocommerce
	 * @group woocommerce-orders-autosuggest
	 */
	public function testFilterTermSuggest() {
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
	public function testFilterTermSuggestWithCustomOrderId() {
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
		$set_custom_order_id = function( $order_id ) {
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
	public function testMappingEs7() {
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
	public function testMappingEs5() {
		$change_es_version = function() {
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
	public function testSetSearchFields() {
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
		$filter = function() {
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
		$change_custom_orders_table = function() {
			return 'yes';
		};
		add_filter( 'pre_option_' . $custom_orders_table, $change_custom_orders_table );

		// Disable legacy mode
		$legacy_mode        = \Automattic\WooCommerce\Internal\DataStores\Orders\DataSynchronizer::ORDERS_DATA_SYNC_ENABLED_OPTION;
		$change_legacy_mode = function() {
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
		$change_custom_orders_table = function() {
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
}
