<?php

class EPTestCore extends WP_UnitTestCase {

	/**
	 * Store info about our wp_remote_request mock
	 *
	 * @var array
	 * @since 0.1.0
	 */
	protected $wp_remote_request_mock = array(
		'return' => false,
		'args' => false,
	);

	/**
	 * Helps us keep track of actions that have fired
	 *
	 * @var array
	 * @since 0.1.0
	 */
	protected $fired_actions = array();

	/**
	 * Helps us keep track of applied filters
	 *
	 * @var array
	 * @since 0.1.1
	 */
	protected $applied_filters = array();

	/**
	 * Setup each test. We use Patchwork to replace wp_remote_request.
	 *
	 * @since 0.1.0
	 */
	public function setUp() {
		parent::setUp();

		$user = @wp_signon(
			array(
				'user_login' => 'admin',
				'user_password' => 'password'
			)
		);

		wp_set_current_user( $user->ID );

		Patchwork\replace( 'wp_remote_request', function( $url, $args = array() ) {

			if ( ! empty( $this->wp_remote_request_mock['args'] ) ) {
				$args = func_get_args();

				for ( $i = 0; $i < count( $this->wp_remote_request_mock['args'] ); $i++ ) {
					if ( $args[$i] != $this->wp_remote_request_mock['args'][$i] ) {
						return false;
					}
				}
			}

			return $this->wp_remote_request_mock['return'];
		} );
	}

	/**
	 * Clean up after each test. Reset our mocks
	 *
	 * @since 0.1.0
	 */
	public function tearDown() {
		parent::tearDown();

		$this->wp_remote_request_mock['args'] = false;
		$this->wp_remote_request_mock['return'] = false;
		$this->fired_actions = array();
	}

	/**
	 * Recursive version of PHP's in_array
	 *
	 * @todo Max recursion restriction
	 * @since 0.1.2
	 * @param mixed $needle
	 * @param array $haystack
	 * @return bool
	 */
	private function _deepInArray( $needle, $haystack ) {
		if ( in_array( $needle, $haystack, true ) ) {
			return true;
		}

		$result = false;

		foreach ( $haystack as $new_haystack ) {
			if ( is_array( $new_haystack ) ) {
				$result = $result || $this->_deepInArray( $needle, $new_haystack );
			}
		}

		return $result;
	}

	/**
	 * We have to mock the request properly to setup WP Query integration.
	 *
	 * @since 0.9
	 */
	public function _setupWPQueryIntegration() {

		$response = array(
			'headers' => array(
				'content-type' => 'application/json; charset=UTF-8',
				'content-length' => '*',
			),
			'body' => '*',
			'response' => array(
				'code' => 200,
				'message' => 'OK',
			),
			'cookies' => array(),
			'filename' => null,
		);

		$this->wp_remote_request_mock['args'] = array( ep_get_index_url() . '/_status' );
		$this->wp_remote_request_mock['return'] = $response;

		EP_WP_Query_Integration::factory()->setup();
	}

	/**
	 * Create a WP post and "sync" it to Elasticsearch. We are mocking the sync
	 *
	 * @param array $post_args
	 * @param array $post_meta
	 * @since 0.1.2
	 * @return int|WP_Error
	 */
	protected function _createAndSyncPost( $post_args = array(), $post_meta = array() ) {

		$post_types = ep_get_indexable_post_types();

		$post_id = wp_insert_post( wp_parse_args( array(
			'post_type' => $post_types[0],
			'post_status' => 'draft',
			'author' => 1,
			'post_title' => 'Test Post ' . time(),
		), $post_args ) );

		// Quit if we have a WP_Error object
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		if ( ! empty( $post_meta ) ) {
			foreach ( $post_meta as $key => $value ) {
				// No need for sanitization here
				update_post_meta( $post_id, $key, $value );
			}
		}

		$response = array(
			'headers' => array(
				'content-type' => 'application/json; charset=UTF-8',
				'content-length' => '*',
			),
			'body' => '{"_index":"' . ep_get_index_name() . '","_type":"post","_id":"' . $post_id . '","_version":1,"created":true}',
			'response' => array(
				'code' => 200,
				'message' => 'OK',
			),
			'cookies' => array(),
			'filename' => null,
		);

		$this->wp_remote_request_mock['args'] = array( ep_get_index_url(). '/post/' . $post_id );

		$this->wp_remote_request_mock['return'] = $response;

		wp_publish_post( $post_id );

		return $post_id;
	}

	/**
	 * Test a simple single site search. This test runs a simple search on post_content
	 *
	 * @since 0.1.0
	 */
	public function testSingleSiteSearchBasic() {

		$post_id = $this->_createAndSyncPost();
		$post = get_post( $post_id );

		$response = array(
			'headers' => array(
				'content-type' => 'application/json; charset=UTF-8',
				'content-length' => '*',
			),
			'body' => '{"took":3,"timed_out":false,"_shards":{"total":5,"successful":5,"failed":0},"hits":{"total":1,"max_score":1,"hits":[{"_index":"test-index","_type":"post","_id":"' . $post_id . '","_score":1,"_source":{"post_id":' . $post_id . ',"post_author":{"login":"admin","display_name":"admin"},"post_date":"2014-03-18 14:14:00","post_date_gmt":"2014-03-18 14:14:00","post_title":"' . get_the_title( $post_id ) . '","post_excerpt":"' . apply_filters( 'the_excerpt', $post->post_excerpt ) . '","post_content":"' . apply_filters( 'the_content', $post->post_content ) . '","post_status":"' . get_post_status( $post_id ) . '","post_name":"test-post","post_modified":"2014-03-18 14:14:00","post_modified_gmt":"2014-03-18 14:14:00","post_parent":0,"post_type":"' . get_post_type( $post_id ) . '","post_mime_type":"","permalink":"' . get_permalink( $post_id ) . '"}}]}}',
			'response' => array(
				'code' => 200,
				'message' => 'OK',
			),
			'cookies' => array(),
			'filename' => null,
		);

		$this->wp_remote_request_mock['args'] = array( ep_get_index_url() . '/post/_search' );
		$this->wp_remote_request_mock['return'] = $response;

		$args = array(
			's' => 'test',
		);
		$query = new EP_Query( $args );

		$this->assertEquals( $query->post_count, 1 );

		while ( $query->have_posts() ) {
			$query->the_post();

			$this->assertEquals( get_the_title( $post_id ), get_the_title() );
		}

		wp_reset_postdata();
	}

	/**
	 * Test a simple single site search. This test runs a simple search on post_content against a bunch of posts some
	 * across a network of blogs.
	 *
	 * @since 0.1.0
	 */
	public function testMultiSiteSearchBasic() {
		$config = $this->_configureMultiSite();

		// First let's create some posts across the network
		$posts = array();
		$body = '';
		$sites = wp_get_sites();
		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			for ( $i = 0; $i < 3; $i++ ) {

				$post_id = $this->_createAndSyncPost();
				$post = get_post( $post_id );

				$ep_id = $post_id;
				if ( $site['blog_id'] > 1 ) {
					$ep_id = $site['blog_id'] . 'ms' . $ep_id;
				}

				if ( ! empty( $body ) ) {
					$body .= ', ';
				}

				$body .= '{"_index":"test-index","_type":"post","_id":"' . $ep_id . '","_score":1,"_source":{"post_id":' . $post_id . ',"post_author":{"login":"admin","display_name":"admin"},"post_date":"2014-03-18 14:14:00","post_date_gmt":"2014-03-18 14:14:00","post_title":"' . get_the_title( $post_id ) . '","post_excerpt":"' . apply_filters( 'the_excerpt', $post->post_excerpt ) . '","post_content":"' . apply_filters( 'the_content', $post->post_content ) . '","post_status":"' . get_post_status( $post_id ) . '","post_name":"test-post","post_modified":"2014-03-18 14:14:00","post_modified_gmt":"2014-03-18 14:14:00","post_parent":0,"post_type":"' . get_post_type( $post_id ) . '","post_mime_type":"","permalink":"' . get_permalink( $post_id ) . '","site_id":' . $site['blog_id'] . '}}';

				$posts[] = $post_id;
			}

			restore_current_blog();
		}

		$response = array(
			'headers' => array(
				'content-type' => 'application/json; charset=UTF-8',
				'content-length' => '*',
			),
			'body' => '{"took":3,"timed_out":false,"_shards":{"total":5,"successful":5,"failed":0},"hits":{"total":' . count( $posts ) . ',"max_score":1,"hits":[' . $body . ']}}',
			'response' => array(
				'code' => 200,
				'message' => 'OK',
			),
			'cookies' => array(),
			'filename' => null,
		);

		$this->wp_remote_request_mock['args'] = array( $config[0]['host'] . '/' . $config[0]['index_name'] . '/post/_search' );
		$this->wp_remote_request_mock['return'] = $response;

		$args = array(
			's' => 'test',
		);
		$query = new EP_Query( $args );

		// Make sure the query returns all the posts we created
		$this->assertEquals( $query->post_count, count( $posts ) );

		// Make sure our loop goes through posts that are on different sites on the network
		$posts_cross_site = 0;

		while ( $query->have_posts() ) {
			$query->the_post();

			if ( get_current_blog_id() != 1 ) {
				$posts_cross_site++;
			}
		}

		$this->assertTrue( $posts_cross_site > 0 );
	}

	/**
	 * Make sure proper taxonomies are synced with post. Hidden taxonomies should be skipped!
	 *
	 * @since 0.1.1
	 */
	public function testSingleSitePostTermSync() {
		$config = $this->_configureSingleSite();

		add_filter( 'ep_post_sync_args', function( $post_args ) {
			$this->applied_filters['ep_post_sync_args'] = $post_args;

			return $post_args;
		}, 10, 1 );

		$post_id = $this->_createAndSyncPost( array(
			'tags_input' => array( 'test-tag', 'test-tag2' ),
			'tax_input' => array(
				'ep_hidden' => 'test-ep-hidden'
			)
		) );

		// Check if ES post sync filter has been triggered
		$this->assertTrue( ! empty( $this->applied_filters['ep_post_sync_args'] ) );

		// Check if ES post sync args have proper terms. ep_hidden terms should not exists
		// since it's a private taxonomy
		$this->assertTrue( ! empty( $this->applied_filters['ep_post_sync_args']['terms']['category'] ) && count( $this->applied_filters['ep_post_sync_args']['terms']['category'] ) == 1 );
		$this->assertTrue( ! empty( $this->applied_filters['ep_post_sync_args']['terms']['post_tag'] ) && count( $this->applied_filters['ep_post_sync_args']['terms']['post_tag'] ) == 2 );
		$this->assertTrue( empty( $this->applied_filters['ep_post_sync_args']['ep_hidden'] ) );
	}

	/**
	 * Test creating a post on single site, making sure that post syncs to ES. Test deleting a post and making sure
	 * the post is deleted from ES.
	 *
	 * @since 0.1.0
	 */
	public function testSingleSitePostCreateDeleteSync() {
		$config = $this->_configureSingleSite();

		// First let's create a post to play with

		add_action( 'ep_sync_on_transition', function() {
			$this->fired_actions['ep_sync_on_transition'] = true;
		}, 10, 0 );

		$post_id = $this->_createAndSyncPost();

		// Let's test to see if this post was sent to the index

		$this->assertTrue( ! empty( $this->fired_actions['ep_sync_on_transition'] ) );

		add_action( 'ep_delete_post', function( $args ) {
			$this->fired_actions['ep_delete_post'] = true;
		} );

		wp_delete_post( $post_id );

		// Check if ES delete action has been properly fired

		$this->assertTrue( ! empty( $this->fired_actions['ep_delete_post'] ) );

		// Now let's make sure the post is not indexed

		$response = array(
			'headers' => array(
				'content-type' => 'application/json; charset=UTF-8',
				'content-length' => '*',
			),
			'body' => '{"_index":"test-index","_type":"post","_id":"' . $post_id . '","found":false}',
			'response' => array(
				'code' => 404,
				'message' => 'Not Found',
			),
			'cookies' => array(),
			'filename' => null,
		);

		$this->wp_remote_request_mock['args'] = array( $config['host'] . '/' . $config['index_name'] . '/post/' . $post_id );
		$this->wp_remote_request_mock['return'] = $response;

		$post_indexed = ep_post_indexed( $post_id );

		$this->assertFalse( $post_indexed );
	}

	/**
	 * Test creating a bunch of posts on multisite across the network, making sure that all posts sync to ES. Test
	 * deleting posts across the network and making sure the post is deleted from ES.
	 *
	 * @since 0.1.0
	 */
	public function testMultiSitePostCreateDeleteSync() {
		$config = $this->_configureMultiSite();

		// First let's create some posts across the network
		$post_ids_by_site = array();

		$sites = wp_get_sites();
		foreach ( $sites as $site ) {
			$post_ids_by_site[$site['blog_id']] = array();

			$post_ids_by_site[$site['blog_id']][] = $this->_createAndSyncPost( array(), array(), $site['blog_id'], true );
			$post_ids_by_site[$site['blog_id']][] = $this->_createAndSyncPost( array(), array(), $site['blog_id'], true );
			$post_ids_by_site[$site['blog_id']][] = $this->_createAndSyncPost( array(), array(), $site['blog_id'], true );
		}


		// Let's test to see if this post was sent to the index

		foreach( $post_ids_by_site as $blog_id => $post_ids ) {

			switch_to_blog( $blog_id );

			foreach( $post_ids as $post_id ) {
				$ep_id = ep_format_es_id( $post_id );

				add_action( 'ep_delete_post', function( $args ) {
					$this->fired_actions['ep_delete_post'] = true;
				} );

				wp_delete_post( $post_id );

				// Check if ES delete action has been properly fired

				$this->assertTrue( ! empty( $this->fired_actions['ep_delete_post'] ) );

				// Now let's make sure the post is not indexed

				$response = array(
					'headers' => array(
						'content-type' => 'application/json; charset=UTF-8',
						'content-length' => '*',
					),
					'body' => '{"_index":"test-index","_type":"post","_id":"' . $ep_id . '","found":false}',
					'response' => array(
						'code' => 404,
						'message' => 'Not Found',
					),
					'cookies' => array(),
					'filename' => null,
				);

				$this->wp_remote_request_mock['args'] = array( $config[0]['host'] . '/' . $config[0]['index_name'] . '/post/' . $ep_id );
				$this->wp_remote_request_mock['return'] = $response;

				$post_indexed = ep_post_indexed( $post_id, $blog_id, 0 );

				$this->assertFalse( $post_indexed );

				$this->wp_remote_request_mock['args'] = false;
				$this->wp_remote_request_mock['return'] = false;
				$this->fired_actions = array();
				remove_all_actions( 'ep_delete_post' );
			}

			restore_current_blog();
		}
	}


	/**
	 * Test to check our is_alive health check function for a single site.
	 * Test both our initial ping of the ES server as well as our storage of the status.
	 *
	 * @since 0.1.2
	 */
	public function testSingleSiteIsAlive() {
		$config = $this->_configureSingleSite();

		$response = array(
			'headers' => array(
				'content-type' => 'application/json; charset=UTF-8',
				'content-length' => '*',
			),
			'body' => '*',
			'response' => array(
				'code' => 200,
				'message' => 'OK',
			),
			'cookies' => array(),
			'filename' => null,
		);

		$this->wp_remote_request_mock['args'] = array( $config['host'] . '/' . $config['index_name'] . '/_status' );
		$this->wp_remote_request_mock['return'] = $response;

		$this->assertTrue( ep_is_alive() );
	}

	/**
	 * Test to check our is_alive health check function for multisite.
	 * Test both our initial ping of the ES server as well as our storage of the status.
	 *
	 * @since 0.1.2
	 */
	public function testMultiSiteIsAlive() {
		$config = $this->_configureMultiSite();

		$response = array(
			'headers' => array(
				'content-type' => 'application/json; charset=UTF-8',
				'content-length' => '*',
			),
			'body' => '*',
			'response' => array(
				'code' => 200,
				'message' => 'OK',
			),
			'cookies' => array(),
			'filename' => null,
		);

		// Test site 1
		$this->wp_remote_request_mock['args'] = array( $config[0]['host'] . '/' . $config[0]['index_name'] . '/_status' );
		$this->wp_remote_request_mock['return'] = $response;
		$this->assertTrue( ep_is_alive( 0 ) );

		// Test site 2
		$this->wp_remote_request_mock['args'] = array( $config[1]['host'] . '/' . $config[1]['index_name'] . '/_status' );
		$this->wp_remote_request_mock['return'] = $response;
		$this->assertTrue( ep_is_alive( 1 ) );
	}

	/**
	 * Test WP Query integration basic in single site
	 *
	 * @since 0.9
	 */
	public function testSingleSiteWPQuery() {
		$config = $this->_configureSingleSite();
		$post_ids = array();
		
		$post_ids[0] = $this->_createAndSyncPost();
		$post_ids[1] = $this->_createAndSyncPost();
		$post_ids[2] = $this->_createAndSyncPost();
		$post_ids[3] = $this->_createAndSyncPost();
		$post_ids[4] = $this->_createAndSyncPost();

		// We have to re-setup the query integration class
		$this->_setupWPQueryIntegration( $config );

		$response = array(
			'headers' => array(
				'content-type' => 'application/json; charset=UTF-8',
				'content-length' => '*',
			),
			'body' => '{"took":3,"timed_out":false,"_shards":{"total":5,"successful":5,"failed":0},"hits":{"total":1101,"max_score":1.0,"hits":[{"_index":"vistage-dev","_type":"post","_id":"782","_score":1.0, "_source" : {"post_id":'. $post_ids[0] .',"post_author":{"login":"","display_name":""},"post_date":"2014-08-12 17:40:53","post_date_gmt":"2014-08-12 17:40:53","post_title":"'. get_the_title( $post_ids[0] ) . '","post_excerpt":"","post_content":"","post_status":"publish","post_name":"post-776","post_modified":"2014-08-12 17:40:53","post_modified_gmt":"2014-08-12 17:40:53","post_parent":0,"post_type":"post","post_mime_type":"","permalink":"http:\/\/vip.dev\/2014\/08\/post-776\/","terms":{"category":[{"term_id":1,"slug":"uncategorized","name":"Uncategorized","parent":0}]},"post_meta":[],"site_id":' . get_current_blog_id() . '}},{"_index":"vistage-dev","_type":"post","_id":"1039","_score":1.0, "_source" : {"post_id":'. $post_ids[1] .',"post_author":{"login":"","display_name":""},"post_date":"2014-08-12 17:40:53","post_date_gmt":"2014-08-12 17:40:53","post_title":"'. get_the_title( $post_ids[1] ) . '","post_excerpt":"","post_content":"","post_status":"publish","post_name":"post-1033","post_modified":"2014-08-12 17:40:53","post_modified_gmt":"2014-08-12 17:40:53","post_parent":0,"post_type":"post","post_mime_type":"","permalink":"http:\/\/vip.dev\/2014\/08\/post-1033\/","terms":{"category":[{"term_id":1,"slug":"uncategorized","name":"Uncategorized","parent":0}]},"post_meta":[],"site_id":' . get_current_blog_id() . '}},{"_index":"vistage-dev","_type":"post","_id":"523","_score":1.0, "_source" : {"post_id":'. $post_ids[2] .',"post_author":{"login":"","display_name":""},"post_date":"2014-08-12 17:40:53","post_date_gmt":"2014-08-12 17:40:53","post_title":"'. get_the_title( $post_ids[2] ) . '","post_excerpt":"","post_content":"","post_status":"publish","post_name":"post-517","post_modified":"2014-08-12 17:40:53","post_modified_gmt":"2014-08-12 17:40:53","post_parent":0,"post_type":"post","post_mime_type":"","permalink":"http:\/\/vip.dev\/2014\/08\/post-517\/","terms":{"category":[{"term_id":1,"slug":"uncategorized","name":"Uncategorized","parent":0}]},"post_meta":[],"site_id":' . get_current_blog_id() . '}},{"_index":"vistage-dev","_type":"post","_id":"268","_score":1.0, "_source" : {"post_id":'. $post_ids[3] .',"post_author":{"login":"","display_name":""},"post_date":"2014-08-12 17:40:53","post_date_gmt":"2014-08-12 17:40:53","post_title":"'. get_the_title( $post_ids[3] ) . '","post_excerpt":"","post_content":"","post_status":"publish","post_name":"post-262","post_modified":"2014-08-12 17:40:53","post_modified_gmt":"2014-08-12 17:40:53","post_parent":0,"post_type":"post","post_mime_type":"","permalink":"http:\/\/vip.dev\/2014\/08\/post-262\/","terms":{"category":[{"term_id":1,"slug":"uncategorized","name":"Uncategorized","parent":0}]},"post_meta":[],"site_id":' . get_current_blog_id() . '}},{"_index":"vistage-dev","_type":"post","_id":"256","_score":1.0, "_source" : {"post_id":'. $post_ids[4] .',"post_author":{"login":"","display_name":""},"post_date":"2014-08-12 17:40:53","post_date_gmt":"2014-08-12 17:40:53","post_title":"'. get_the_title( $post_ids[4] ) . '","post_excerpt":"","post_content":"","post_status":"publish","post_name":"post-250","post_modified":"2014-08-12 17:40:53","post_modified_gmt":"2014-08-12 17:40:53","post_parent":0,"post_type":"post","post_mime_type":"","permalink":"http:\/\/vip.dev\/2014\/08\/post-250\/","terms":{"category":[{"term_id":1,"slug":"uncategorized","name":"Uncategorized","parent":0}]},"post_meta":[],"site_id":' . get_current_blog_id() . '}}]}}',
			'response' => array(
				'code' => 200,
				'message' => 'OK',
			),
			'cookies' => array(),
			'filename' => null,
		);

		$this->wp_remote_request_mock['args'] = array( $config['host'] . '/' . $config['index_name'] . '/post/_search' );
		$this->wp_remote_request_mock['return'] = $response;

		$args = array(
			's' => 'test',
		);

		add_action( 'ep_wp_query_search', function() {
			$this->fired_actions['ep_wp_query_search'] = true;
		}, 10, 0 );

		$query = new WP_Query( $args );

		$this->assertTrue( ! empty( $this->fired_actions['ep_wp_query_search'] ) );

		$this->assertEquals( $query->post_count, 5 );
		$this->assertEquals( $query->found_posts, 1101 );

		$i = 0;

		while ( $query->have_posts() ) {
			$query->the_post();

			$this->assertEquals( get_the_title( $post_ids[$i] ), get_the_title() );

			$i++;
		}
	}
}