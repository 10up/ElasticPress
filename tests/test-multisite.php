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

		ep_activate();

		ep_delete_network_alias();
		ep_create_network_alias( $indexes );

		wp_set_current_user( $admin_id );

		EP_WP_Query_Integration::factory()->setup();

		$this->setup_test_post_type();

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
	 * Test that a post becoming unpublished correctly gets removed from the Elasticsearch index
	 *
	 * @since 0.9.3
	 */
	public function testPostUnpublish() {
		$sites = ep_get_sites();

		foreach( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			add_action( 'ep_delete_post', array( $this, 'action_delete_post' ), 10, 0 );

			$post_id = ep_create_and_sync_post();

			ep_refresh_index();

			$post = ep_get_post( $post_id );

			// Ensure that our post made it over to elasticsearch
			$this->assertTrue( ! empty( $post ) );

			// Let's transition the post status from published to draft
			wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft' ) );

			ep_refresh_index();

			$this->assertTrue( ! empty( $this->fired_actions['ep_delete_post'] ) );

			$post = ep_get_post( $post_id );

			// Alright, now the post has been removed from the index, so this should be empty
			$this->assertTrue( empty( $post ) );

			$this->fired_actions = array();

			restore_current_blog();
		}
	}

	/**
	 * Test a simple post content search
	 *
	 * @since 0.9
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
	 * Test a tax query search
	 *
	 * @since 1.0
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
	 * Test a post type query search
	 *
	 * @since 1.0
	 */
	public function testPostTypeQuery() {
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
	 * Test an author ID query
	 *
	 * @since 1.0
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
	 * Test a fuzzy search on taxonomy terms
	 *
	 * @since 1.0
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
}