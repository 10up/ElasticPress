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

		set_current_screen( 'front' );
	}

	/**
	 * Clean up after each test. Reset our mocks
	 *
	 * @since 3.6.0
	 */
	public function tear_down() {
		parent::tear_down();

		global $hook_suffix;
		$hook_suffix = 'sites.php';

		set_current_screen();

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

		$this->assertStringContainsString( 'Improve comment search relevancy and query performance.', $output );
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

		$this->assertStringContainsString( 'This feature will empower your website to overcome traditional WordPress comment search and query limitations that can present themselves at scale.', $output );
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

		$query = new WP_Comment_Query(
			[
				'ep_integrate' => false,
			]
		);

		$this->assertFalse( $this->get_feature()->integrate_search_queries( true, $query ) );

		$query = new WP_Comment_Query(
			[
				'ep_integrate' => 0,
			]
		);

		$this->assertFalse( $this->get_feature()->integrate_search_queries( true, $query ) );

		$query = new WP_Comment_Query(
			[
				'ep_integrate' => 'false',
			]
		);

		$this->assertFalse( $this->get_feature()->integrate_search_queries( true, $query ) );

		$query = new WP_Comment_Query(
			[
				'search' => 'blog',
			]
		);

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

		$this->assertEquals( 1, $status->code );
	}

	/**
	 * Test the `is_visible` method
	 *
	 * @since 4.5.0
	 * @group comments
	 */
	public function testIsVisible() {
		$this->assertTrue( $this->get_feature()->is_visible() );

		$change_visibility = function ( $is_visible, $feature_slug, $feature ) {
			$this->assertTrue( $is_visible );
			$this->assertSame( 'comments', $feature_slug );
			$this->assertInstanceOf( '\ElasticPress\Feature\Comments\Comments', $feature );
			return false;
		};
		add_filter( 'ep_feature_is_visible', $change_visibility, 10, 3 );

		$this->assertFalse( $this->get_feature()->is_visible() );
	}

	/**
	 * Test the `is_available` method
	 *
	 * @since 4.5.0
	 * @group comments
	 */
	public function testIsAvailable() {
		$this->assertTrue( $this->get_feature()->is_available() );

		$change_availability = function ( $is_available, $feature_slug, $feature ) {
			$this->assertTrue( $is_available );
			$this->assertSame( 'comments', $feature_slug );
			$this->assertInstanceOf( '\ElasticPress\Feature\Comments\Comments', $feature );
			return false;
		};
		add_filter( 'ep_feature_is_available', $change_availability, 10, 3 );

		$this->assertFalse( $this->get_feature()->is_available() );
	}
}
