<?php

class ESTestCore extends WP_UnitTestCase {

	protected $wp_remote_request_mock = array(
		'return' => false,
		'args' => false,
	);

	protected $fired_actions = array();

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

	public function tearDown() {
		parent::tearDown();

		$this->wp_remote_request_mock['args'] = false;
		$this->wp_remote_request_mock['return'] = false;
		$this->fired_actions = array();
	}

	protected function _configureSingleSite() {
		$config = array(
			'post_types' => array( 'post' ),
			'host' => 'http://127.0.0.1:9200',
			'index_name' => 'test-index',
		);

		es_update_option( $config );

		return $config;
	}

	protected function _configureMultiSite() {
		$config = array();

		$config[0] = array(
			'host' => 'http://127.0.0.1:9200',
			'index_name' => 'test-index',
			'cross_site_search_active' => 1,
		);

		es_update_option( $config[0], 0 );

		$config[1] = array(
			'host' => 'http://888.0.0.1:9100',
			'post_types' => array( 'post' ),
			'index_name' => 'test-index-1',
		);

		es_update_option( $config[1], 1 );

		$blog_ids = $this->factory->blog->create_many( 2 );

		$i = 2;

		foreach ( $blog_ids as $blog_id ) {
			$config[$blog_id] = array(
				'host' => 'http://999.0.0.1:9100',
				'post_types' => ( $i % 2 == 0 ) ? array( 'post', 'page' ) : array( 'page' ),
				'index_name' => 'test-index-' . $i,
			);

			es_update_option( $config[$blog_id], $blog_id );

			$i++;
		}

		return $config;
	}

	protected function _createAndSyncPost( $site_id = null, $cross_site = false ) {
		if ( $site_id != null ) {
			switch_to_blog( $site_id );
		}

		$config = es_get_option( $site_id );
		$index_name = $config['index_name'];
		$host = $config['host'];

		if ( $cross_site ) {
			$global_config = es_get_option( 0 );
			$index_name = $global_config['index_name'];
			$host = $global_config['host'];
		}

		$post_id = wp_insert_post( array(
			'post_type' => $config['post_types'][0],
			'post_status' => 'draft',
			'author' => 1,
			'post_title' => 'Test Post ' . time(),
		) );

		$es_id = $post_id;
		if ( $site_id > 1 ) {
			$es_id = $site_id . 'ms' . $post_id;
		}

		$response = array(
			'headers' => array(
				'content-type' => 'application/json; charset=UTF-8',
				'content-length' => '*',
			),
			'body' => '{"_index":"' . $index_name . '","_type":"post","_id":"' . $es_id . '","_version":1,"created":true}',
			'response' => array(
				'code' => 200,
				'message' => 'OK',
			),
			'cookies' => array(),
			'filename' => null,
		);

		$this->wp_remote_request_mock['args'] = array( $host . '/' . $index_name . '/post/' . $es_id );

		$this->wp_remote_request_mock['return'] = $response;

		wp_publish_post( $post_id );

		if ( $site_id != null ) {
			restore_current_blog();
		}

		return $post_id;
	}

	public function testSingleSiteConfigSet() {
		$config = $this->_configureSingleSite();

		$current_site_id = get_current_blog_id();

		$option = get_site_option( 'es_config_by_site', array() );

		$this->assertTrue( isset( $option[$current_site_id] ) );

		foreach ( $option[$current_site_id] as $key => $value ) {
			$this->assertEquals( $option[$current_site_id][$key], $config[$key] );
		}
	}

	public function testMultiSiteConfigSet() {
		$config = $this->_configureMultiSite();

		$option = get_site_option( 'es_config_by_site', array() );

		$this->assertTrue( count( $option ) > 1 );

		foreach ( $option as $site_id => $site_config ) {

			foreach ( $site_config as $key => $value ) {
				$this->assertEquals( $site_config[$key], $config[$site_id][$key] );
			}
		}
	}

	public function testSingleSiteSearchBasic() {
		$config = $this->_configureSingleSite();

		$post_id = $this->_createAndSyncPost();
		$post = get_post( $post_id );

		$response = array(
			'headers' => array(
				'content-type' => 'application/json; charset=UTF-8',
				'content-length' => '*',
			),
			'body' => '{"took":3,"timed_out":false,"_shards":{"total":5,"successful":5,"failed":0},"hits":{"total":1,"max_score":1,"hits":[{"_index":"vipqs","_type":"post","_id":"' . $post_id . '","_score":1,"_source":{"post_id":' . $post_id . ',"post_author":{"login":"admin","display_name":"admin"},"post_date":"2014-03-18 14:14:00","post_date_gmt":"2014-03-18 14:14:00","post_title":"' . get_the_title( $post_id ) . '","post_excerpt":"' . apply_filters( 'the_excerpt', $post->post_excerpt ) . '","post_content":"' . apply_filters( 'the_content', $post->post_content ) . '","post_status":"' . get_post_status( $post_id ) . '","post_name":"test-post","post_modified":"2014-03-18 14:14:00","post_modified_gmt":"2014-03-18 14:14:00","post_parent":0,"post_type":"' . get_post_type( $post_id ) . '","post_mime_type":"","permalink":"' . get_permalink( $post_id ) . '","site_id":' . get_current_blog_id() . '}}]}}',
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
		$query = new ES_Query( $args );

		$this->assertEquals( $query->post_count, 1 );

		while ( $query->have_posts() ) {
			$query->the_post();

			$this->assertEquals( get_the_title( $post_id ), get_the_title() );
		}
	}

	public function testSingleSitePostCreateDeleteSync() {
		$config = $this->_configureSingleSite();

		// First let's create a post to play with

		$post_id = $this->_createAndSyncPost();

		// Let's test to see if this post was sent to the index

		$es_id = get_post_meta( $post_id, 'es_id', true );

		$this->assertEquals( $es_id, $post_id );

		add_action( 'es_delete_post', function( $args ) {
			$this->fired_actions['es_delete_post'] = true;
		} );

		wp_delete_post( $post_id );

		// Check if ES delete action has been properly fired

		$this->assertTrue( ! empty( $this->fired_actions['es_delete_post'] ) );

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

		$post_indexed = es_post_indexed( $post_id );

		$this->assertFalse( $post_indexed );
	}

	public function testMultiSitePostCreateDeleteSync() {
		$config = $this->_configureMultiSite();

		// First let's create some posts across the network
		$post_ids_by_site = array();

		$sites = wp_get_sites();
		foreach ( $sites as $site ) {
			$post_ids_by_site[$site['blog_id']] = array();

			$post_ids_by_site[$site['blog_id']][] = $this->_createAndSyncPost( $site['blog_id'], true );
			$post_ids_by_site[$site['blog_id']][] = $this->_createAndSyncPost( $site['blog_id'], true );
			$post_ids_by_site[$site['blog_id']][] = $this->_createAndSyncPost( $site['blog_id'], true );
		}


		// Let's test to see if this post was sent to the index

		foreach( $post_ids_by_site as $blog_id => $post_ids ) {

			switch_to_blog( $blog_id );

			foreach( $post_ids as $post_id ) {
				$es_id = get_post_meta( $post_id, 'es_id', true );
				$correct_es_id = ( $blog_id <= 1 ) ? $post_id : $blog_id . 'ms' . $post_id;

				$this->assertEquals( $es_id, $correct_es_id );

				add_action( 'es_delete_post', function( $args ) {
					$this->fired_actions['es_delete_post'] = true;
				} );

				wp_delete_post( $post_id );

				// Check if ES delete action has been properly fired

				$this->assertTrue( ! empty( $this->fired_actions['es_delete_post'] ) );

				// Now let's make sure the post is not indexed

				$response = array(
					'headers' => array(
						'content-type' => 'application/json; charset=UTF-8',
						'content-length' => '*',
					),
					'body' => '{"_index":"test-index","_type":"post","_id":"' . $es_id . '","found":false}',
					'response' => array(
						'code' => 404,
						'message' => 'Not Found',
					),
					'cookies' => array(),
					'filename' => null,
				);

				$this->wp_remote_request_mock['args'] = array( $config[0]['host'] . '/' . $config[0]['index_name'] . '/post/' . $es_id );
				$this->wp_remote_request_mock['return'] = $response;

				$post_indexed = es_post_indexed( $post_id, $blog_id, 0 );

				$this->assertFalse( $post_indexed );

				$this->wp_remote_request_mock['args'] = false;
				$this->wp_remote_request_mock['return'] = false;
				$this->fired_actions = array();
				remove_all_actions( 'es_delete_post' );
			}

			restore_current_blog();
		}
	}
}