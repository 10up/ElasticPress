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
	 * Configure a single site test
	 *
	 * @since 0.1.0
	 * @return array
	 */
	protected function _configureSingleSite() {
		$config = array(
			'post_types' => array( 'post' ),
			'host' => 'http://127.0.0.1:9200',
			'index_name' => 'test-index',
		);

		ep_update_option( $config );

		return $config;
	}

	/**
	 * Configure a multisite test
	 *
	 * @since 0.1.0
	 * @return array
	 */
	protected function _configureMultiSite() {
		$config = array();

		$config[0] = array(
			'host' => 'http://127.0.0.1:9200',
			'index_name' => 'test-index',
			'cross_site_search_active' => 1,
		);

		ep_update_option( $config[0], 0 );

		$config[1] = array(
			'host' => 'http://888.0.0.1:9100',
			'post_types' => array( 'post' ),
			'index_name' => 'test-index-1',
		);

		ep_update_option( $config[1], 1 );

		$blog_ids = $this->factory->blog->create_many( 2 );

		$i = 2;

		foreach ( $blog_ids as $blog_id ) {
			$config[$blog_id] = array(
				'host' => 'http://999.0.0.1:9100',
				'post_types' => ( $i % 2 == 0 ) ? array( 'post', 'page' ) : array( 'page' ),
				'index_name' => 'test-index-' . $i,
			);

			ep_update_option( $config[$blog_id], $blog_id );

			$i++;
		}

		return $config;
	}

	/**
	 * Create a WP post and "sync" it to Elasticsearch. We are mocking the sync
	 *
	 * @param array $post_args
	 * @param int $site_id
	 * @param bool $cross_site
	 * @since 0.1.0
	 * @return int|WP_Error
	 */
	protected function _createAndSyncPost( $post_args = array(), $site_id = null, $cross_site = false ) {
		if ( $site_id != null ) {
			switch_to_blog( $site_id );
		}

		$config = ep_get_option( $site_id );
		$index_name = $config['index_name'];
		$host = $config['host'];

		if ( $cross_site ) {
			$global_config = ep_get_option( 0 );
			$index_name = $global_config['index_name'];
			$host = $global_config['host'];
		}

		$post_id = wp_insert_post( wp_parse_args( array(
			'post_type' => $config['post_types'][0],
			'post_status' => 'draft',
			'author' => 1,
			'post_title' => 'Test Post ' . time(),
		), $post_args ) );

		$ep_id = $post_id;
		if ( $site_id > 1 ) {
			$ep_id = $site_id . 'ms' . $post_id;
		}

		$response = array(
			'headers' => array(
				'content-type' => 'application/json; charset=UTF-8',
				'content-length' => '*',
			),
			'body' => '{"_index":"' . $index_name . '","_type":"post","_id":"' . $ep_id . '","_version":1,"created":true}',
			'response' => array(
				'code' => 200,
				'message' => 'OK',
			),
			'cookies' => array(),
			'filename' => null,
		);

		$this->wp_remote_request_mock['args'] = array( $host . '/' . $index_name . '/post/' . $ep_id );

		$this->wp_remote_request_mock['return'] = $response;

		wp_publish_post( $post_id );

		if ( $site_id != null ) {
			restore_current_blog();
		}

		return $post_id;
	}

	/**
	 * Simple test to ensure single site configuration consistency
	 *
	 * @aince 0.1.0
	 */
	public function testSingleSiteConfigSet() {
		$config = $this->_configureSingleSite();

		$current_site_id = get_current_blog_id();

		$option = get_site_option( 'ep_config_by_site', array() );

		$this->assertTrue( isset( $option[$current_site_id] ) );

		foreach ( $option[$current_site_id] as $key => $value ) {
			$this->assertEquals( $option[$current_site_id][$key], $config[$key] );
		}
	}

	/**
	 * Simple test to ensure multisite configuration consistency
	 *
	 * @aince 0.1.0
	 */
	public function testMultiSiteConfigSet() {
		$config = $this->_configureMultiSite();

		$option = get_site_option( 'ep_config_by_site', array() );

		$this->assertTrue( count( $option ) > 1 );

		foreach ( $option as $site_id => $site_config ) {

			foreach ( $site_config as $key => $value ) {
				$this->assertEquals( $site_config[$key], $config[$site_id][$key] );
			}
		}
	}

	/**
	 * Test a simple single site search. This test runs a simple search on post_content
	 *
	 * @since 0.1.0
	 */
	public function testSingleSiteSearchBasic() {
		$config = $this->_configureSingleSite();

		$post_id = $this->_createAndSyncPost();
		$post = get_post( $post_id );

		$response = array(
			'headers' => array(
				'content-type' => 'application/json; charset=UTF-8',
				'content-length' => '*',
			),
			'body' => '{"took":3,"timed_out":false,"_shards":{"total":5,"successful":5,"failed":0},"hits":{"total":1,"max_score":1,"hits":[{"_index":"test-index","_type":"post","_id":"' . $post_id . '","_score":1,"_source":{"post_id":' . $post_id . ',"post_author":{"login":"admin","display_name":"admin"},"post_date":"2014-03-18 14:14:00","post_date_gmt":"2014-03-18 14:14:00","post_title":"' . get_the_title( $post_id ) . '","post_excerpt":"' . apply_filters( 'the_excerpt', $post->post_excerpt ) . '","post_content":"' . apply_filters( 'the_content', $post->post_content ) . '","post_status":"' . get_post_status( $post_id ) . '","post_name":"test-post","post_modified":"2014-03-18 14:14:00","post_modified_gmt":"2014-03-18 14:14:00","post_parent":0,"post_type":"' . get_post_type( $post_id ) . '","post_mime_type":"","permalink":"' . get_permalink( $post_id ) . '","site_id":' . get_current_blog_id() . '}}]}}',
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
		$query = new EP_Query( $args );

		$this->assertEquals( $query->post_count, 1 );

		while ( $query->have_posts() ) {
			$query->the_post();

			$this->assertEquals( get_the_title( $post_id ), get_the_title() );
		}
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

		$post_id = $this->_createAndSyncPost();

		// Let's test to see if this post was sent to the index

		$ep_id = get_post_meta( $post_id, 'ep_id', true );

		$this->assertEquals( $ep_id, $post_id );

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

			$post_ids_by_site[$site['blog_id']][] = $this->_createAndSyncPost( array(), $site['blog_id'], true );
			$post_ids_by_site[$site['blog_id']][] = $this->_createAndSyncPost( array(), $site['blog_id'], true );
			$post_ids_by_site[$site['blog_id']][] = $this->_createAndSyncPost( array(), $site['blog_id'], true );
		}


		// Let's test to see if this post was sent to the index

		foreach( $post_ids_by_site as $blog_id => $post_ids ) {

			switch_to_blog( $blog_id );

			foreach( $post_ids as $post_id ) {
				$ep_id = get_post_meta( $post_id, 'ep_id', true );
				$correct_ep_id = ( $blog_id <= 1 ) ? $post_id : $blog_id . 'ms' . $post_id;

				$this->assertEquals( $ep_id, $correct_ep_id );

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
}