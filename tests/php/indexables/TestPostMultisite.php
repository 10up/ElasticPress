<?php
/**
 * Test post indexable in multisite context
 *
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;

/**
 * Test multisite post class
 */
class TestPostMultisite extends BaseTestCase {

	/**
	 * Setup each test.
	 *
	 * @since 0.1.0
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

		$this->factory->blog->create_many( 2, array( 'user_id' => $admin_id ) );

		$sites   = ElasticPress\Utils\get_sites();
		$indexes = array();

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			ElasticPress\Indexables::factory()->get( 'post' )->delete_index();
			ElasticPress\Indexables::factory()->get( 'post' )->put_mapping();

			$indexes[] = ElasticPress\Indexables::factory()->get( 'post' )->get_index_name();

			restore_current_blog();
		}

		ElasticPress\Indexables::factory()->get( 'post' )->delete_network_alias();
		ElasticPress\Indexables::factory()->get( 'post' )->create_network_alias( $indexes );

		wp_set_current_user( $admin_id );

		$this->setup_test_post_type();

		set_current_screen( 'front' );

		/**
		 * Most of our search test are bundled into core tests for legacy reasons
		 */
		ElasticPress\Features::factory()->activate_feature( 'search' );
		ElasticPress\Features::factory()->setup_features();

		// Need to call this since it's hooked to init
		ElasticPress\Features::factory()->get_registered_feature( 'search' )->search_setup();

		// Allow some meta fields to be indexed.
		add_filter(
			'ep_prepare_meta_allowed_keys',
			function( $allowed_metakeys ) {
				return array_merge(
					$allowed_metakeys,
					[
						'test_key',
						'test_key2',
						'test_key3',
					]
				);
			}
		);
	}

	/**
	 * Clean up after each test. Reset our mocks
	 *
	 * @since 0.1.0
	 */
	public function tear_down() {
		if ( ! is_multisite() ) {
			return;
		}

		parent::tear_down();

		$this->fired_actions = array();

		ElasticPress\Indexables::factory()->get( 'post' )->delete_network_alias();
	}

	/**
	 * Cleans up all data for a list of sites.
	 *
	 * @param  array $sites List of sites.
	 * @return void
	 */
	public function cleanUpSites( $sites ) {
		global $wpdb;

		$indexes = array();

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			ElasticPress\Indexables::factory()->get( 'post' )->delete_index();

			ElasticPress\Elasticsearch::factory()->refresh_indices();

			$sql      = "select ID from {$wpdb->posts}";
			$post_ids = $wpdb->get_col( $sql ); // phpcs:ignore

			foreach ( $post_ids as $post_id ) {
				wp_delete_post( $post_id, true );
			}

			restore_current_blog();
		}
	}

	/**
	 * Test the get_sites() function.
	 *
	 * @since 0.9
	 * @group testMultipleTests
	 */
	public function testGetSites() {
		$sites = ElasticPress\Utils\get_sites();

		if ( ! is_multisite() ) {
			$this->assertEmpty( $sites );
		} else {
			$this->assertNotEmpty( $sites );
		}

		$this->cleanUpSites( $sites );
	}

	/**
	 * Test a simple post sync
	 *
	 * @since 0.9
	 * @group testMultipleTests
	 */
	public function testPostSync() {
		$sites = ElasticPress\Utils\get_sites();

		if ( ! is_multisite() ) {
			$this->assertEmpty( $sites );
		}

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			add_action( 'ep_sync_on_transition', array( $this, 'action_sync_on_transition' ), 10, 0 );

			$post_id = $this->ep_factory->post->create();

			ElasticPress\Elasticsearch::factory()->refresh_indices();

			$this->assertTrue( ! empty( $this->fired_actions['ep_sync_on_transition'] ) );

			$post = ElasticPress\Indexables::factory()->get( 'post' )->get( $post_id );
			$this->assertTrue( ! empty( $post ) );

			$this->fired_actions = array();

			restore_current_blog();
		}

		$this->cleanUpSites( $sites );
	}

	/**
	 * Test a simple post content search
	 *
	 * @since 0.9
	 * @group testMultipleTests
	 */
	public function testWPQuerySearchContent() {
		$sites = ElasticPress\Utils\get_sites();

		if ( ! is_multisite() ) {
			$this->assertEmpty( $sites );
			return;
		}

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			$this->ep_factory->post->create( array( 'post_content' => 'findme' ) );
			$this->ep_factory->post->create();
			$this->ep_factory->post->create( array( 'post_content' => 'findme' ) );

			ElasticPress\Elasticsearch::factory()->refresh_indices();

			restore_current_blog();
		}

		$args = array(
			's'        => 'findme',
			'site__in' => 'all',
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );

		$this->assertEquals( $query->post_count, 6 );
		$this->assertEquals( $query->found_posts, 6 );

		$other_site_post_count = 0;
		$original_site_id      = get_current_blog_id();

		while ( $query->have_posts() ) {
			$query->the_post();

			global $post;

			$wp_post = get_post( get_the_ID() );

			$this->assertEquals( $post->post_title, get_the_title() );
			$this->assertEquals( $post->post_content, get_the_content() );
			$this->assertEquals( $post->post_date, $wp_post->post_date );
			$this->assertEquals( $post->post_modified, $wp_post->post_modified );
			$this->assertEquals( $post->post_date_gmt, $wp_post->post_date_gmt );
			$this->assertEquals( $post->post_modified_gmt, $wp_post->post_modified_gmt );
			$this->assertEquals( $post->post_name, $wp_post->post_name );
			$this->assertEquals( $post->post_parent, $wp_post->post_parent );
			$this->assertEquals( $post->post_excerpt, $wp_post->post_excerpt );
			$this->assertEquals( $post->site_id, get_current_blog_id() );

			if ( get_current_blog_id() !== $original_site_id ) {
				$other_site_post_count++;
			}
		}

		$this->assertEquals( 4, $other_site_post_count );

		wp_reset_postdata();

		$this->cleanUpSites( $sites );
	}

	/**
	 * Test a simple post content search on a subset of network sites
	 *
	 * @since 0.9.2
	 * @group testMultipleTests
	 */
	public function testWPQuerySearchContentSiteSubset() {
		$sites = ElasticPress\Utils\get_sites();

		if ( ! is_multisite() ) {
			$this->assertEmpty( $sites );
			return;
		}

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			$this->ep_factory->post->create( array( 'post_content' => 'findme' ) );
			$this->ep_factory->post->create();
			$this->ep_factory->post->create( array( 'post_content' => 'findme' ) );

			ElasticPress\Elasticsearch::factory()->refresh_indices();

			restore_current_blog();
		}

		$args = array(
			's'        => 'findme',
			'site__in' => array( $sites[1]['blog_id'], $sites[2]['blog_id'] ),
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( $query->post_count, 4 );
		$this->assertEquals( $query->found_posts, 4 );

		$this->cleanUpSites( $sites );
	}

	/**
	 * Test to ensure that if we pass an invalid blog_id to the 'site__in' parameter that it doesn't break the search
	 *
	 * @since 0.9.2
	 * @group testMultipleTests
	 */
	public function testInvalidSubsites() {
		$sites = ElasticPress\Utils\get_sites();

		if ( ! is_multisite() ) {
			$this->assertEmpty( $sites );
			return;
		}

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			$this->ep_factory->post->create( array( 'post_content' => 'findme' ) );
			$this->ep_factory->post->create();
			$this->ep_factory->post->create( array( 'post_content' => 'findme' ) );

			ElasticPress\Elasticsearch::factory()->refresh_indices();

			restore_current_blog();
		}

		// 200 is an invalid blog_id which we're going to pass to test
		$args = array(
			's'        => 'findme',
			'site__in' => array( $sites[1]['blog_id'], $sites[2]['blog_id'], 200 ),
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( $query->post_count, 4 );
		$this->assertEquals( $query->found_posts, 4 );

		$this->cleanUpSites( $sites );
	}

	/**
	 * Test a simple post content search on a single site on the network
	 *
	 * @since 0.9.2
	 * @group testMultipleTests
	 */
	public function testWPQuerySearchContentSingleSite() {
		$sites = ElasticPress\Utils\get_sites();

		if ( ! is_multisite() ) {
			$this->assertEmpty( $sites );
			return;
		}

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			$this->ep_factory->post->create( array( 'post_content' => 'findme' ) );
			$this->ep_factory->post->create();
			$this->ep_factory->post->create( array( 'post_content' => 'findme' ) );

			ElasticPress\Elasticsearch::factory()->refresh_indices();

			restore_current_blog();
		}

		$args = array(
			's'        => 'findme',
			'site__in' => $sites[1]['blog_id'],
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( $query->post_count, 2 );
		$this->assertEquals( $query->found_posts, 2 );

		$this->cleanUpSites( $sites );
	}

	/**
	 * Test that post data is setup correctly after switch_to_blog()
	 *
	 * @since 0.9.2
	 * @group testMultipleTests
	 */
	public function testWPQueryPostDataSetup() {
		$sites = ElasticPress\Utils\get_sites();

		if ( ! is_multisite() ) {
			$this->assertEmpty( $sites );
			return;
		}

		$old_blog_id = get_current_blog_id();

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			$this->ep_factory->post->create( array( 'post_content' => 'findme' ) );
			$this->ep_factory->post->create();
			$this->ep_factory->post->create( array( 'post_content' => 'findme' ) );

			ElasticPress\Elasticsearch::factory()->refresh_indices();

			restore_current_blog();
		}

		$args = array(
			's'        => 'findme',
			'site__in' => 'all',
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );

		while ( $query->have_posts() ) {
			$query->the_post();

			global $post;

			$wp_post = get_post( get_the_ID() );

			$this->assertEquals( get_current_blog_id(), $post->site_id );
			$this->assertEquals( get_permalink( get_the_ID() ), get_permalink() );
			$this->assertEquals( get_edit_post_link( get_the_ID() ), get_edit_post_link() );
			$this->assertEquals( get_the_date( '', get_the_ID() ), get_the_date() );
			$this->assertEquals( get_the_date( '', get_the_ID() ), get_the_date() );
			$this->assertEquals( get_the_time( '', get_the_ID() ), get_the_time() );
		}

		wp_reset_postdata();

		$this->assertEquals( get_current_blog_id(), $old_blog_id );

		$this->cleanUpSites( $sites );
	}

	/**
	 * Test a simple post title search
	 *
	 * @since 0.9
	 * @group testMultipleTests
	 */
	public function testWPQuerySearchTitle() {
		$sites = ElasticPress\Utils\get_sites();

		if ( ! is_multisite() ) {
			$this->assertEmpty( $sites );
			return;
		}

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			$this->ep_factory->post->create();
			$this->ep_factory->post->create( array( 'post_title' => 'findme' ) );

			ElasticPress\Elasticsearch::factory()->refresh_indices();

			restore_current_blog();
		}

		$args = array(
			's'        => 'findme',
			'site__in' => 'all',
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );

		$this->assertEquals( $query->post_count, 3 );
		$this->assertEquals( $query->found_posts, 3 );

		$this->cleanUpSites( $sites );
	}

	/**
	 * Test a simple post excerpt search
	 *
	 * @since 0.9
	 * @group testMultipleTests
	 */
	public function testWPQuerySearchExcerpt() {
		$sites = ElasticPress\Utils\get_sites();

		if ( ! is_multisite() ) {
			$this->assertEmpty( $sites );
			return;
		}

		$i = 0;

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			$this->ep_factory->post->create();

			if ( $i > 0 ) {
				$this->ep_factory->post->create( array( 'post_excerpt' => 'findme' ) );
			}

			ElasticPress\Elasticsearch::factory()->refresh_indices();

			restore_current_blog();

			$i++;
		}

		$args = array(
			's'        => 'findme',
			'site__in' => 'all',
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( $query->post_count, 2 );
		$this->assertEquals( $query->found_posts, 2 );

		$this->cleanUpSites( $sites );
	}

	/**
	 * Test a tax query search
	 *
	 * @since 1.0
	 * @group testMultipleTests
	 */
	public function testTaxQuery() {
		$sites = ElasticPress\Utils\get_sites();

		if ( ! is_multisite() ) {
			$this->assertEmpty( $sites );
			return;
		}

		$i = 0;

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			$this->ep_factory->post->create(
				array(
					'post_content' => 'findme',
					'tags_input'   => array(
						'one',
						'three',
					),
				)
			);

			if ( $i > 0 ) {
				$this->ep_factory->post->create(
					array(
						'post_content' => 'findme',
						'tags_input'   => array(
							'two',
							'three',
						),
					)
				);
			}

			ElasticPress\Elasticsearch::factory()->refresh_indices();

			restore_current_blog();

			$i++;
		}

		$args = array(
			's'         => 'findme',
			'site__in'  => 'all',
			'tax_query' => array(
				array(
					'taxonomy' => 'post_tag',
					'terms'    => array( 'two' ),
					'field'    => 'slug',
				),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( $query->post_count, 2 );
		$this->assertEquals( $query->found_posts, 2 );

		$this->cleanUpSites( $sites );
	}

	/**
	 * Test a post type query search for pages
	 *
	 * @since 1.3
	 * @group testMultipleTests
	 */
	public function testPostTypeSearchQueryPage() {
		$sites = ElasticPress\Utils\get_sites();

		if ( ! is_multisite() ) {
			$this->assertEmpty( $sites );
			return;
		}

		$i = 0;

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			$this->ep_factory->post->create( array( 'post_content' => 'findme' ) );

			if ( $i > 0 ) {
				$this->ep_factory->post->create(
					array(
						'post_content' => 'findme',
						'post_type'    => 'page',
					)
				);
			}

			ElasticPress\Elasticsearch::factory()->refresh_indices();

			restore_current_blog();

			$i++;
		}

		$args = array(
			's'         => 'findme',
			'site__in'  => 'all',
			'post_type' => 'page',
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( $query->post_count, 2 );
		$this->assertEquals( $query->found_posts, 2 );

		$this->cleanUpSites( $sites );
	}

	/**
	 * Test a post type query search for posts
	 *
	 * @since 1.3
	 * @group testMultipleTests
	 */
	public function testPostTypeSearchQueryPost() {
		$sites = ElasticPress\Utils\get_sites();

		if ( ! is_multisite() ) {
			$this->assertEmpty( $sites );
			return;
		}

		$i = 0;

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			$this->ep_factory->post->create(
				array(
					'post_content' => 'findme',
					'post_type'    => 'page',
				)
			);

			if ( $i > 0 ) {
				$this->ep_factory->post->create( array( 'post_content' => 'findme' ) );
			}

			ElasticPress\Elasticsearch::factory()->refresh_indices();

			restore_current_blog();

			$i++;
		}

		$args = array(
			's'         => 'findme',
			'site__in'  => 'all',
			'post_type' => 'post',
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( $query->post_count, 2 );
		$this->assertEquals( $query->found_posts, 2 );

		$this->cleanUpSites( $sites );
	}

	/**
	 * Test a post type query search where no post type is specified
	 *
	 * @since 1.3
	 * @group testMultipleTests
	 */
	public function testNoPostTypeSearchQuery() {
		$sites = ElasticPress\Utils\get_sites();

		if ( ! is_multisite() ) {
			$this->assertEmpty( $sites );
			return;
		}

		$i = 0;

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			$this->ep_factory->post->create(
				array(
					'post_content' => 'findme',
					'post_type'    => 'page',
				)
			);

			if ( $i > 0 ) {
				$this->ep_factory->post->create( array( 'post_content' => 'findme' ) );
			}

			ElasticPress\Elasticsearch::factory()->refresh_indices();

			restore_current_blog();

			$i++;
		}

		$args = array(
			's'        => 'findme',
			'site__in' => 'all',
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( $query->post_count, 5 );
		$this->assertEquals( $query->found_posts, 5 );

		$this->cleanUpSites( $sites );
	}

	/**
	 * Test a post type query non-search where no post type is specified. Defaults to `post` post type
	 *
	 * @since 1.3
	 * @group testMultipleTests
	 */
	public function testNoPostTypeNoSearchQuery() {
		$sites = ElasticPress\Utils\get_sites();

		if ( ! is_multisite() ) {
			$this->assertEmpty( $sites );
			return;
		}

		$i = 0;

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			$this->ep_factory->post->create(
				array(
					'post_content' => 'findme',
					'post_type'    => 'page',
				)
			);

			if ( $i > 0 ) {
				$this->ep_factory->post->create( array( 'post_content' => 'findme' ) );
			}

			ElasticPress\Elasticsearch::factory()->refresh_indices();

			restore_current_blog();

			$i++;
		}

		$args = array(
			'ep_integrate' => true,
			'site__in'     => 'all',
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( $query->post_count, 2 );
		$this->assertEquals( $query->found_posts, 2 );

		$this->cleanUpSites( $sites );
	}

	/**
	 * Test an author ID query
	 *
	 * @since 1.0
	 * @group testMultipleTests
	 */
	public function testAuthorIDQuery() {
		$sites = ElasticPress\Utils\get_sites();

		if ( ! is_multisite() ) {
			$this->assertEmpty( $sites );
			return;
		}

		$i = 0;

		$user_id = $this->factory->user->create(
			array(
				'user_login' => 'john',
				'role'       => 'administrator',
			)
		);

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			$this->ep_factory->post->create( array( 'post_content' => 'findme' ) );

			if ( $i > 0 ) {
				$this->ep_factory->post->create(
					array(
						'post_content' => 'findme',
						'post_author'  => $user_id,
					)
				);
			}

			ElasticPress\Elasticsearch::factory()->refresh_indices();

			restore_current_blog();

			$i++;
		}

		$args = array(
			's'        => 'findme',
			'site__in' => 'all',
			'author'   => $user_id,
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( $query->post_count, 2 );
		$this->assertEquals( $query->found_posts, 2 );

		$this->cleanUpSites( $sites );
	}

	/**
	 * Test an author name query
	 *
	 * @since 1.0
	 * @group testMultipleTests
	 */
	public function testAuthorNameQuery() {
		$sites = ElasticPress\Utils\get_sites();

		if ( ! is_multisite() ) {
			$this->assertEmpty( $sites );
			return;
		}

		$i = 0;

		$user_id = $this->factory->user->create(
			array(
				'user_login' => 'john',
				'role'       => 'administrator',
			)
		);

		$posts_created = 0;

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			$this->ep_factory->post->create( array( 'post_content' => 'findme' ) );

			if ( $i > 0 ) {
				$this->ep_factory->post->create(
					array(
						'post_content' => 'findme',
						'post_author'  => $user_id,
					)
				);

				$posts_created++;
			}

			ElasticPress\Elasticsearch::factory()->refresh_indices();

			restore_current_blog();

			$i++;
		}

		$args = array(
			's'           => 'findme',
			'site__in'    => 'all',
			'author_name' => 'john',
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertSame( 2, $query->post_count );
		$this->assertSame( 2, $query->found_posts );

		$this->cleanUpSites( $sites );
	}

	/**
	 * Test a fuzzy search on meta
	 *
	 * @since 1.0
	 * @group testMultipleTests
	 */
	public function testSearchMetaQuery() {
		$sites = ElasticPress\Utils\get_sites();

		if ( ! is_multisite() ) {
			$this->assertEmpty( $sites );
			return;
		}

		$i = 0;

		$post_ids = [];

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			$post_ids[] = $this->ep_factory->post->create( array( 'post_content' => 'post content' ) );

			if ( $i > 0 ) {
				$post_ids[] = $this->ep_factory->post->create(
					array(
						'post_content' => 'post content',
						'meta_input'   => array( 'test_key' => 'findme' ),
					)
				);
			}

			ElasticPress\Elasticsearch::factory()->refresh_indices();

			restore_current_blog();

			$i++;
		}

		$args = array(
			's'             => 'findme',
			'site__in'      => 'all',
			'search_fields' => array(
				'post_title',
				'post_excerpt',
				'post_content',
				'meta' => array( 'test_key' ),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertSame( 2, $query->post_count );
		$this->assertSame( 2, $query->found_posts );

		// Cleanup.
		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			foreach ( $post_ids as $post_id ) {
				wp_delete_post( $post_id, true );
			}

			ElasticPress\Elasticsearch::factory()->refresh_indices();

			restore_current_blog();
		}

		$this->cleanUpSites( $sites );
	}

	/**
	 * Test a search with a filter on meta
	 *
	 * @since 1.3
	 * @group testMultipleTests
	 */
	public function testFilterMetaQuery() {
		$sites = ElasticPress\Utils\get_sites();

		if ( ! is_multisite() ) {
			$this->assertEmpty( $sites );
			return;
		}

		$i = 0;

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			$post_id = $this->ep_factory->post->create(
				array(
					'post_content' => 'post content findme',
					'meta_input'   => array(
						'test_key'  => 'findme',
						'test_key2' => 'findme3',
					),
				)
			);

			$this->assertNotFalse( $post_id );

			if ( $i > 0 ) {
				$post_id = $this->ep_factory->post->create(
					array(
						'post_content' => 'post content findme',
						'meta_input'   => array(
							'test_key2' => 'findme',
							'test_key'  => 'value2',
							'test_key3' => 'findme',
						),
					)
				);
			}

			ElasticPress\Elasticsearch::factory()->refresh_indices();

			restore_current_blog();

			$i++;
		}

		$args = array(
			's'          => 'findme',
			'site__in'   => 'all',
			'meta_query' => array(
				array(
					'key'   => 'test_key',
					'value' => 'value2',
				),
				array(
					'key'     => 'test_key2',
					'value'   => 'findme3',
					'compare' => '!=',
				),
				array(
					'key'     => 'test_key3',
					'compare' => 'exists',
				),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertSame( 2, $query->post_count );
		$this->assertSame( 2, $query->found_posts );

		$this->cleanUpSites( $sites );
	}

	/**
	 * Test a fuzzy search on taxonomy terms
	 *
	 * @since 1.0
	 * @group testMultipleTests
	 */
	public function testSearchTaxQuery() {

		add_filter( 'ep_search_algorithm_version', array( $this, 'set_algorithm_34' ) );

		$sites = ElasticPress\Utils\get_sites();

		if ( ! is_multisite() ) {
			$this->assertEmpty( $sites );
			return;
		}

		$i = 0;

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			$this->ep_factory->post->create( array( 'post_content' => 'post content' ) );

			if ( $i > 0 ) {
				$this->ep_factory->post->create(
					array(
						'post_content' => 'post content',
						'tags_input'   => array( 'findme 2' ),
					)
				);
			}

			ElasticPress\Elasticsearch::factory()->refresh_indices();

			restore_current_blog();

			$i++;
		}

		$args = array(
			's'             => 'one findme two',
			'site__in'      => 'all',
			'search_fields' => array(
				'post_title',
				'post_excerpt',
				'post_content',
				'taxonomies' => array( 'post_tag' ),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertSame( 2, $query->post_count );
		$this->assertSame( 2, $query->found_posts );

		$this->cleanUpSites( $sites );
	}

	/**
	 * Test a fuzzy search on author names
	 *
	 * @since 1.0
	 * @group testMultipleTests
	 */
	public function testSearchAuthorQuery() {

		add_filter( 'ep_search_algorithm_version', array( $this, 'set_algorithm_34' ) );

		$sites = ElasticPress\Utils\get_sites();

		if ( ! is_multisite() ) {
			$this->assertEmpty( $sites );
			return;
		}

		$i = 0;

		$user_id = $this->factory->user->create(
			array(
				'user_login' => 'john',
				'role'       => 'administrator',
			)
		);

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			$this->ep_factory->post->create( array( 'post_content' => 'post content' ) );

			if ( $i > 0 ) {
				$this->ep_factory->post->create(
					array(
						'post_content' => 'post content',
						'post_author'  => $user_id,
					)
				);
			}

			ElasticPress\Elasticsearch::factory()->refresh_indices();

			restore_current_blog();

			$i++;
		}

		$args = array(
			's'             => 'john boy',
			'site__in'      => 'all',
			'search_fields' => array(
				'post_title',
				'post_excerpt',
				'post_content',
				'author_name',
			),
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertSame( 2, $query->post_count );
		$this->assertSame( 2, $query->found_posts );

		$this->cleanUpSites( $sites );
	}

	/**
	 * Test a fuzzy search on taxonomy terms
	 *
	 * @since 1.0
	 * @group testMultipleTests
	 */
	public function testAdvancedQuery() {
		$sites = ElasticPress\Utils\get_sites();

		if ( ! is_multisite() ) {
			$this->assertEmpty( $sites );
			return;
		}

		$user_id = $this->factory->user->create(
			array(
				'user_login' => 'john',
				'role'       => 'administrator',
			)
		);

		switch_to_blog( $sites[0]['blog_id'] );

		$this->ep_factory->post->create(
			array(
				'post_content' => 'post content',
				'tags_input'   => array( 'term' ),
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		restore_current_blog();

		switch_to_blog( $sites[1]['blog_id'] );

		$this->ep_factory->post->create(
			array(
				'post_content' => 'post content',
				'tags_input'   => array( 'term' ),
				'post_author'  => $user_id,
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		restore_current_blog();

		switch_to_blog( $sites[2]['blog_id'] );

		$this->ep_factory->post->create(
			array(
				'post_content' => 'post content',
				'tags_input'   => array( 'term' ),
				'post_author'  => $user_id,
				'post_type'    => 'ep_test',
				'meta_input'   => array( 'test_key' => 'findme' ),
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		restore_current_blog();

		$args = array(
			's'             => 'findme',
			'site__in'      => 'all',
			'post_type'     => 'ep_test',
			'author'        => $user_id,
			'search_fields' => array(
				'post_title',
				'post_excerpt',
				'post_content',
				'meta' => array( 'test_key' ),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );

		$this->cleanUpSites( $sites );
	}

	/**
	 * Test pagination
	 *
	 * @since 0.9
	 * @group testMultipleTests
	 */
	public function testPagination() {
		$sites = ElasticPress\Utils\get_sites();

		if ( ! is_multisite() ) {
			$this->assertEmpty( $sites );
			return;
		}

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			$this->ep_factory->post->create( array( 'post_title' => 'findme' ) );
			$this->ep_factory->post->create( array( 'post_title' => 'findme' ) );

			ElasticPress\Elasticsearch::factory()->refresh_indices();

			restore_current_blog();
		}

		$args = array(
			's'              => 'findme',
			'site__in'       => 'all',
			'posts_per_page' => 2,
		);

		$query = new \WP_Query( $args );

		$found_posts = array();

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 6, $query->found_posts );

		$found_posts[] = $query->posts[0]->site_id . $query->posts[0]->ID;
		$found_posts[] = $query->posts[1]->site_id . $query->posts[1]->ID;

		$args = array(
			's'              => 'findme',
			'site__in'       => 'all',
			'posts_per_page' => 2,
			'paged'          => 2,
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 6, $query->found_posts );

		$found_posts[] = $query->posts[0]->site_id . $query->posts[0]->ID;
		$found_posts[] = $query->posts[1]->site_id . $query->posts[1]->ID;

		$this->assertEquals( 4, count( array_unique( $found_posts ) ) );

		$this->cleanUpSites( $sites );
	}

	/**
	 * Test query restoration after wp_reset_postdata
	 *
	 * @since 0.9.2
	 * @group testMultipleTests
	 */
	public function testQueryRestorationResetPostData() {
		$sites = ElasticPress\Utils\get_sites();

		if ( ! is_multisite() ) {
			$this->assertEmpty( $sites );
			return;
		}

		$old_blog_id = get_current_blog_id();

		$main_post_id = $this->factory->post->create();

		query_posts( array( 'p' => $main_post_id ) );
		$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];

		$i = 0;

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			$this->ep_factory->post->create( array( 'post_title' => 'findme' ) );
			$this->ep_factory->post->create( array( 'post_title' => 'findme' ) );
			$this->ep_factory->post->create( array( 'post_title' => 'notfirstblog' ) );

			ElasticPress\Elasticsearch::factory()->refresh_indices();

			restore_current_blog();

			$i++;
		}

		$args = array(
			's'        => 'notfirstblog',
			'site__in' => 'all',
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				global $post;
				$query->the_post();
			}
		}

		wp_reset_postdata();

		$new_blog_id = get_current_blog_id();

		$this->assertEquals( $old_blog_id, $new_blog_id );

		$this->cleanUpSites( $sites );
	}

	/**
	 * Test query restoration after wp_reset_query
	 *
	 * @since 0.9.2
	 * @group testMultipleTests
	 */
	public function testQueryRestorationResetQuery() {
		$sites = ElasticPress\Utils\get_sites();

		if ( ! is_multisite() ) {
			$this->assertEmpty( $sites );
			return;
		}

		$old_blog_id = get_current_blog_id();

		$main_post_id = $this->factory->post->create();

		query_posts( array( 'p' => $main_post_id ) );
		$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];

		$i = 0;

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			$this->ep_factory->post->create( array( 'post_title' => 'findme' ) );
			$this->ep_factory->post->create( array( 'post_title' => 'findme' ) );

			if ( $i > 0 ) {
				$this->ep_factory->post->create( array( 'post_title' => 'notfirstblog' ) );
			}

			ElasticPress\Elasticsearch::factory()->refresh_indices();

			restore_current_blog();

			$i++;
		}

		$args = array(
			's'        => 'notfirstblog',
			'site__in' => 'all',
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				global $post;
				$query->the_post();
			}
		}

		wp_reset_query();

		$new_blog_id = get_current_blog_id();

		$this->assertEquals( $old_blog_id, $new_blog_id );

		$this->cleanUpSites( $sites );
	}

	/**
	 * Test query stack with nested queries
	 *
	 * @since 1.2
	 * @group testMultipleTests
	 */
	public function testQueryStack() {
		$sites = ElasticPress\Utils\get_sites();

		if ( ! is_multisite() ) {
			$this->assertEmpty( $sites );
			return;
		}

		$old_blog_id = get_current_blog_id();

		$main_post_id = $this->factory->post->create();

		query_posts( array( 'p' => $main_post_id ) );
		$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];

		$i = 0;

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			$this->ep_factory->post->create( array( 'post_title' => 'findme' ) );
			$this->ep_factory->post->create( array( 'post_title' => 'findme' ) );

			if ( $i > 0 ) {
				$this->ep_factory->post->create( array( 'post_title' => 'notfirstblog' ) );
			} elseif ( 0 === $i ) {
				$this->ep_factory->post->create( array( 'post_title' => 'firstblog' ) );
			}

			ElasticPress\Elasticsearch::factory()->refresh_indices();

			restore_current_blog();

			$i++;
		}

		$args = array(
			's'        => 'notfirstblog',
			'site__in' => (int) $sites[1]['blog_id'],
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();

				$blog_id = get_current_blog_id();

				$query_two = new \WP_Query();

				if ( $query_two->have_posts() ) {
					while ( $query_two->have_posts() ) {
						global $post;
						$query_two->the_post();

						$this->assertTrue( empty( $post->site_id ) );
					}
				}

				$this->assertEquals( get_current_blog_id(), $blog_id );
			}
		}

		wp_reset_query();

		$new_blog_id = get_current_blog_id();

		$this->assertEquals( $old_blog_id, $new_blog_id );

		$this->cleanUpSites( $sites );
	}

	/**
	 * Test filter for skipping query integration
	 *
	 * @since 1.2
	 * @group testMultipleTests
	 */
	public function testQueryIntegrationSkip() {
		$sites = ElasticPress\Utils\get_sites();

		if ( ! is_multisite() ) {
			$this->assertEmpty( $sites );
			return;
		}

		$main_post_id = $this->factory->post->create();

		query_posts( array( 'p' => $main_post_id ) );
		$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];

		$i = 0;

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			$this->ep_factory->post->create( array( 'post_title' => 'findme' ) );
			$this->ep_factory->post->create( array( 'post_title' => 'findme' ) );

			if ( $i > 0 ) {
				$this->ep_factory->post->create( array( 'post_title' => 'notfirstblog' ) );
			} elseif ( 0 === $i ) {
				$this->ep_factory->post->create( array( 'post_title' => 'firstblog' ) );
			}

			ElasticPress\Elasticsearch::factory()->refresh_indices();

			restore_current_blog();

			$i++;
		}

		add_filter( 'ep_skip_query_integration', '__return_true' );

		$args = array(
			's'     => 'notfirstblog',
			'sites' => 'all',
		);

		$query = new \WP_Query( $args );

		$this->assertNull( $query->elasticsearch_success );
		$this->assertTrue( empty( $query->posts ) );

		$this->cleanUpSites( $sites );
	}

	/**
	 * Test post object data
	 *
	 * @since 1.4
	 * @group testMultipleTests
	 */
	public function testPostObject() {
		$sites = ElasticPress\Utils\get_sites();

		if ( ! is_multisite() ) {
			$this->assertEmpty( $sites );
			return;
		}

		$user_id = $this->factory->user->create(
			array(
				'user_login' => 'john',
				'role'       => 'administrator',
			)
		);

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			$this->ep_factory->post->create(
				array(
					'post_title'   => 'findme',
					'post_author'  => $user_id,
					'post_excerpt' => 'find',
					'menu_order'   => $site['blog_id'],
				)
			);
			$this->ep_factory->post->create(
				array(
					'post_title'   => 'findme',
					'post_author'  => $user_id,
					'post_excerpt' => 'find',
					'menu_order'   => $site['blog_id'],
				)
			);

			ElasticPress\Elasticsearch::factory()->refresh_indices();

			restore_current_blog();
		}

		$args = array(
			's'              => 'findme',
			'site__in'       => 'all',
			'posts_per_page' => 10,
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 6, $query->post_count );
		$this->assertEquals( 6, $query->found_posts );

		while ( $query->have_posts() ) {
			$query->the_post();
			global $post;

			$this->assertEquals( $user_id, $post->post_author );
			$this->assertEquals( 'find', $post->post_excerpt );
			$this->assertEquals( $post->site_id, $post->menu_order );
		}
		wp_reset_postdata();

		$this->cleanUpSites( $sites );
	}

	/**
	 * Test index_exists helper function
	 *
	 * @group testMultipleTests
	 */
	public function testIndexExists() {
		$sites = ElasticPress\Utils\get_sites();

		if ( ! is_multisite() ) {
			$this->assertEmpty( $sites );
			return;
		}

		$first_site_index       = ElasticPress\Indexables::factory()->get( 'post' )->get_index_name( $sites[0]['blog_id'] );
		$index_should_exist     = ElasticPress\Elasticsearch::factory()->index_exists( $first_site_index );
		$index_should_not_exist = ElasticPress\Elasticsearch::factory()->index_exists( $first_site_index . 2 );

		$this->assertTrue( $index_should_exist );
		$this->assertFalse( $index_should_not_exist );

		$this->cleanUpSites( $sites );
	}

	/**
	 * Tests deletion of index when a blog is deleted
	 *
	 * @link https://github.com/10up/ElasticPress/issues/392
	 * @group testMultipleTests
	 */
	public function testDeactivateSite() {
		$index_count = Functions\count_indexes();

		if ( ! is_multisite() ) {
			$this->assertSame( $index_count['total_indexes'], 0 );
			$this->assertSame( $index_count['last_blog_id_with_index'], 0 );

			return;
		}

		$count_indexes = $index_count['total_indexes'];
		$last_blog_id  = $index_count['last_blog_id_with_index'];

		do_action( 'deactivate_blog', $last_blog_id );
		update_blog_status( $last_blog_id, 'deleted', '1' );

		$post_delete_count  = Functions\count_indexes();
		$post_count_indexes = $post_delete_count['total_indexes'];

		$this->assertNotEquals( $count_indexes, $post_count_indexes );
	}

	/**
	 * Tests deletion of index when a blog is marked as spam
	 *
	 * @group testMultipleTests
	 * @link https://github.com/10up/ElasticPress/issues/392
	 */
	public function testSpamSite() {
		$index_count = Functions\count_indexes();

		if ( ! is_multisite() ) {
			$this->assertSame( $index_count['total_indexes'], 0 );
			$this->assertSame( $index_count['last_blog_id_with_index'], 0 );

			return;
		}

		$count_indexes = $index_count['total_indexes'];
		$last_blog_id  = $index_count['last_blog_id_with_index'];

		update_blog_status( $last_blog_id, 'spam', '1' );

		$post_delete_count  = Functions\count_indexes();
		$post_count_indexes = $post_delete_count['total_indexes'];

		$this->assertNotEquals( $count_indexes, $post_count_indexes );
	}

	/**
	 * Tests deletion of index when a blog is marked as archived
	 *
	 * @group testMultipleTests
	 * @link https://github.com/10up/ElasticPress/issues/392
	 */
	public function testArchivedSite() {
		$index_count = Functions\count_indexes();

		if ( ! is_multisite() ) {
			$this->assertSame( $index_count['total_indexes'], 0 );
			$this->assertSame( $index_count['last_blog_id_with_index'], 0 );

			return;
		}

		$count_indexes = $index_count['total_indexes'];
		$last_blog_id  = $index_count['last_blog_id_with_index'];

		update_blog_status( $last_blog_id, 'archived', '1' );

		$post_delete_count  = Functions\count_indexes();
		$post_count_indexes = $post_delete_count['total_indexes'];

		$this->assertNotEquals( $count_indexes, $post_count_indexes );
	}

	/**
	 * Tests WP Query returns the result of only those sites which are defined in `site__in` when both `site__in` and `site__not_in` are defined
	 *
	 * @since 4.4.0
	 * @group testMultipleTests
	 */
	public function testWPQueryWithSiteInAndNotSiteInParam() {

		$sites = ElasticPress\Utils\get_sites();

		if ( ! is_multisite() ) {
			$this->assertEmpty( $sites );
			return;
		}

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			$this->ep_factory->post->create( array( 'post_content' => 'findme' ) );
			$this->ep_factory->post->create();
			$this->ep_factory->post->create( array( 'post_content' => 'findme' ) );

			ElasticPress\Elasticsearch::factory()->refresh_indices();

			restore_current_blog();
		}

		$args = array(
			's'            => 'findme',
			'site__in'     => $sites[1]['blog_id'],
			'site__not_in' => $sites[1]['blog_id'],
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( $query->post_count, 2 );
		$this->assertEquals( $query->found_posts, 2 );

		$this->cleanUpSites( $sites );
	}

	/**
	 * Test a simple post content search on a subset of network sites with deprecated `sites` parameter
	 *
	 * @since 4.4.0
	 * @expectedDeprecated get_es_posts
	 * @group testMultipleTests
	 */
	public function testWPQuerySearchContentSiteSubsetWithDeprecatedSitesParam() {

		$sites = ElasticPress\Utils\get_sites();

		if ( ! is_multisite() ) {
			$this->assertEmpty( $sites );
			return;
		}

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			$this->ep_factory->post->create( array( 'post_content' => 'findme' ) );
			$this->ep_factory->post->create();
			$this->ep_factory->post->create( array( 'post_content' => 'findme' ) );

			ElasticPress\Elasticsearch::factory()->refresh_indices();

			restore_current_blog();
		}

		$args = array(
			's'     => 'findme',
			'sites' => array( $sites[1]['blog_id'], $sites[2]['blog_id'] ),
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( $query->post_count, 4 );
		$this->assertEquals( $query->found_posts, 4 );

		$this->cleanUpSites( $sites );
	}

	/**
	 * Test a simple post content search with deprecated `sites` parameter
	 *
	 * @since 4.4.0
	 * @expectedDeprecated get_es_posts
	 * @group testMultipleTests
	 */
	public function testWPQuerySearchContentWithDeprecatedSitesParam() {
		$sites = ElasticPress\Utils\get_sites();

		if ( ! is_multisite() ) {
			$this->assertEmpty( $sites );
			return;
		}

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			$this->ep_factory->post->create( array( 'post_content' => 'findme' ) );
			$this->ep_factory->post->create();
			$this->ep_factory->post->create( array( 'post_content' => 'findme' ) );

			ElasticPress\Elasticsearch::factory()->refresh_indices();

			restore_current_blog();
		}

		$args = array(
			's'     => 'findme',
			'sites' => 'all',
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );

		$this->assertEquals( $query->post_count, 6 );
		$this->assertEquals( $query->found_posts, 6 );

		$other_site_post_count = 0;
		$original_site_id      = get_current_blog_id();

		while ( $query->have_posts() ) {
			$query->the_post();

			global $post;

			$wp_post = get_post( get_the_ID() );

			$this->assertEquals( $post->post_title, get_the_title() );
			$this->assertEquals( $post->post_content, get_the_content() );
			$this->assertEquals( $post->post_date, $wp_post->post_date );
			$this->assertEquals( $post->post_modified, $wp_post->post_modified );
			$this->assertEquals( $post->post_date_gmt, $wp_post->post_date_gmt );
			$this->assertEquals( $post->post_modified_gmt, $wp_post->post_modified_gmt );
			$this->assertEquals( $post->post_name, $wp_post->post_name );
			$this->assertEquals( $post->post_parent, $wp_post->post_parent );
			$this->assertEquals( $post->post_excerpt, $wp_post->post_excerpt );
			$this->assertEquals( $post->site_id, get_current_blog_id() );

			if ( get_current_blog_id() !== $original_site_id ) {
				$other_site_post_count++;
			}
		}

		$this->assertEquals( 4, $other_site_post_count );

		wp_reset_postdata();

		$this->cleanUpSites( $sites );
	}


	/**
	 * Test a simple post content search with deprecated `sites` parameter and with value `current`
	 *
	 * @since 4.4.1
	 * @expectedDeprecated get_es_posts
	 * @group testMultipleTests
	 */
	public function testWPQuerySearchContentWithDeprecatedSitesParamWithValueCurrent() {

		$sites = ElasticPress\Utils\get_sites();

		if ( ! is_multisite() ) {
			$this->assertEmpty( $sites );
			return;
		}

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			$this->ep_factory->post->create_many( 2, array( 'post_content' => 'findme' ) );
			$this->ep_factory->post->create();

			ElasticPress\Elasticsearch::factory()->refresh_indices();

			restore_current_blog();
		}

		switch_to_blog( $sites[1]['blog_id'] );

		$args = array(
			's'     => 'findme',
			'sites' => 'current',
		);

		$query = new \WP_Query( $args );
		$posts = $query->posts;

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( $query->post_count, 2 );
		$this->assertEquals( $query->found_posts, 2 );

		foreach ( $posts as $post ) {
			$this->assertEquals( $post->site_id, $sites[1]['blog_id'] );
		}

		$this->cleanUpSites( $sites );
	}

	/**
	 * Test a simple post content search with `site__in` parameter and with value `current`.
	 *
	 * @since 4.4.1
	 * @group testMultipleTests
	 */
	public function testWPQuerySearchContentWithDeprecatedSiteInParamWithValueCurrent() {

		$sites = ElasticPress\Utils\get_sites();

		if ( ! is_multisite() ) {
			$this->assertEmpty( $sites );
			return;
		}

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			$this->ep_factory->post->create_many( 2, array( 'post_content' => 'findme' ) );
			$this->ep_factory->post->create();

			ElasticPress\Elasticsearch::factory()->refresh_indices();

			restore_current_blog();
		}

		switch_to_blog( $sites[1]['blog_id'] );

		$args = array(
			's'        => 'findme',
			'site__in' => 'current',
		);

		$query = new \WP_Query( $args );
		$posts = $query->posts;

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( $query->post_count, 2 );
		$this->assertEquals( $query->found_posts, 2 );

		foreach ( $posts as $post ) {
			$this->assertEquals( $post->site_id, $sites[1]['blog_id'] );
		}

		$this->cleanUpSites( $sites );
	}

	/**
	 * Tests WP Query returns the data from all sites except one.
	 *
	 * @since 4.4.0
	 * @group testMultipleTests
	 */
	public function testWPQueryForAllSiteExceptOne() {

		$sites = ElasticPress\Utils\get_sites();

		if ( ! is_multisite() ) {
			$this->assertEmpty( $sites );
			return;
		}

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			$this->ep_factory->post->create_many( 3 );

			ElasticPress\Elasticsearch::factory()->refresh_indices();

			restore_current_blog();
		}

		$args = array(
			'ep_integrate' => true,
			'site__not_in' => array( $sites[1]['blog_id'] ),
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 6, $query->post_count );
		$this->assertEquals( 6, $query->found_posts );
	}

	/**
	 * Tests a simple post content search returns data from all the sites except one.
	 *
	 * @since 4.4.0
	 * group testMultipleTests
	 */
	public function testWPQuerySearchContentForAllSiteExceptOne() {

		$sites = ElasticPress\Utils\get_sites();

		if ( ! is_multisite() ) {
			$this->assertEmpty( $sites );
			return;
		}

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			$this->ep_factory->post->create( array( 'post_content' => 'findme' ) );
			$this->ep_factory->post->create();
			$this->ep_factory->post->create( array( 'post_content' => 'findme' ) );

			ElasticPress\Elasticsearch::factory()->refresh_indices();

			restore_current_blog();
		}

		$args = array(
			's'            => 'findme',
			'site__not_in' => array( $sites[1]['blog_id'] ),
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 4, $query->post_count );
		$this->assertEquals( 4, $query->found_posts );
	}


}
