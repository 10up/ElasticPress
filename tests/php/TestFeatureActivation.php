<?php
/**
 * Test feature activation, registration, and deactivation.
 *
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;

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

		ElasticPress\Indexables::factory()->get( 'post' )->sync_manager->sync_queue = [];

		$this->setup_test_post_type();
	}

	/**
	 * Clean up after each test. Reset our mocks
	 *
	 * @since 2.2
	 */
	public function tear_down() {
		parent::tear_down();

		// make sure no one attached to this
		remove_filter( 'ep_sync_terms_allow_hierarchy', array( $this, 'ep_allow_multiple_level_terms_sync' ), 100 );
		$this->fired_actions = array();
	}

	/**
	 * Make sure no feature settings or req statuses exist at the start.
	 *
	 * @group feature-activation
	 * @since  2.2
	 */
	public function testNoActiveFeatures() {
		// VIP: Use per-site option
		delete_option( 'ep_feature_requirement_statuses' );
		delete_option( 'ep_feature_settings' );

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
		// VIP: Use per-site option
		delete_option( 'ep_feature_requirement_statuses' );
		delete_option( 'ep_feature_settings' );

		$this->handle_feature_activation();
		ElasticPress\Features::factory()->setup_features();

		$this->assertEquals( true, ElasticPress\Features::factory()->registered_features['search']->is_active() );
		$this->assertEquals( 0, ElasticPress\Features::factory()->registered_features['search']->requirements_status()->code );

		$this->assertEquals( false, ElasticPress\Features::factory()->registered_features['protected_content']->is_active() );
		$this->assertEquals( 1, ElasticPress\Features::factory()->registered_features['protected_content']->requirements_status()->code );

		$this->assertEquals( false, ElasticPress\Features::factory()->registered_features['users']->is_active() );
		$this->assertEquals( 1, ElasticPress\Features::factory()->registered_features['users']->requirements_status()->code );

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
		// VIP: Use per-site option
		delete_option( 'ep_feature_requirement_statuses' );
		delete_option( 'ep_feature_settings' );

		$this->handle_feature_activation();
		ElasticPress\Features::factory()->setup_features();

		// VIP: Use per-site option
		$requirements_statuses = get_option( 'ep_feature_requirement_statuses' );

		$this->assertEquals( 0, $requirements_statuses['search'] );
		$this->assertEquals( 1, $requirements_statuses['protected_content'] );
		$this->assertEquals( 1, $requirements_statuses['users'] );
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
		// VIP: Use per-site option
		delete_option( 'ep_feature_requirement_statuses' );
		delete_option( 'ep_feature_settings' );

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
		// VIP: Use per-site option
		delete_option( 'ep_feature_requirement_statuses' );
		delete_option( 'ep_feature_settings' );

		ElasticPress\Features::factory()->register_feature(
			new FeatureTest()
		);

		$this->handle_feature_activation();
		ElasticPress\Features::factory()->setup_features();

		// VIP: Use per-site option
		$requirements_statuses = get_option( 'ep_feature_requirement_statuses' );

		$this->assertEquals( true, ElasticPress\Features::factory()->registered_features['test']->is_active() );
		$this->assertEquals( 0, ElasticPress\Features::factory()->registered_features['test']->requirements_status()->code );
		$this->assertEquals( 0, $requirements_statuses['test'] );

		// VIP: Use per-site option
		update_option( 'ep_test_feature_on', 2 );

		$this->handle_feature_activation();

		// VIP: Use per-site option
		$requirements_statuses = get_option( 'ep_feature_requirement_statuses' );

		$this->assertEquals( false, ElasticPress\Features::factory()->registered_features['test']->is_active() );
		$this->assertEquals( 2, ElasticPress\Features::factory()->registered_features['test']->requirements_status()->code );
		$this->assertEquals( 2, $requirements_statuses['test'] );
	}

	/**
	 * Test that a simple EP feature is registered and auto-activated properly. Also
	 * test that when it's req status changes, it's enabled.
	 *
	 * @group feature-activation
	 * @since  2.2
	 */
	public function testAutoActivateWithFeature() {
		// VIP: Use per-site option
		delete_option( 'ep_feature_requirement_statuses' );
		delete_option( 'ep_feature_settings' );

		ElasticPress\Features::factory()->register_feature(
			new FeatureTest()
		);

		// VIP: Use per-site option
		update_option( 'ep_test_feature_on', 2 );

		$this->handle_feature_activation();
		ElasticPress\Features::factory()->setup_features();

		// VIP: Use per-site option
		$requirements_statuses = get_option( 'ep_feature_requirement_statuses' );

		$this->assertEquals( false, ElasticPress\Features::factory()->registered_features['test']->is_active() );
		$this->assertEquals( 2, ElasticPress\Features::factory()->registered_features['test']->requirements_status()->code );
		$this->assertEquals( 2, $requirements_statuses['test'] );

		// VIP: Use per-site option
		update_option( 'ep_test_feature_on', 0 );

		$this->handle_feature_activation();

		// VIP: Use per-site option
		$requirements_statuses = get_option( 'ep_feature_requirement_statuses' );

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
		// VIP: Use per-site option
		delete_option( 'ep_feature_requirement_statuses' );
		delete_option( 'ep_feature_settings' );

		ElasticPress\Features::factory()->register_feature(
			new FeatureTest()
		);

		// VIP: Use per-site option
		update_option( 'ep_test_feature_on', 0 );

		$this->handle_feature_activation();
		ElasticPress\Features::factory()->setup_features();

		// VIP: Use per-site option
		$requirements_statuses = get_option( 'ep_feature_requirement_statuses' );

		$this->assertEquals( true, ElasticPress\Features::factory()->registered_features['test']->is_active() );
		$this->assertEquals( 0, ElasticPress\Features::factory()->registered_features['test']->requirements_status()->code );
		$this->assertEquals( 0, $requirements_statuses['test'] );

		// VIP: Use per-site option
		update_option( 'ep_test_feature_on', 1 );

		$this->handle_feature_activation();

		// VIP: Use per-site option
		$requirements_statuses = get_option( 'ep_feature_requirement_statuses' );

		$this->assertEquals( true, ElasticPress\Features::factory()->registered_features['test']->is_active() );
		$this->assertEquals( 1, ElasticPress\Features::factory()->registered_features['test']->requirements_status()->code );
		$this->assertEquals( 1, $requirements_statuses['test'] );
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
