<?php
/**
 * Test weighting sub-feature
 *
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;

/**
 * Weighting test class
 */
class TestWeighting extends BaseTestCase {

	/**
	 * Setup each test.
	 *
	 * @since 3.4.1
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
		ElasticPress\Features::factory()->activate_feature( 'search' );
	}

	/**
	 * Clean up after each test. Reset our mocks
	 *
	 * @since 2.1
	 */
	public function tearDown() {
		parent::tearDown();

		// make sure no one attached to this
		remove_filter( 'ep_sync_terms_allow_hierarchy', array( $this, 'ep_allow_multiple_level_terms_sync' ), 100 );
		$this->fired_actions = array();
		$this->get_weighting_feature()->save_weighting_configuration( [ 'weighting' => [] ] );

	}


	/**
	 * Test searchable post_types exist after configuration change
	 */
	function testWeightablePostType() {
		$search = ElasticPress\Features::factory()->get_registered_feature( 'search' );

		$searchable_post_types = $search->get_searchable_post_types();

		$weighting_settings = [
			'weighting' => [
				'post' => [
					'post_title' => [
						'enabled' => 'on',
						'weight'  => 1
					]
				],
			]
		];

		$this->get_weighting_feature()->save_weighting_configuration( $weighting_settings );

		$weighting_configuration = $this->get_weighting_feature()->get_weighting_configuration();

		$this->assertEquals( count( $searchable_post_types ), count( array_keys( $weighting_configuration ) ) );

		$this->assertFalse( in_array( 'ep_test_not_public', array_keys( $weighting_configuration ), true ) );

	}

	/**
	 * Test settings toggle
	 */
	public function testWeightingConfiguration() {

		$weighting_ep_test = $this->get_weighting_feature()->get_post_type_default_settings( 'ep_test' );
		$this->assertTrue( $weighting_ep_test['post_title']['enabled'] );

		$weighting_configuration = $this->get_weighting_feature()->get_weighting_configuration();
		$this->assertEmpty( $weighting_configuration );

		$weighting_settings = [
			'weighting' => [
				'post' => [
					'post_title' => [
						'enabled' => 'on',
						'weight'  => 1
					]
				],
			]
		];

		// enable post_title weighting
		$this->get_weighting_feature()->save_weighting_configuration( $weighting_settings );
		$weighting_configuration = $this->get_weighting_feature()->get_weighting_configuration();
		$this->assertTrue( $weighting_configuration['post']['post_title']['enabled'] );
		$this->assertEquals( 1, $weighting_configuration['post']['post_title']['weight'] );

		// disable post_title weighting
		$weighting_settings['weighting']['post']['post_title']['enabled'] = '';
		$this->get_weighting_feature()->save_weighting_configuration( $weighting_settings );
		$weighting_configuration = $this->get_weighting_feature()->get_weighting_configuration();
		$this->assertFalse( $weighting_configuration['post']['post_title']['enabled'] );

	}

	/**
	 * @return weighting sub-feature
	 */
	public function get_weighting_feature() {
		$search = ElasticPress\Features::factory()->get_registered_feature( 'search' );

		return $search->weighting;
	}

}
