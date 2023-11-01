<?php
/**
 * Test feature activation, registration, and deactivation.
 *
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;
use \ElasticPress\Features;
use \ElasticPress\REST\Features as FeaturesRest;

/**
 * Feature activation test class
 */
class TestFeatureActivation extends BaseTestCase {
	/**
	 * Checking if HTTP request returns 404 status code.
	 *
	 * @var boolean
	 */
	public $is_404 = false;

	/**
	 * Setup each test.
	 *
	 * @since 2.2
	 */
	public function set_up() {
		global $wpdb;
		parent::set_up();
		$wpdb->suppress_errors();

		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );

		wp_set_current_user( $admin_id );

		ElasticPress\Elasticsearch::factory()->delete_all_indices();
		ElasticPress\Indexables::factory()->get( 'post' )->put_mapping();

		ElasticPress\Indexables::factory()->get( 'post' )->sync_manager->reset_sync_queue();

		$this->setup_test_post_type();
	}

	/**
	 * Clean up after each test. Reset our mocks
	 *
	 * @since 2.2
	 */
	public function tear_down() {
		parent::tear_down();

		$this->fired_actions = array();
	}

	/**
	 * Make sure no feature settings or req statuses exist at the start.
	 *
	 * @group feature-activation
	 * @since  2.2
	 */
	public function testNoActiveFeatures() {
		delete_site_option( 'ep_feature_requirement_statuses' );
		delete_site_option( 'ep_feature_settings' );

		ElasticPress\Features::factory()->setup_features();

		foreach ( ElasticPress\Features::factory()->registered_features as $feature ) {
			$this->assertEquals( false, $feature->is_active() );
		}
	}

	/**
	 * Test that default EP features are auto-activated properly
	 *
	 * @group feature-activation
	 * @since  2.2
	 */
	public function testAutoActivated() {
		delete_site_option( 'ep_feature_requirement_statuses' );
		delete_site_option( 'ep_feature_settings' );

		$this->handle_feature_activation();
		ElasticPress\Features::factory()->setup_features();

		$this->assertEquals( true, ElasticPress\Features::factory()->registered_features['search']->is_active() );
		$this->assertEquals( 0, ElasticPress\Features::factory()->registered_features['search']->requirements_status()->code );

		$this->assertEquals( false, ElasticPress\Features::factory()->registered_features['protected_content']->is_active() );
		$this->assertEquals( 1, ElasticPress\Features::factory()->registered_features['protected_content']->requirements_status()->code );

		$this->assertEquals( true, ElasticPress\Features::factory()->registered_features['woocommerce']->is_active() );
		$this->assertEquals( 0, ElasticPress\Features::factory()->registered_features['woocommerce']->requirements_status()->code );

		$this->assertEquals( true, ElasticPress\Features::factory()->registered_features['related_posts']->is_active() );
		$this->assertEquals( 0, ElasticPress\Features::factory()->registered_features['related_posts']->requirements_status()->code );
	}

	/**
	 * Test that default EP requirement statuses are set properly in options
	 *
	 * @group feature-activation
	 * @since  2.2
	 */
	public function testRequirementStatuses() {
		delete_site_option( 'ep_feature_requirement_statuses' );
		delete_site_option( 'ep_feature_settings' );

		$this->handle_feature_activation();
		ElasticPress\Features::factory()->setup_features();

		$requirements_statuses = get_site_option( 'ep_feature_requirement_statuses' );

		$this->assertEquals( 0, $requirements_statuses['search'] );
		$this->assertEquals( 1, $requirements_statuses['protected_content'] );
		$this->assertEquals( 0, $requirements_statuses['related_posts'] );
		$this->assertEquals( 0, $requirements_statuses['woocommerce'] );
	}

	/**
	 * Test that a simple EP feature is registered and auto-activated properly
	 *
	 * @group feature-activation
	 * @since  2.2
	 */
	public function testAutoActivateWithSimpleFeature() {
		delete_site_option( 'ep_feature_requirement_statuses' );
		delete_site_option( 'ep_feature_settings' );

		ElasticPress\Features::factory()->register_feature(
			new FeatureTest()
		);

		$this->handle_feature_activation();
		ElasticPress\Features::factory()->setup_features();

		$this->assertEquals( true, ElasticPress\Features::factory()->registered_features['test']->is_active() );
		$this->assertEquals( 0, ElasticPress\Features::factory()->registered_features['test']->requirements_status()->code );
	}

	/**
	 * Test that a simple EP feature is registered and auto-activated properly. Also
	 * test that when it's req status changes, it's disabled.
	 *
	 * @group feature-activation
	 * @since  2.2
	 */
	public function testAutoDeactivateWithFeature() {
		delete_site_option( 'ep_feature_requirement_statuses' );
		delete_site_option( 'ep_feature_settings' );

		ElasticPress\Features::factory()->register_feature(
			new FeatureTest()
		);

		$this->handle_feature_activation();
		ElasticPress\Features::factory()->setup_features();

		$requirements_statuses = get_site_option( 'ep_feature_requirement_statuses' );

		$this->assertEquals( true, ElasticPress\Features::factory()->registered_features['test']->is_active() );
		$this->assertEquals( 0, ElasticPress\Features::factory()->registered_features['test']->requirements_status()->code );
		$this->assertEquals( 0, $requirements_statuses['test'] );

		update_site_option( 'ep_test_feature_on', 2 );

		$this->handle_feature_activation();

		$requirements_statuses = get_site_option( 'ep_feature_requirement_statuses' );

		$this->assertEquals( false, ElasticPress\Features::factory()->registered_features['test']->is_active() );
		$this->assertEquals( 2, ElasticPress\Features::factory()->registered_features['test']->requirements_status()->code );
		$this->assertEquals( 2, $requirements_statuses['test'] );
	}

	/**
	 * Test that a simple EP feature is registered and auto-activated properly. Also
	 * test that when it's req status changes, it's enabled.
	 *
	 * @group feature-activation
	 * @since 5.0.0
	 */
	public function test_auto_activate_with_feature() {
		delete_site_option( 'ep_feature_requirement_statuses' );
		delete_site_option( 'ep_feature_settings' );

		$feature = new FeatureTest();

		$feature->requires_install_reindex = false;

		ElasticPress\Features::factory()->register_feature( $feature );

		update_site_option( 'ep_test_feature_on', 2 );

		$this->handle_feature_activation();
		ElasticPress\Features::factory()->setup_features();

		$requirements_statuses = get_site_option( 'ep_feature_requirement_statuses' );

		$this->assertEquals( false, ElasticPress\Features::factory()->registered_features['test']->is_active() );
		$this->assertEquals( 2, ElasticPress\Features::factory()->registered_features['test']->requirements_status()->code );
		$this->assertEquals( 2, $requirements_statuses['test'] );

		update_site_option( 'ep_test_feature_on', 0 );

		$this->handle_feature_activation();

		$requirements_statuses = get_site_option( 'ep_feature_requirement_statuses' );

		$this->assertEquals( true, ElasticPress\Features::factory()->registered_features['test']->is_active() );
		$this->assertEquals( 0, ElasticPress\Features::factory()->registered_features['test']->requirements_status()->code );
		$this->assertEquals( 0, $requirements_statuses['test'] );
	}

	/**
	 * Test that a simple EP feature is registered and auto-activated properly. Test that when the req
	 * status changes to 1, nothing happens.
	 *
	 * @group feature-activation
	 * @since  2.2
	 */
	public function testReqChangeNothingWithFeature() {
		delete_site_option( 'ep_feature_requirement_statuses' );
		delete_site_option( 'ep_feature_settings' );

		ElasticPress\Features::factory()->register_feature(
			new FeatureTest()
		);

		update_site_option( 'ep_test_feature_on', 0 );

		$this->handle_feature_activation();
		ElasticPress\Features::factory()->setup_features();

		$requirements_statuses = get_site_option( 'ep_feature_requirement_statuses' );

		$this->assertEquals( true, ElasticPress\Features::factory()->registered_features['test']->is_active() );
		$this->assertEquals( 0, ElasticPress\Features::factory()->registered_features['test']->requirements_status()->code );
		$this->assertEquals( 0, $requirements_statuses['test'] );

		update_site_option( 'ep_test_feature_on', 1 );

		$this->handle_feature_activation();

		$requirements_statuses = get_site_option( 'ep_feature_requirement_statuses' );

		$this->assertEquals( true, ElasticPress\Features::factory()->registered_features['test']->is_active() );
		$this->assertEquals( 1, ElasticPress\Features::factory()->registered_features['test']->requirements_status()->code );
		$this->assertEquals( 1, $requirements_statuses['test'] );
	}

	/**
	 * Test the `get_settings` method
	 *
	 * @since 4.5.0
	 */
	public function testGetSettings() {
		$feature = new FeatureTest();

		$default_settings = [
			'setting_1' => 123,
			'setting_2' => false,
		];

		$feature->default_settings = $default_settings;

		$this->assertEquals( $default_settings, $feature->get_settings() );

		$new_values = [
			'setting_1' => 456,
			'setting_2' => true,
			'setting_3' => 'custom_string',
		];

		$filter = function() use ( $new_values ) {
			return [
				'test' => $new_values,
			];
		};
		add_filter( 'pre_site_option_ep_feature_settings', $filter );
		add_filter( 'pre_option_ep_feature_settings', $filter );

		$this->assertEquals( $new_values, $feature->get_settings() );
	}

	/**
	 * Test the `get_setting` method
	 *
	 * @since 4.5.0
	 */
	public function testGetSetting() {
		$feature = new FeatureTest();

		$feature->default_settings = [
			'setting_1' => 123,
			'setting_2' => false,
		];

		$this->assertEquals( 123, $feature->get_setting( 'setting_1' ) );
		$this->assertFalse( $feature->get_setting( 'setting_2' ) );
		$this->assertNull( $feature->get_setting( 'non_existent_setting' ) );

		$filter = function() {
			return [
				'test' => [
					'setting_1' => 456,
					'setting_3' => 'new_string',
				],
			];
		};
		add_filter( 'pre_site_option_ep_feature_settings', $filter );
		add_filter( 'pre_option_ep_feature_settings', $filter );

		$this->assertEquals( 456, $feature->get_setting( 'setting_1' ) );
		$this->assertFalse( $feature->get_setting( 'setting_2' ) );
		$this->assertEquals( 'new_string', $feature->get_setting( 'setting_3' ) );
		$this->assertNull( $feature->get_setting( 'non_existent_setting' ) );
	}

	/**
	 * Test if feature settings are updated when sent via REST API
	 *
	 * @since 5.0.0
	 * @group feature-activation
	 */
	public function test_feature_setting_update() {
		Features::factory()->register_feature(
			new FeatureTest()
		);

		$controller = new FeaturesRest();
		$request    = new \WP_REST_Request( 'PUT', '/elasticpress/v1/features' );
		$request->set_param(
			'test',
			[
				'active'  => '1',
				'field_1' => '1',
				'field_2' => '1',
				'field_3' => '1',
				'field_4' => '1',
			]
		);
		$request->set_param( 'did-you-mean', [ 'active' => '1' ] );

		$controller->update_settings( $request );

		$current_settings = Features::factory()->get_feature_settings();

		/*
		 * field_2 will not be set yet as it requires a sync
		 * field_3 will not be set yet as it requires the did-you-mean-feature
		 */
		$this->assertSame(
			[
				'active'         => false, // requires a sync, so will be false
				'force_inactive' => false,
				'field_1'        => '1',
				'field_4'        => '1', // As search is already active, this field is set now
			],
			$current_settings['test']
		);

		$draft_settings = Features::factory()->get_feature_settings_draft();
		$this->assertSame(
			[
				'active'         => true,
				'force_inactive' => false,
				'field_1'        => '1',
				'field_4'        => '1',
				'field_2'        => '1',
				'field_3'        => '1',
			],
			$draft_settings['test']
		);
	}

	/**
	 * Test if feature settings are applied after a sync
	 *
	 * @since 5.0.0
	 * @group feature-activation
	 */
	public function test_feature_setting_applied_after_sync() {
		Features::factory()->register_feature(
			new FeatureTest()
		);

		$test_settings = [
			'active'  => '1',
			'field_1' => '1',
			'field_2' => '1',
			'field_3' => '1',
		];

		Features::factory()->update_feature( 'test', $test_settings, true, 'draft' );

		$wp_cli = new \ElasticPress\Command();

		$wp_cli->sync(
			[],
			[
				'setup' => true,
				'yes'   => true,
			]
		);
		ob_clean();

		$updated_settings = Features::factory()->get_feature_settings();
		$this->assertSame(
			$updated_settings['test'],
			[
				'active'         => true,
				'force_inactive' => false,
				'field_1'        => '1',
				'field_2'        => '1',
				'field_3'        => '1',
			]
		);
	}

	/**
	 * Wrapper for Features::handle_feature_activation() calls in admin context.
	 *
	 * To avoid unnecessary updates on the `ep_feature_requirement_statuses` option,
	 * the `Features::handle_feature_activation()` only changes the option value when called in admin or WP-CLI contexts.
	 */
	protected function handle_feature_activation() {
		set_current_screen( 'edit.php' );
		ElasticPress\Features::factory()->handle_feature_activation();
		set_current_screen( 'front' );
	}
}
