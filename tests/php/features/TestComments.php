<?php
/**
 * Test document feature
 *
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;
use WP_Comment_Query;

/**
 * Document test class
 */
class TestComments extends BaseTestCase {

	/**
	 * Setup each test.
	 *
	 * @since 2.3
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

		set_current_screen( 'front' );

		delete_option( 'ep_active_features' );
	}

	/**
	 * Clean up after each test. Reset our mocks
	 *
	 * @since 2.3
	 */
	public function tearDown() {
		parent::tearDown();

		global $hook_suffix;
		$hook_suffix = 'sites.php';

		set_current_screen();

		// make sure no one attached to this
		remove_filter( 'ep_sync_terms_allow_hierarchy', array( $this, 'ep_allow_multiple_level_terms_sync' ), 100 );
		$this->fired_actions = array();
    }

	protected function get_feature() {
		return ElasticPress\Features::factory()->get_registered_feature( 'comments' );
	}

    public function testConstruct() {
        $instance = new ElasticPress\Feature\Comments\Comments();

        $this->assertEquals( 'comments', $instance->slug );
        $this->assertEquals( 'Comments', $instance->title );
    }

    public function testBoxSummary() {
		ob_start();
		$this->get_feature()->output_feature_box_summary();
        $output = ob_get_clean();

		$this->assertContains( 'Improve comment search relevancy and query performance.', $output );
    }

    public function testBoxLong() {
		ob_start();
		$this->get_feature()->output_feature_box_long();
        $output = ob_get_clean();

		$this->assertContains( 'This feature will empower your website to overcome traditional WordPress comment search and query limitations that can present themselves at scale.', $output );
	}

	public function testIntegrateSearchQueries() {
		$this->assertTrue( $this->get_feature()->integrate_search_queries( true, null ) );
		$this->assertFalse( $this->get_feature()->integrate_search_queries( false, null ) );

		$query = new WP_Comment_Query( [
			'ep_integrate' => false
		] );

		$this->assertFalse( $this->get_feature()->integrate_search_queries( true, $query ) );

		$query = new WP_Comment_Query( [
			'search' => 'blog'
		] );

		$this->assertTrue( $this->get_feature()->integrate_search_queries( false, $query ) );
	}

    public function testRequirementsStatus() {
        $status = $this->get_feature()->requirements_status();

        $this->assertAttributeEquals( 1, 'code', $status );
    }
}
