<?php

class ESTestCore extends WP_UnitTestCase {

	protected $wp_remote_request_mock = array(
		'return' => false,
		'args' => false,
	);

	protected $fired_actions = array();

	public function setUp() {
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

	protected function _createAndSyncPost( $config ) {
		$post_id = wp_insert_post( array(
			'post_type' => 'post',
			'post_status' => 'draft',
			'author' => 1,
			'post_title' => 'Test Post'
		) );

		$response = array(
			'headers' => array(
				'content-type' => 'application/json; charset=UTF-8',
				'content-length' => '*',
			),
			'body' => '{"_index":"test-index","_type":"post","_id":"' . $post_id . '","_version":1,"created":true}',
			'response' => array(
				'code' => 200,
				'message' => 'OK',
			),
			'cookies' => array(),
			'filename' => null,
		);

		$this->wp_remote_request_mock['args'] = array( $config['host'] . '/' . $config['index_name'] . '/post/' . $post_id );
		$this->wp_remote_request_mock['return'] = $response;

		wp_publish_post( $post_id );

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

	public function testSingleSiteSearchBasic() {
		$config = $this->_configureSingleSite();

		$post_id = $this->_createAndSyncPost( $config );
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

	public function testPostCreateDeleteSync() {
		$config = $this->_configureSingleSite();

		// First let's create a post to play with

		$post_id = $this->_createAndSyncPost( $config );

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
}