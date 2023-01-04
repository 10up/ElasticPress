<?php
/**
 * Test term indexable functionality
 *
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;

class TestTermMultisite extends BaseTestCase {


	/**
	 * Setup each test.
	 *
	 * @since 4.4.0
	 */
	public function set_up() {
		if ( ! is_multisite() ) {
			return;
		}

		global $wpdb;
		parent::set_up();
		$wpdb->suppress_errors();

		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( $admin_id );

		ElasticPress\Features::factory()->activate_feature( 'terms' );
		ElasticPress\Features::factory()->setup_features();

		ElasticPress\Elasticsearch::factory()->delete_all_indices();

		ElasticPress\Indexables::factory()->get( 'term' )->put_mapping();

		// Need to call this since it's hooked to init.
		ElasticPress\Features::factory()->get_registered_feature( 'terms' )->search_setup();

		$this->factory->blog->create_many( 2, array( 'user_id' => $admin_id ) );

		$sites   = ElasticPress\Utils\get_sites();
		$indexes = array();

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			ElasticPress\Indexables::factory()->get( 'term' )->put_mapping();
			$indexes[] = ElasticPress\Indexables::factory()->get( 'term' )->get_index_name();

			restore_current_blog();
		}

		ElasticPress\Indexables::factory()->get( 'term' )->delete_network_alias();
		ElasticPress\Indexables::factory()->get( 'term' )->create_network_alias( $indexes );

		wp_set_current_user( $admin_id );

	}

	/**
	 * Clean up after each test. Reset our mocks
	 *
	 * @since 4.4.0
	 */
	public function tear_down() {
		if ( ! is_multisite() ) {
			return;
		}

		parent::tear_down();

		ElasticPress\Indexables::factory()->get( 'term' )->delete_network_alias();
	}

	/**
	 * Test WP Term Query returns the data from all the sites.
	 *
	 * @since 4.4.0
	 */
	public function testTermQueryForAllSites() {

		$sites = ElasticPress\Utils\get_sites();
		if ( ! is_multisite() ) {
			$this->assertEmpty( $sites );
			return;
		}

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			$this->ep_factory->category->create();
			$this->ep_factory->category->create();
			$this->ep_factory->category->create();
			$this->ep_factory->category->create();

			ElasticPress\Elasticsearch::factory()->refresh_indices();
			restore_current_blog();
		}

		$args  = array(
			'taxonomy'     => 'category',
			'ep_integrate' => true,
			'site__in'     => 'all',
			'hide_empty'   => false,
		);
		$query = new \WP_Term_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 12, count( $query->get_terms() ) );
	}

	/**
	 * Test WP Term Query returns the data from the selected sites.
	 *
	 * @since 4.4.0
	 */
	public function testTermQueryForSitesSubset() {

		$sites = ElasticPress\Utils\get_sites();
		if ( ! is_multisite() ) {
			$this->assertEmpty( $sites );
			return;
		}

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			$this->ep_factory->category->create();
			$this->ep_factory->category->create();
			$this->ep_factory->category->create();
			$this->ep_factory->category->create();

			ElasticPress\Elasticsearch::factory()->refresh_indices();
			restore_current_blog();
		}

		$args  = array(
			'taxonomy'     => 'category',
			'ep_integrate' => true,
			'site__in'     => array( $sites[0]['blog_id'], $sites[1]['blog_id'] ),
			'hide_empty'   => false,
		);
		$query = new \WP_Term_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 8, count( $query->get_terms() ) );

	}

	/**
	 * Test WP Term Query returns the search data from all the sites.
	 *
	 * @since 4.4.0
	 */
	public function testTermQuerySearchForAllSites() {

		$sites = ElasticPress\Utils\get_sites();

		if ( ! is_multisite() ) {
			$this->assertEmpty( $sites );
			return;
		}

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			$this->ep_factory->category->create(
				array(
					'slug'        => 'apple',
					'name'        => 'Big Apple ' . $site['blog_id'],
					'description' => 'The apple fruit term',
				)
			);
			$this->ep_factory->category->create();
			$this->ep_factory->category->create();
			$this->ep_factory->category->create();

			ElasticPress\Elasticsearch::factory()->refresh_indices();
			restore_current_blog();
		}

		$args  = array(
			'search'     => 'apple',
			'site__in'   => 'all',
			'taxonomy'   => 'category',
			'hide_empty' => false,
		);
		$query = new \WP_Term_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 3, count( $query->get_terms() ) );
	}

	/**
	 * Test WP Term Query returns the search data from the selected sites.
	 *
	 * @since 4.4.0
	 */
	public function testTermQuerySearchForSitesSubset() {

		$sites = ElasticPress\Utils\get_sites();

		if ( ! is_multisite() ) {
			$this->assertEmpty( $sites );
			return;
		}

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			$this->ep_factory->category->create(
				array(
					'slug'        => 'apple',
					'name'        => 'Big Apple ' . $site['blog_id'],
					'description' => 'The apple fruit term',
				)
			);
			$this->ep_factory->category->create();
			$this->ep_factory->category->create();
			$this->ep_factory->category->create();

			ElasticPress\Elasticsearch::factory()->refresh_indices();
			restore_current_blog();
		}

		$args  = array(
			'search'     => 'apple',
			'site__in'   => array( $sites[0]['blog_id'], $sites[1]['blog_id'] ),
			'taxonomy'   => 'category',
			'hide_empty' => false,
		);
		$query = new \WP_Term_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 2, count( $query->get_terms() ) );
	}

	/**
	 * Test WP Term Query returns the data from all the sites except one.
	 *
	 * @since 4.4.0
	 */
	public function testTermQueryForAllSitesExceptOne() {

		$sites = ElasticPress\Utils\get_sites();

		if ( ! is_multisite() ) {
			$this->assertEmpty( $sites );
			return;
		}

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			$this->ep_factory->category->create();
			$this->ep_factory->category->create();
			$this->ep_factory->category->create();
			$this->ep_factory->category->create();

			ElasticPress\Elasticsearch::factory()->refresh_indices();
			restore_current_blog();
		}

		$args  = array(
			'site__not_in' => array( $sites[0]['blog_id'] ),
			'taxonomy'     => 'category',
			'hide_empty'   => false,
			'ep_integrate' => true,
		);
		$query = new \WP_Term_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 8, count( $query->get_terms() ) );

	}

	/**
	 * Test WP Term Query returns the search data from all the sites except one.
	 *
	 * @since 4.4.0
	 */
	public function testTermQuerySearchForAllSitesExceptOne() {

		$sites = ElasticPress\Utils\get_sites();

		if ( ! is_multisite() ) {
			$this->assertEmpty( $sites );
			return;
		}

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			$this->ep_factory->category->create(
				array(
					'slug'        => 'apple',
					'name'        => 'Big Apple ' . $site['blog_id'],
					'description' => 'The apple fruit term',
				)
			);
			$this->ep_factory->category->create();
			$this->ep_factory->category->create();
			$this->ep_factory->category->create();

			ElasticPress\Elasticsearch::factory()->refresh_indices();
			restore_current_blog();
		}

		$args  = array(
			'search'       => 'apple',
			'site__not_in' => array( $sites[1]['blog_id'] ),
			'taxonomy'     => 'category',
			'hide_empty'   => false,
		);
		$query = new \WP_Term_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 2, count( $query->get_terms() ) );
	}

	/**
	 * Test WP Term Query with the deprecated `sites` param
	 *
	 * @since 4.4.0
	 * @expectedDeprecated maybe_filter_query
	 */
	public function testTermQuerySearchWithDeprecatedSitesParam() {

		$sites = ElasticPress\Utils\get_sites();

		if ( ! is_multisite() ) {
			$this->assertEmpty( $sites );
			return;
		}

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			$this->ep_factory->category->create();
			$this->ep_factory->category->create();
			$this->ep_factory->category->create();
			$this->ep_factory->category->create();

			ElasticPress\Elasticsearch::factory()->refresh_indices();
			restore_current_blog();
		}

		$args  = array(
			'sites'        => 'all',
			'taxonomy'     => 'category',
			'hide_empty'   => false,
			'ep_integrate' => true,
		);
		$query = new \WP_Term_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 12, count( $query->get_terms() ) );

		// test for only one site.
		$args  = array(
			'sites'        => $sites[0]['blog_id'],
			'taxonomy'     => 'category',
			'hide_empty'   => false,
			'ep_integrate' => true,
		);
		$query = new \WP_Term_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 4, count( $query->get_terms() ) );

	}

	/**
	 * Test WP Term Query with the deprecated `sites` param and with value `current`
	 *
	 * @since 4.4.1
	 * @expectedDeprecated maybe_filter_query
	 */
	public function testTermQuerySearchWithDeprecatedSitesParamAndValueCurrent() {

		$sites = ElasticPress\Utils\get_sites();

		if ( ! is_multisite() ) {
			$this->assertEmpty( $sites );
			return;
		}

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			$this->ep_factory->category->create_many( 4 );

			ElasticPress\Elasticsearch::factory()->refresh_indices();
			restore_current_blog();
		}

		// switch the site.
		switch_to_blog( $sites[1]['blog_id'] );

		// test for only current site.
		$args  = array(
			'sites'        => 'current',
			'taxonomy'     => 'category',
			'hide_empty'   => false,
			'ep_integrate' => true,
		);
		$query = new \WP_Term_Query( $args );
		$terms = $query->get_terms();

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 4, count( $terms ) );

		foreach ( $terms as $term ) {
			$this->assertEquals( $sites[1]['blog_id'], $term->site_id );
		}
	}

	/**
	 * Test WP Term Query with the `site__in` param and with value `current`
	 *
	 * @since 4.4.1
	 */
	public function testTermQuerySearchWithSiteInParamAndValueCurrent() {

		$sites = ElasticPress\Utils\get_sites();

		if ( ! is_multisite() ) {
			$this->assertEmpty( $sites );
			return;
		}

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			$this->ep_factory->category->create_many( 4 );

			ElasticPress\Elasticsearch::factory()->refresh_indices();
			restore_current_blog();
		}

		// switch the site.
		switch_to_blog( $sites[1]['blog_id'] );

		// test for only current site.
		$args  = array(
			'site__in'     => 'current',
			'taxonomy'     => 'category',
			'hide_empty'   => false,
			'ep_integrate' => true,
		);
		$query = new \WP_Term_Query( $args );
		$terms = $query->get_terms();

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 4, count( $terms ) );

		foreach ( $terms as $term ) {
			$this->assertEquals( $sites[1]['blog_id'], $term->site_id );
		}
	}
}
