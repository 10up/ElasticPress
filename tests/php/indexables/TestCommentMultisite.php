<?php
/**
 * Test comment indexable functionality
 *
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;

class TestCommentMultisite extends BaseTestCase {

	/**
	 * Setup each test.
	 *
	 * @since 4.4.0
	 */
	public function setUp() {
		if ( ! is_multisite() ) {
			return;
		}

		global $wpdb;
		parent::setUp();
		$wpdb->suppress_errors();

		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( $admin_id );

		ElasticPress\Features::factory()->activate_feature( 'comments' );
		ElasticPress\Features::factory()->setup_features();

		ElasticPress\Elasticsearch::factory()->delete_all_indices();

		ElasticPress\Indexables::factory()->get( 'comment' )->put_mapping();

		// Need to call this since it's hooked to init.
		ElasticPress\Features::factory()->get_registered_feature( 'comments' )->search_setup();

		$this->factory->blog->create_many( 2, array( 'user_id' => $admin_id ) );

		$sites   = ElasticPress\Utils\get_sites();
		$indexes = array();

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			ElasticPress\Indexables::factory()->get( 'comment' )->put_mapping();
			$indexes[] = ElasticPress\Indexables::factory()->get( 'comment' )->get_index_name();

			restore_current_blog();
		}

		ElasticPress\Indexables::factory()->get( 'comment' )->delete_network_alias();
		ElasticPress\Indexables::factory()->get( 'comment' )->create_network_alias( $indexes );

		wp_set_current_user( $admin_id );

	}

	/**
	 * Clean up after each test. Reset our mocks
	 *
	 * @since 4.4.0
	 */
	public function tearDown() {
		if ( ! is_multisite() ) {
			return;
		}

		parent::tearDown();
		ElasticPress\Indexables::factory()->get( 'comment' )->delete_network_alias();
	}

	/**
	 * Test Comment Query return comments from all sites.
	 *
	 * @since 4.4.0
	 */
	public function testCommentQueryForAllSites() {

		$sites = ElasticPress\Utils\get_sites();
		if ( ! is_multisite() ) {
			$this->assertEmpty( $sites );
			return;
		}

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			$post_id = Functions\create_and_sync_post();

			$this->ep_factory->comment->create( array( 'comment_post_ID' => $post_id ) );
			$this->ep_factory->comment->create( array( 'comment_post_ID' => $post_id ) );
			$this->ep_factory->comment->create( array( 'comment_post_ID' => $post_id ) );

			ElasticPress\Elasticsearch::factory()->refresh_indices();
			restore_current_blog();
		}

		$query = new \WP_Comment_Query( array(
			'ep_integrate' => true,
			'site__in' => 'all'
		) );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 9, count( $query->get_comments() ) );
	}


	/**
	 * Test Comment Query returns comments from sites subset.
	 *
	 * @since 4.4.0
	 */
	public function testCommentQueryForSitesSubset() {

		$sites = ElasticPress\Utils\get_sites();
		if ( ! is_multisite() ) {
			$this->assertEmpty( $sites );
			return;
		}

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			$post_id = Functions\create_and_sync_post();

			$this->ep_factory->comment->create( array( 'comment_post_ID' => $post_id ) );
			$this->ep_factory->comment->create( array( 'comment_post_ID' => $post_id ) );
			$this->ep_factory->comment->create( array( 'comment_post_ID' => $post_id ) );

			ElasticPress\Elasticsearch::factory()->refresh_indices();
			restore_current_blog();
		}

		$query = new \WP_Comment_Query( array(
			'ep_integrate' => true,
			'site__in' => [ $sites[0]['blog_id'], $sites[2]['blog_id'] ]
		) );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 6, count( $query->get_comments() ) );
	}

	/**
	 * Test Comment Query return comments from all sites except one.
	 *
	 * @since 4.4.0
	 */
	public function testCommentQueryForSitesExceptOne() {

		$sites = ElasticPress\Utils\get_sites();
		if ( ! is_multisite() ) {
			$this->assertEmpty( $sites );
			return;
		}

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			$post_id = Functions\create_and_sync_post();

			$this->ep_factory->comment->create( array( 'comment_post_ID' => $post_id ) );
			$this->ep_factory->comment->create( array( 'comment_post_ID' => $post_id ) );
			$this->ep_factory->comment->create( array( 'comment_post_ID' => $post_id ) );

			ElasticPress\Elasticsearch::factory()->refresh_indices();
			restore_current_blog();
		}

		$query = new \WP_Comment_Query( array(
			'ep_integrate' => true,
			'site__not_in' => [ $sites[2]['blog_id'] ]
		) );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 6, count( $query->get_comments() ) );
	}

	/**
	 * Test Comment Query search returns result from all sites.
	 *
	 * @since 4.4.0
	 */
	public function testCommentQuerySearchForAllSites() {

		$sites = ElasticPress\Utils\get_sites();
		if ( ! is_multisite() ) {
			$this->assertEmpty( $sites );
			return;
		}

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			$post_id = Functions\create_and_sync_post();

			$this->ep_factory->comment->create( array( 'comment_post_ID' => $post_id ) );
			$this->ep_factory->comment->create( array( 'comment_post_ID' => $post_id ) );
			$this->ep_factory->comment->create( array( 'comment_post_ID' => $post_id, 'comment_content' => 'Hello World' ) );

			ElasticPress\Elasticsearch::factory()->refresh_indices();
			restore_current_blog();
		}

		$query = new \WP_Comment_Query( array(
			'search' => 'Hello World',
			'site__in' => 'all'
		) );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 3, count( $query->get_comments() ) );
	}

	/**
	 * Test Comment Query with the deprecated `sites` param
	 *
	 * @since 4.4.0
	 * @expectedDeprecated maybe_filter_query
	 */
	public function testCommentQueryWithDeprecatedSitesParam() {

		$sites = ElasticPress\Utils\get_sites();
		if ( ! is_multisite() ) {
			$this->assertEmpty( $sites );
			return;
		}

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			$post_id = Functions\create_and_sync_post();

			$this->ep_factory->comment->create( array( 'comment_post_ID' => $post_id ) );
			$this->ep_factory->comment->create( array( 'comment_post_ID' => $post_id ) );
			$this->ep_factory->comment->create( array( 'comment_post_ID' => $post_id ) );

			ElasticPress\Elasticsearch::factory()->refresh_indices();
			restore_current_blog();
		}

		$query = new \WP_Comment_Query( array(
			'ep_integrate' => true,
			'sites' => 'all'
		) );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 9, count( $query->get_comments() ) );
	}

}
