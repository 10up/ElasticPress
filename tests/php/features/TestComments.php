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
	 * @since 3.6.0
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
	 * @since 3.6.0
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

	/**
	 * Get Comment feature
	 *
	 * @since  3.6.0
	 * @return ElasticPress\Feature\Comments
	 */
	protected function get_feature() {
		return ElasticPress\Features::factory()->get_registered_feature( 'comments' );
	}

	/**
	 * Test Comment Feature slug and title
	 *
	 * @since  3.6.0
	 * @group comments
	 */
    public function testConstruct() {
        $instance = new ElasticPress\Feature\Comments\Comments();

        $this->assertEquals( 'comments', $instance->slug );
        $this->assertEquals( 'Comments', $instance->title );
    }

	/**
	 * Test Comment Feature box summary
	 *
	 * @since  3.6.0
	 * @group comments
	 */
    public function testBoxSummary() {
		ob_start();
		$this->get_feature()->output_feature_box_summary();
        $output = ob_get_clean();

		$this->assertContains( 'Improve comment search relevancy and query performance.', $output );
    }

	/**
	 * Test Comment Feature box long text
	 *
	 * @since  3.6.0
	 * @group comments
	 */
    public function testBoxLong() {
		ob_start();
		$this->get_feature()->output_feature_box_long();
        $output = ob_get_clean();

		$this->assertContains( 'This feature will empower your website to overcome traditional WordPress comment search and query limitations that can present themselves at scale.', $output );
	}

	/**
	 * Test enable integration
	 *
	 * @since  3.6.0
	 * @group comments
	 */
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

	/**
	 * Test Comment Feature status
	 *
	 * @since  3.6.0
	 * @group comments
	 */
    public function testRequirementsStatus() {
        $status = $this->get_feature()->requirements_status();

        $this->assertAttributeEquals( 1, 'code', $status );
    }
}
