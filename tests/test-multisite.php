<?php

class EPTestMultisite extends EP_Test_Base {

	/**
	 * Setup each test.
	 *
	 * @since 0.1.0
	 */
	public function setUp() {
		global $wpdb;
		parent::setUp();
		$wpdb->suppress_errors();

		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( $admin_id );

		$this->factory->blog->create_many( 2, array( 'user_id' => $admin_id ) );

		$sites = ep_get_sites();
		$indexes = array();

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			ep_delete_index();
			ep_put_mapping();

			$indexes[] = ep_get_index_name();

			restore_current_blog();
		}

		ep_delete_network_alias();
		ep_create_network_alias( $indexes );

		wp_set_current_user( $admin_id );

		EP_WP_Query_Integration::factory()->setup();

		$this->setup_test_post_type();

		set_current_screen( 'front' );

		/**
		 * Most of our search test are bundled into core tests for legacy reasons
		 */
		ep_activate_feature( 'search' );
		EP_Features::factory()->setup_features();
	}

	/**
	 * Clean up after each test. Reset our mocks
	 *
	 * @since 0.1.0
	 */
	public function tearDown() {
		parent::tearDown();

		$this->fired_actions = array();

		$sites = ep_get_sites();
		$indexes = array();

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			ep_delete_index();

			restore_current_blog();
		}

		ep_delete_network_alias();
	}

	/**
	 * Test a simple post sync
	 *
	 * @since 0.9
	 * @group multisite
	 */
	public function testPostSync() {
		$sites = ep_get_sites();

		foreach( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			add_action( 'ep_sync_on_transition', array( $this, 'action_sync_on_transition' ), 10, 0 );

			$post_id = ep_create_and_sync_post();

			ep_refresh_index();

			$this->assertTrue( ! empty( $this->fired_actions['ep_sync_on_transition'] ) );

			$post = ep_get_post( $post_id );
			$this->assertTrue( ! empty( $post ) );

			$this->fired_actions = array();

			restore_current_blog();
		}
	}

	/**
	 * Test a simple post content search
	 *
	 * @since 0.9
	 * @group multisite
	 */
	public function testWPQuerySearchContent() {
		$sites = ep_get_sites();

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			ep_create_and_sync_post( array( 'post_content' => 'findme' ) );
			ep_create_and_sync_post();
			ep_create_and_sync_post( array( 'post_content' => 'findme' ) );

			ep_refresh_index();

			restore_current_blog();
		}

		$args = array(
			's' => 'findme',
			'sites' => 'all',
		);

		add_action( 'ep_wp_query_search', array( $this, 'action_wp_query_search' ), 10, 0 );

		$query = new WP_Query( $args );

		$this->assertTrue( ! empty( $this->fired_actions['ep_wp_query_search'] ) );

		$this->assertEquals( $query->post_count, 6 );
		$this->assertEquals( $query->found_posts, 6 );

		$other_site_post_count = 0;
		$original_site_id = get_current_blog_id();

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

			if ( get_current_blog_id() != $original_site_id ) {
				$other_site_post_count++;
			}
		}

		$this->assertEquals( 4, $other_site_post_count );

		wp_reset_postdata();
	}

	/**
	 * Test a simple post content search on a subset of network sites
	 *
	 * @since 0.9.2
	 * @group multisite
	 */
	public function testWPQuerySearchContentSiteSubset() {
		$sites = ep_get_sites();

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			ep_create_and_sync_post( array( 'post_content' => 'findme' ) );
			ep_create_and_sync_post();
			ep_create_and_sync_post( array( 'post_content' => 'findme' ) );

			ep_refresh_index();

			restore_current_blog();
		}

		$args = array(
			's' => 'findme',
			'sites' => array( $sites[1]['blog_id'], $sites[2]['blog_id'] ),
		);

		$query = new WP_Query( $args );

		$this->assertEquals( $query->post_count, 4 );
		$this->assertEquals( $query->found_posts, 4 );
	}

	/**
	 * Test to ensure that if we pass an invalid blog_id to the 'sites' parameter that it doesn't break the search
	 *
	 * @since 0.9.2
	 * @group multisite
	 */
	public function testInvalidSubsites() {
		$sites = ep_get_sites();

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			ep_create_and_sync_post( array( 'post_content' => 'findme' ) );
			ep_create_and_sync_post();
			ep_create_and_sync_post( array( 'post_content' => 'findme' ) );

			ep_refresh_index();

			restore_current_blog();
		}

		// 200 is an invalid blog_id which we're going to pass to test
		$args = array(
			's' => 'findme',
			'sites' => array( $sites[1]['blog_id'], $sites[2]['blog_id'], 200 ),
		);

		$query = new WP_Query( $args );

		$this->assertEquals( $query->post_count, 4 );
		$this->assertEquals( $query->found_posts, 4 );
	}

	/**
	 * Test a simple post content search on a single site on the network
	 *
	 * @since 0.9.2
	 * @group multisite
	 */
	public function testWPQuerySearchContentSingleSite() {
		$sites = ep_get_sites();

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			ep_create_and_sync_post( array( 'post_content' => 'findme' ) );
			ep_create_and_sync_post();
			ep_create_and_sync_post( array( 'post_content' => 'findme' ) );

			ep_refresh_index();

			restore_current_blog();
		}

		$args = array(
			's' => 'findme',
			'sites' => $sites[1]['blog_id'],
		);

		$query = new WP_Query( $args );

		$this->assertEquals( $query->post_count, 2 );
		$this->assertEquals( $query->found_posts, 2 );
	}

	/**
	 * Test that post data is setup correctly after switch_to_blog()
	 *
	 * @since 0.9.2
	 * @group multisite
	 */
	public function testWPQueryPostDataSetup() {
		$sites = ep_get_sites();

		$old_blog_id = get_current_blog_id();

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			ep_create_and_sync_post( array( 'post_content' => 'findme' ) );
			ep_create_and_sync_post();
			ep_create_and_sync_post( array( 'post_content' => 'findme' ) );

			ep_refresh_index();

			restore_current_blog();
		}

		$args = array(
			's' => 'findme',
			'sites' => 'all',
		);

		$query = new WP_Query( $args );

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
	}

	/**
	 * Test a simple post title search
	 *
	 * @since 0.9
	 * @group multisite
	 */
	public function testWPQuerySearchTitle() {
		$sites = ep_get_sites();

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			ep_create_and_sync_post();
			ep_create_and_sync_post( array( 'post_title' => 'findme' ) );

			ep_refresh_index();

			restore_current_blog();
		}

		$args = array(
			's' => 'findme',
			'sites' => 'all',
		);

		add_action( 'ep_wp_query_search', array( $this, 'action_wp_query_search' ), 10, 0 );

		$query = new WP_Query( $args );

		$this->assertTrue( ! empty( $this->fired_actions['ep_wp_query_search'] ) );

		$this->assertEquals( $query->post_count, 3 );
		$this->assertEquals( $query->found_posts, 3 );
	}

	/**
	 * Test a simple post excerpt search
	 *
	 * @since 0.9
	 * @group multisite
	 */
	public function testWPQuerySearchExcerpt() {
		$sites = ep_get_sites();

		$i = 0;

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			ep_create_and_sync_post();

			if ( $i > 0 ) {
				ep_create_and_sync_post( array( 'post_excerpt' => 'findme' ) );
			}

			ep_refresh_index();

			restore_current_blog();

			$i++;
		}

		$args = array(
			's' => 'findme',
			'sites' => 'all',
		);

		add_action( 'ep_wp_query_search', array( $this, 'action_wp_query_search' ), 10, 0 );

		$query = new WP_Query( $args );

		$this->assertTrue( ! empty( $this->fired_actions['ep_wp_query_search'] ) );

		$this->assertEquals( $query->post_count, 2 );
		$this->assertEquals( $query->found_posts, 2 );
	}

	/**
	 * Test a simple date param search by date and monthnum
	 *
	 * @group multisite
	 */
	public function testSimpleDateMonthNum() {
		ep_create_date_query_posts();

		$args = array(
			's' => 'findme',
			'sites' => 'all',
			'monthnum' => 12,
			'posts_per_page' => 100,
		);

		$query = new WP_Query( $args );
		$this->assertEquals( $query->post_count, 15 );
		$this->assertEquals( $query->found_posts, 15 );

		$args = array(
			's' => 'findme',
			'sites' => 'all',
			'day' => 5,
			'posts_per_page' => 100,
		);

		$query = new WP_Query( $args );
		$this->assertEquals( $query->post_count, 3 );
		$this->assertEquals( $query->found_posts, 3 );
	}

	/**
	 * Test a simple date param search by day number of week
	 *
	 * @group multisite
	 */
	public function testSimpleDateDay() {
		ep_create_date_query_posts();

		$args = array(
			's' => 'findme',
			'sites' => 'all',
			'day' => 5,
			'posts_per_page' => 100,
		);

		$query = new WP_Query( $args );
		$this->assertEquals( $query->post_count, 3 );
		$this->assertEquals( $query->found_posts, 3 );
	}

	/**
	 * Test a date query with before and after range
	 *
	 * @group multisite
	 */
	public function testDateQueryBeforeAfter() {
		ep_create_date_query_posts();

		$args = array(
			's' => 'findme',
			'sites' => 'all',
			'posts_per_page' => 100,
			'date_query' => array(
				array(
					'after'     => 'January 1st, 2012',
					'before'    => array(
						'year' => 2012,
						'day' => 2,
						'month' => 1,
						'hour' => 23,
						'minute' => 59,
						'second' => 59
					),
					'inclusive' => true,
				),
			)
		);

		$query = new WP_Query( $args );
		$this->assertEquals( $query->post_count, 6 );
		$this->assertEquals( $query->found_posts, 6 );
	}

	/**
	 * Test a date query with multiple column range comparison
	 *
	 * @group multisite
	 */
	public function testDateQueryMultiColumn() {
		ep_create_date_query_posts();

		$args = array(
			's' => 'findme',
			'sites' => 'all',
			'posts_per_page' => 100,
			'date_query' => array(
				array(
					'column' => 'post_date',
					'after' => 'January 1st 2012',
				),
				array(
					'column' => 'post_date_gmt',
					'after'  => 'January 3rd 2012 8AM',
				),
			)
		);

		$query = new WP_Query( $args );

		$this->assertEquals( $query->post_count, 12 );
		$this->assertEquals( $query->found_posts, 12 );
	}

	/**
	 * Test a date query with multiple column range comparison inclusive
	 * 
	 * @group multisite
	 */
	public function testDateQueryMultiColumnInclusive() {
		ep_create_date_query_posts();

		$args = array(
			's' => 'findme',
			'sites' => 'all',
			'posts_per_page' => 100,
			'date_query' => array(
				array(
					'column' => 'post_date',
					'before' => 'January 5th 2012 11:00PM',
				),
				array(
					'column' => 'post_date',
					'after'  => 'January 5th 2012 10:00PM',
				),
				'inclusive' => true,
			)
		);

		$query = new WP_Query( $args );
		$this->assertEquals( $query->post_count, 3 );
		$this->assertEquals( $query->found_posts, 3 );
	}


	/**
	 * Test a date query with multiple eltries
	 * 
	 * @group multisite
	 */
	public function testDateQueryWorkingHours() {
		ep_create_date_query_posts();

		$args = array(
			's' => 'findme',
			'sites'			=> 'all',
			'posts_per_page' => 100,
			'date_query' => array(
				array(
					'hour'      => 9,
					'compare'   => '>=',
				),
				array(
					'hour'      => 17,
					'compare'   => '<=',
				),
				array(
					'dayofweek' => array( 2, 6 ),
					'compare'   => 'BETWEEN',
				),
			),
		);

		$query = new WP_Query( $args );

		$this->assertEquals( $query->post_count, 15 );
		$this->assertEquals( $query->found_posts, 15 );
	}

	/**
	 * Test a date query with multiple column range comparison not inclusive
	 * 
	 * @group multisite
	 */
	public function testDateQueryMultiColumnNotInclusive() {
		ep_create_date_query_posts();

		$args = array(
			's' => 'findme',
			'sites' => 'all',
			'posts_per_page' => 100,
			'date_query' => array(
				array(
					'column' => 'post_date',
					'before' => 'January 5th 2012',
				),
				array(
					'column' => 'post_date',
					'after'  => 'January 5th 2012',
				),
				'inclusive' => false,
			)
		);

		$query = new WP_Query( $args );
		$this->assertEquals( $query->post_count, 0 );
		$this->assertEquals( $query->found_posts, 0 );
	}

	/**
	 * Test a simple date query search by year, monthnum and day of week
	 * 
	 * @group multisite
	 */
	public function testDateQuerySimple() {
		ep_create_date_query_posts();

		$args = array(
			's' => 'findme',
			'sites' => 'all',
			'posts_per_page' => 100,
			'date_query' => array(
				array(
					'year'  => 2012,
					'monthnum' => 1,
					'day'   => 1,
				)
			)
		);

		$query = new WP_Query( $args );
		$this->assertEquals( $query->post_count, 3 );
		$this->assertEquals( $query->found_posts, 3 );
	}

	/**
	 * Test a date query with BETWEEN comparison
	 *
	 * @group multisite
	 */
	public function testDateQueryBetween() {
		ep_create_date_query_posts();

		$args = array(
			's' => 'findme',
			'sites' => 'all',
			'posts_per_page' => 100,
			'date_query' => array(
				array (
					'day'	=> array( 1, 5 ),
					'compare'	=> 'BETWEEN',
				)
			)
		);

		$query = new WP_Query( $args );
		$this->assertEquals( $query->post_count, 15 );
		$this->assertEquals( $query->found_posts, 15 );
	}

	/**
	 * Test a date query with NOT BETWEEN comparison
	 *
	 * @group multisite
	 */
	public function testDateQueryNotBetween() {
		ep_create_date_query_posts();

		$args = array(
			's' => 'findme',
			'sites' => 'all',
			'posts_per_page' => 100,
			'date_query' => array(
				array (
					'day'	=> array( 1, 5 ),
					'compare'	=> 'NOT BETWEEN',
				)
			)
		);

		$query = new WP_Query( $args );
		$this->assertEquals( $query->post_count, 24 );
		$this->assertEquals( $query->found_posts, 24 );
	}

	/**
	 * Test a date query with BETWEEN comparison on 1 day range
	 *
	 * @group multisite
	 */
	public function testDateQueryShortBetween() {
		ep_create_date_query_posts();

		$args = array(
			's' => 'findme',
			'sites' => 'all',
			'posts_per_page' => 100,
			'date_query' => array(
				array (
					'day'	=> array( 5, 5 ),
					'compare'	=> 'BETWEEN',
				)
			)
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 3, $query->post_count );
		$this->assertEquals( 3, $query->found_posts );
	}

	/**
	 * Test a date query with multiple range comparisons
	 *
	 * Currently created posts don't have that many date based differences
	 * for this test
	 *
	 * @group multisite
	 */
	public function testDateQueryCompare() {
		ep_create_date_query_posts();

		$args = array(
			's' => 'findme',
			'sites' => 'all',
			'posts_per_page' => 100,
			'date_query' => array(
				array(
					'monthnum'      => 1,
					'compare'   => '<=',
				),
				array(
					'year'      => 2012,
					'compare'   => '>=',
				),
				array(
					'day' => array( 2, 5 ),
					'compare'   => 'BETWEEN',
				),
			)
		);

		$query = new WP_Query( $args );
		$this->assertEquals( $query->post_count, 12 );
		$this->assertEquals( $query->found_posts, 12 );
	}

	/**
	 * Test a date query with multiple range comparisons where before and after are
	 * structured differently. Test inclusive range.
	 * 
	 * @group multisite
	 */
	public function testDateQueryInclusiveTypeMix() {
		ep_create_date_query_posts();

		$args = array(
			's' => 'findme',
			'sites' => 'all',
			'posts_per_page' => 100,
			'date_query' => array(
				array(
					'after'     => 'January 4, 2012',
					'before'    => array(
						'year'  => 2012,
						'month' => 1,
						'day'   => 5,
						'hour'	=> 23,
						'minute' => 0,
						'second' => 0
					),
					'inclusive' => true,
				),
			)
		);

		$query = new WP_Query( $args );
		$this->assertEquals( $query->post_count, 6 );
		$this->assertEquals( $query->found_posts, 6 );
	}

	/**
	 * Test a date query with multiple range comparisons where before and after are
	 * structured differently. Test exclusive range.
	 * 
	 * @group multisite
	 */
	public function testDateQueryExclusiveTypeMix() {
		ep_create_date_query_posts();

		$args = array(
			's' => 'findme',
			'sites' => 'all',
			'posts_per_page' => 100,
			'date_query' => array(
				array(
					'after'     => 'January 4, 2012 10:00PM',
					'before'    => array(
						'year'  => 2012,
						'month' => 1,
						'day'   => 5,
					),
					'inclusive' => false,
				),
			)
		);

		$query = new WP_Query( $args );
		$this->assertEquals( $query->post_count, 0 );
		$this->assertEquals( $query->found_posts, 0 );
	}

	/**
	 * Test another date query with multiple range comparisons
	 * 
	 * @group multisite
	 */
	public function testDateQueryCompare2() {
		ep_create_date_query_posts();

		$args = array(
			's' => 'findme',
			'sites' => 'all',
			'posts_per_page' => 100,
			'date_query' => array(
				array(
					'monthnum'  => 1,
					'compare'   => '<=',
				),
				array(
					'year'      => 2012,
					'compare'   => '>=',
				),
				array(
					'day' => array( 5, 6 ),
					'compare'   => 'BETWEEN',
				),
			)
		);

		$query = new WP_Query( $args );
		$this->assertEquals( 6, $query->post_count );
		$this->assertEquals( 6, $query->found_posts );
	}

	/**
	 * Test date query where posts are only pulled from weekdays
	 * 
	 * @group multisite
	 */
	public function testDateQueryWeekdayRange() {
		ep_create_date_query_posts();

		$args = array(
			's' => 'findme',
			'sites' => 'all',
			'posts_per_page' => 100,
			'date_query' => array(
				array(
					'dayofweek' => array( 2, 6 ),
					'compare'   => 'BETWEEN',
				),
			),
		);

		$query = new WP_Query( $args );
		$this->assertEquals( 27, $query->post_count );
		$this->assertEquals( 27, $query->found_posts );
	}

	/**
	 * Test a tax query search
	 *
	 * @since 1.0
	 * @group multisite
	 */
	public function testTaxQuery() {
		$sites = ep_get_sites();

		$i = 0;

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			ep_create_and_sync_post( array( 'post_content' => 'findme', 'tags_input' => array( 'one', 'three' ) ) );

			if ( $i > 0 ) {
				ep_create_and_sync_post( array( 'post_content' => 'findme', 'tags_input' => array( 'two', 'three' ) ) );
			}

			ep_refresh_index();

			restore_current_blog();

			$i++;
		}

		$args = array(
			's' => 'findme',
			'sites' => 'all',
			'tax_query' => array(
				array(
					'taxonomy' => 'post_tag',
					'terms' => array( 'two' ),
					'field' => 'slug',
				)
			)
		);

		$query = new WP_Query( $args );

		$this->assertEquals( $query->post_count, 2 );
		$this->assertEquals( $query->found_posts, 2 );
	}

	/**
	 * Test a post type query search for pages
	 *
	 * @since 1.3
	 * @group multisite
	 */
	public function testPostTypeSearchQueryPage() {
		$sites = ep_get_sites();

		$i = 0;

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			ep_create_and_sync_post( array( 'post_content' => 'findme' ) );

			if ( $i > 0 ) {
				ep_create_and_sync_post( array( 'post_content' => 'findme', 'post_type' => 'page' ) );
			}

			ep_refresh_index();

			restore_current_blog();

			$i++;
		}

		$args = array(
			's' => 'findme',
			'sites' => 'all',
			'post_type' => 'page',
		);

		$query = new WP_Query( $args );

		$this->assertEquals( $query->post_count, 2 );
		$this->assertEquals( $query->found_posts, 2 );
	}

	/**
	 * Test a post type query search for posts
	 *
	 * @since 1.3
	 * @group multisite
	 */
	public function testPostTypeSearchQueryPost() {
		$sites = ep_get_sites();

		$i = 0;

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			ep_create_and_sync_post( array( 'post_content' => 'findme', 'post_type' => 'page' ) );

			if ( $i > 0 ) {
				ep_create_and_sync_post( array( 'post_content' => 'findme' ) );
			}

			ep_refresh_index();

			restore_current_blog();

			$i++;
		}

		$args = array(
			's' => 'findme',
			'sites' => 'all',
			'post_type' => 'post',
		);

		$query = new WP_Query( $args );

		$this->assertEquals( $query->post_count, 2 );
		$this->assertEquals( $query->found_posts, 2 );
	}

	/**
	 * Test a post type query search where no post type is specified
	 *
	 * @since 1.3
	 * @group multisite
	 */
	public function testNoPostTypeSearchQuery() {
		$sites = ep_get_sites();

		$i = 0;

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			ep_create_and_sync_post( array( 'post_content' => 'findme', 'post_type' => 'page' ) );

			if ( $i > 0 ) {
				ep_create_and_sync_post( array( 'post_content' => 'findme' ) );
			}

			ep_refresh_index();

			restore_current_blog();

			$i++;
		}

		$args = array(
			's' => 'findme',
			'sites' => 'all',
		);

		$query = new WP_Query( $args );

		$this->assertEquals( $query->post_count, 5 );
		$this->assertEquals( $query->found_posts, 5 );
	}

	/**
	 * Test a post type query non-search where no post type is specified. Defaults to `post` post type
	 *
	 * @since 1.3
	 * @group multisite
	 */
	public function testNoPostTypeNoSearchQuery() {
		$sites = ep_get_sites();

		$i = 0;

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			ep_create_and_sync_post( array( 'post_content' => 'findme', 'post_type' => 'page' ) );

			if ( $i > 0 ) {
				ep_create_and_sync_post( array( 'post_content' => 'findme' ) );
			}

			ep_refresh_index();

			restore_current_blog();

			$i++;
		}

		$args = array(
			'ep_integrate' => true,
			'sites' => 'all',
		);

		$query = new WP_Query( $args );

		$this->assertEquals( $query->post_count, 2 );
		$this->assertEquals( $query->found_posts, 2 );
	}

	/**
	 * Test an author ID query
	 *
	 * @since 1.0
	 * @group multisite
	 */
	public function testAuthorIDQuery() {
		$sites = ep_get_sites();

		$i = 0;

		$user_id = $this->factory->user->create( array( 'user_login' => 'john', 'role' => 'administrator' ) );

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			ep_create_and_sync_post( array( 'post_content' => 'findme' ) );

			if ( $i > 0 ) {
				ep_create_and_sync_post( array( 'post_content' => 'findme', 'post_author' => $user_id ) );
			}

			ep_refresh_index();

			restore_current_blog();

			$i++;
		}

		$args = array(
			's' => 'findme',
			'sites' => 'all',
			'author' => $user_id,
		);

		$query = new WP_Query( $args );

		$this->assertEquals( $query->post_count, 2 );
		$this->assertEquals( $query->found_posts, 2 );
	}

	/**
	 * Test an author name query
	 *
	 * @since 1.0
	 * @group multisite
	 */
	public function testAuthorNameQuery() {
		$sites = ep_get_sites();

		$i = 0;

		$user_id = $this->factory->user->create( array( 'user_login' => 'john', 'role' => 'administrator' ) );

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			ep_create_and_sync_post( array( 'post_content' => 'findme' ) );

			if ( $i > 0 ) {
				ep_create_and_sync_post( array( 'post_content' => 'findme', 'post_author' => $user_id ) );
			}

			ep_refresh_index();

			restore_current_blog();

			$i++;
		}

		$args = array(
			's' => 'findme',
			'sites' => 'all',
			'author_name' => 'john',
		);

		$query = new WP_Query( $args );

		$this->assertEquals( $query->post_count, 2 );
		$this->assertEquals( $query->found_posts, 2 );
	}

	/**
	 * Test a fuzzy search on meta
	 *
	 * @since 1.0
	 * @group multisite
	 */
	public function testSearchMetaQuery() {
		$sites = ep_get_sites();

		$i = 0;

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			ep_create_and_sync_post( array( 'post_content' => 'post content' ) );

			if ( $i > 0 ) {
				ep_create_and_sync_post( array( 'post_content' => 'post content' ), array( 'test_key' => 'findme' ) );
			}

			ep_refresh_index();

			restore_current_blog();

			$i++;
		}

		$args = array(
			's' => 'findme',
			'sites' => 'all',
			'search_fields' => array(
				'post_title',
				'post_excerpt',
				'post_content',
				'meta' => array( 'test_key' ),
			),
		);

		$query = new WP_Query( $args );

		$this->assertEquals( $query->post_count, 2 );
		$this->assertEquals( $query->found_posts, 2 );
	}

	/**
	 * Test a search with a filter on meta
	 *
	 * @since 1.3
	 * @group multisite
	 */
	public function testFilterMetaQuery() {
		$sites = ep_get_sites();

		$i = 0;

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			ep_create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => 'findme', 'test_key2' => 'findme3', ) );

			if ( $i > 0 ) {
				ep_create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key2' => 'findme', 'test_key' => 'value2', 'test_key3' => 'findme' ) );
			}

			ep_refresh_index();

			restore_current_blog();

			$i++;
		}

		$args = array(
			's' => 'findme',
			'sites' => 'all',
			'meta_query' => array(
				array(
					'key' => 'test_key',
					'value' => 'value2'
				),
				array(
					'key' => 'test_key2',
					'value' => 'findme3',
					'compare' => '!=',
				),
				array(
					'key' => 'test_key3',
					'compare' => 'exists',
				)
			)
		);

		$query = new WP_Query( $args );

		$this->assertEquals( $query->post_count, 2 );
		$this->assertEquals( $query->found_posts, 2 );
	}

	/**
	 * Test a fuzzy search on taxonomy terms
	 *
	 * @since 1.0
	 * @group multisite
	 */
	public function testSearchTaxQuery() {
		$sites = ep_get_sites();

		$i = 0;

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			ep_create_and_sync_post( array( 'post_content' => 'post content' ) );

			if ( $i > 0 ) {
				ep_create_and_sync_post( array( 'post_content' => 'post content', 'tags_input' => array( 'findme 2' ) ));
			}

			ep_refresh_index();

			restore_current_blog();

			$i++;
		}

		$args = array(
			's' => 'one findme two',
			'sites' => 'all',
			'search_fields' => array(
				'post_title',
				'post_excerpt',
				'post_content',
				'taxonomies' => array( 'post_tag' )
			),
		);

		$query = new WP_Query( $args );

		$this->assertEquals( $query->post_count, 2 );
		$this->assertEquals( $query->found_posts, 2 );
	}

	/**
	 * Test a fuzzy search on author names
	 *
	 * @since 1.0
	 * @group multisite
	 */
	public function testSearchAuthorQuery() {
		$sites = ep_get_sites();

		$i = 0;

		$user_id = $this->factory->user->create( array( 'user_login' => 'john', 'role' => 'administrator' ) );

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			ep_create_and_sync_post( array( 'post_content' => 'post content' ) );

			if ( $i > 0 ) {
				ep_create_and_sync_post( array( 'post_content' => 'post content', 'post_author' => $user_id ) );
			}

			ep_refresh_index();

			restore_current_blog();

			$i++;
		}

		$args = array(
			's' => 'john boy',
			'sites' => 'all',
			'search_fields' => array(
				'post_title',
				'post_excerpt',
				'post_content',
				'author_name'
			),
		);

		$query = new WP_Query( $args );

		$this->assertEquals( $query->post_count, 2 );
		$this->assertEquals( $query->found_posts, 2 );
	}

	/**
	 * Test a fuzzy search on taxonomy terms
	 *
	 * @since 1.0
	 * @group multisite
	 */
	public function testAdvancedQuery() {
		$user_id = $this->factory->user->create( array( 'user_login' => 'john', 'role' => 'administrator' ) );

		$sites = ep_get_sites();

		switch_to_blog( $sites[0]['blog_id'] );

		ep_create_and_sync_post( array(
			'post_content' => 'post content',
			'tags_input' => array( 'term' )
		) );

		ep_refresh_index();

		restore_current_blog();

		switch_to_blog( $sites[1]['blog_id'] );

		ep_create_and_sync_post( array(
			'post_content' => 'post content',
			'tags_input' => array( 'term' ),
			'post_author' => $user_id,
		) );

		ep_refresh_index();

		restore_current_blog();

		switch_to_blog( $sites[2]['blog_id'] );

		ep_create_and_sync_post( array(
			'post_content' => 'post content',
			'tags_input' => array( 'term' ),
			'post_author' => $user_id,
			'post_type' => 'ep_test'
		), array( 'test_key' => 'findme' ) );

		ep_refresh_index();

		restore_current_blog();

		$args = array(
			's' => 'findme',
			'sites' => 'all',
			'post_type' => 'ep_test',
			'author' => $user_id,
			'tax_query' => array(
				array(
					'taxonomy' => 'post_tag',
					'terms' => array( 'term' ),
					'field' => 'slug',
				)
			),
			'search_fields' => array(
				'post_title',
				'post_excerpt',
				'post_content',
				'meta' => array( 'test_key' ),
			),
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );
	}

	/**
	 * Test pagination
	 *
	 * @since 0.9
	 * @group multisite
	 */
	public function testPagination() {
		$sites = ep_get_sites();

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			ep_create_and_sync_post( array( 'post_title' => 'findme' ) );
			ep_create_and_sync_post( array( 'post_title' => 'findme' ) );

			ep_refresh_index();

			restore_current_blog();
		}

		$args = array(
			's' => 'findme',
			'sites' => 'all',
			'posts_per_page' => 2,
		);

		$query = new WP_Query( $args );

		$found_posts = array();

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 6, $query->found_posts );

		$found_posts[] = $query->posts[0]->site_id . $query->posts[0]->ID;
		$found_posts[] = $query->posts[1]->site_id . $query->posts[1]->ID;

		$args = array(
			's' => 'findme',
			'sites' => 'all',
			'posts_per_page' => 2,
			'paged' => 2,
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 6, $query->found_posts );

		$found_posts[] = $query->posts[0]->site_id . $query->posts[0]->ID;
		$found_posts[] = $query->posts[1]->site_id . $query->posts[1]->ID;

		$this->assertEquals( 4, count( array_unique( $found_posts ) ) );
	}

	/**
	 * Test query restoration after wp_reset_postdata
	 *
	 * @since 0.9.2
	 * @group multisite
	 */
	public function testQueryRestorationResetPostData() {
		$old_blog_id = get_current_blog_id();

		$main_post_id = $this->factory->post->create();

		query_posts( array( 'p' => $main_post_id ) );
		$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];

		$sites = ep_get_sites();

		$i = 0;

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			ep_create_and_sync_post( array( 'post_title' => 'findme' ) );
			ep_create_and_sync_post( array( 'post_title' => 'findme' ) );
			ep_create_and_sync_post( array( 'post_title' => 'notfirstblog' ) );

			ep_refresh_index();

			restore_current_blog();

			$i++;
		}

		$args = array(
			's' => 'notfirstblog',
			'sites' => 'all',
		);

		$query = new WP_Query( $args );

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				global $post;
				$query->the_post();

				// do stuff!
			}
		}

		wp_reset_postdata();

		$new_blog_id = get_current_blog_id();

		$this->assertEquals( $old_blog_id, $new_blog_id );
	}

	/**
	 * Test query restoration after wp_reset_query
	 *
	 * @since 0.9.2
	 * @group multisite
	 */
	public function testQueryRestorationResetQuery() {
		$old_blog_id = get_current_blog_id();

		$main_post_id = $this->factory->post->create();

		query_posts( array( 'p' => $main_post_id ) );
		$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];

		$sites = ep_get_sites();

		$i = 0;

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			ep_create_and_sync_post( array( 'post_title' => 'findme' ) );
			ep_create_and_sync_post( array( 'post_title' => 'findme' ) );

			if ( $i > 0 ) {
				ep_create_and_sync_post( array( 'post_title' => 'notfirstblog' ) );
			}

			ep_refresh_index();

			restore_current_blog();

			$i++;
		}

		$args = array(
			's' => 'notfirstblog',
			'sites' => 'all',
		);

		$query = new WP_Query( $args );

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				global $post;
				$query->the_post();

				// do stuff!
			}
		}

		wp_reset_query();

		$new_blog_id = get_current_blog_id();

		$this->assertEquals( $old_blog_id, $new_blog_id );
	}

	/**
	 * Test query stack with nested queries
	 *
	 * @since 1.2
	 * @group multisite
	 */
	public function testQueryStack() {
		$old_blog_id = get_current_blog_id();

		$main_post_id = $this->factory->post->create();

		query_posts( array( 'p' => $main_post_id ) );
		$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];

		$sites = ep_get_sites();

		$i = 0;

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			ep_create_and_sync_post( array( 'post_title' => 'findme' ) );
			ep_create_and_sync_post( array( 'post_title' => 'findme' ) );

			if ( $i > 0 ) {
				ep_create_and_sync_post( array( 'post_title' => 'notfirstblog' ) );
			} elseif ( $i === 0 ) {
				ep_create_and_sync_post( array( 'post_title' => 'firstblog' ) );
			}

			ep_refresh_index();

			restore_current_blog();

			$i++;
		}

		$args = array(
			's' => 'notfirstblog',
			'sites' => (int) $sites[1]['blog_id'],
		);

		$query = new WP_Query( $args );

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();

				$blog_id = get_current_blog_id();

				$query_two = new WP_Query();

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
	}

	/**
	 * Test filter for skipping query integration
	 *
	 * @since 1.2
	 * @group multisite
	 */
	public function testQueryIntegrationSkip() {
		$main_post_id = $this->factory->post->create();

		query_posts( array( 'p' => $main_post_id ) );
		$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];

		$sites = ep_get_sites();

		$i = 0;

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			ep_create_and_sync_post( array( 'post_title' => 'findme' ) );
			ep_create_and_sync_post( array( 'post_title' => 'findme' ) );

			if ( $i > 0 ) {
				ep_create_and_sync_post( array( 'post_title' => 'notfirstblog' ) );
			} elseif ( $i === 0 ) {
				ep_create_and_sync_post( array( 'post_title' => 'firstblog' ) );
			}

			ep_refresh_index();

			restore_current_blog();

			$i++;
		}

		add_filter( 'ep_skip_query_integration', '__return_true' );

		$args = array(
			's' => 'notfirstblog',
			'sites' => 'all',
		);

		$query = new WP_Query( $args );

		$this->assertTrue( empty( $query->posts ) );
	}

	/**
	 * Test post object data
	 *
	 * @since 1.4
	 * @group multisite
	 */
	public function testPostObject() {
		$sites = ep_get_sites();

		$user_id = $this->factory->user->create( array( 'user_login' => 'john', 'role' => 'administrator' ) );

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			ep_create_and_sync_post( array( 'post_title' => 'findme', 'post_author' => $user_id, 'post_excerpt' => 'find', 'menu_order' => $site['blog_id'] ) );
			ep_create_and_sync_post( array( 'post_title' => 'findme', 'post_author' => $user_id, 'post_excerpt' => 'find', 'menu_order' => $site['blog_id'] ) );

			ep_refresh_index();

			restore_current_blog();
		}

		$args = array(
			's' => 'findme',
			'sites' => 'all',
			'posts_per_page' => 10,
		);

		$query = new WP_Query( $args );


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
	}

	/**
	 * Test index_exists helper function
	 * 
	 * @group multisite
	 */
	public function testIndexExists() {
		$sites = ep_get_sites();

		$first_site_index = ep_get_index_name( $sites[0]['blog_id'] );
		$index_should_exist = ep_index_exists( $first_site_index );
		$index_should_not_exist = ep_index_exists( $first_site_index . 2 );

		$this->assertTrue( $index_should_exist );
		$this->assertFalse( $index_should_not_exist );
	}

	/**
	 * Tests Deletion of index when a blog is deleted
	 * 
	 * @group multisite
	 */
	public function testDeleteIndex( ) {
		$index_count = ep_count_indexes();

		$count_indexes = $index_count['total_indexes'];
		$last_blog_id = $index_count['last_blog_id_with_index'];

		wpmu_delete_blog( $last_blog_id );

		$post_delete_count = ep_count_indexes();
		$post_count_indexes = $post_delete_count['total_indexes'];

		$this->assertNotEquals( $count_indexes, $post_count_indexes );
	}

	/**
	 * Tests deletion of index when a blog is deleted
	 * 
	 * @link https://github.com/10up/ElasticPress/issues/392
	 * @group multisite
	 */
	public function testDeactivateSite( ) {
		$index_count = ep_count_indexes();

		$count_indexes = $index_count['total_indexes'];
		$last_blog_id = $index_count['last_blog_id_with_index'];

		do_action( 'deactivate_blog', $last_blog_id );
		update_blog_status( $last_blog_id, 'deleted', '1' );

		$post_delete_count = ep_count_indexes();
		$post_count_indexes = $post_delete_count['total_indexes'];

		$this->assertNotEquals( $count_indexes, $post_count_indexes );
	}

	/**
	 * Tests deletion of index when a blog is marked as spam
	 * 
	 * @group multisite
	 * @link https://github.com/10up/ElasticPress/issues/392
	 */
	public function testSpamSite( ) {
		$index_count = ep_count_indexes();

		$count_indexes = $index_count['total_indexes'];
		$last_blog_id = $index_count['last_blog_id_with_index'];

		update_blog_status( $last_blog_id, 'spam', '1' );

		$post_delete_count = ep_count_indexes();
		$post_count_indexes = $post_delete_count['total_indexes'];

		$this->assertNotEquals( $count_indexes, $post_count_indexes );
	}

	/**
	 * Tests deletion of index when a blog is marked as archived
	 * 
	 * @group multisite
	 * @link https://github.com/10up/ElasticPress/issues/392
	 */
	public function testArchivedSite( ) {
		$index_count = ep_count_indexes();

		$count_indexes = $index_count['total_indexes'];
		$last_blog_id = $index_count['last_blog_id_with_index'];

		update_blog_status( $last_blog_id, 'archived', '1' );

		$post_delete_count = ep_count_indexes();
		$post_count_indexes = $post_delete_count['total_indexes'];

		$this->assertNotEquals( $count_indexes, $post_count_indexes );
	}

	/**
	 * Check if elasticpress_enabled() properly handles an object without the is_search() method.
	 * @group 285
	 * @link https://github.com/10up/ElasticPress/issues/285
	 */
	public function testQueryWithoutIsSearch() {
		$query	 = new stdClass();
		$check	 = ep_elasticpress_enabled( $query );
		$this->assertFalse( $check );
	}

	/**
	 * Check if elasticpress_enabled() properly handles an object with the is_search() method.
	 * 
	 * @group multisite
	 * @link https://github.com/10up/ElasticPress/issues/285
	 */
	public function testQueryWithIsSearch() {
		$args	 = array(
			's'		 => 'findme',
			'sites'	 => 'all',
		);
		$query	 = new WP_Query( $args );
		$check	 = ep_elasticpress_enabled( $query );
		$this->assertTrue( $check );
	}

	/**
	 * Test index status
	 *
	 * Tests index status when site is and is not indexed.
	 *
	 * @since 0.1.0
	 * @group multisite
	 */
	function testGetIndexStatus() {

		$blog = get_current_blog_id();

		$status_indexed = ep_get_index_status( $blog );

		ep_delete_index();

		$status_unindexed = ep_get_index_status( $blog );

		$this->setUp();

		$this->assertTrue( $status_indexed['status'] );
		$this->assertFalse( $status_unindexed['status'] );

	}

	/**
	 * Search status
	 *
	 * Test search status.
	 *
	 * @since 0.1.0
	 * @group multisite
	 */
	function testGetSearchStatus() {

		$blog = get_current_blog_id();

		$status_indexed = ep_get_search_status( $blog );

		ep_delete_index();

		$status_unindexed = ep_get_search_status( $blog );

		$this->setUp();

		$this->assertInstanceOf( 'stdClass', $status_indexed );
		$this->assertFalse( $status_unindexed );

	}

	/**
	 * Cluster status
	 *
	 * Test cluster status.
	 *
	 * @since 0.1.0
	 * @group multisite
	 */
	function testGetClusterStatus() {

		$status_indexed = ep_get_cluster_status();

		ep_delete_index();

		$status_unindexed = ep_get_cluster_status();


		$this->setUp();

		if ( is_array( $status_indexed ) ) {

			$this->assertTrue( $status_indexed['status'] );

		} else {

			$this->assertTrue( isset( $status_indexed->cluster_name ) );

		}

		if ( is_array( $status_unindexed ) ) {

			$this->assertTrue( $status_unindexed['status'] );

		} else {

			$this->assertTrue( isset( $status_unindexed->cluster_name ) );

		}
	}
	
}
