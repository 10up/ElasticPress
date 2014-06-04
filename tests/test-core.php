<?php

class ESTestCore extends PHPUnit_Framework_TestCase {

	protected $wp_function_mocks = array(
		'wp_remote_request' => array(
			'return' => false,
			'args' => false,
		),
	);

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
	}

	public function _configureSingleSite() {
		$config = array(
			'post_types' => array( 'post' ),
			'host' => 'http://127.0.0.1:9200',
			'index_name' => 'test-index',
		);

		es_update_option( $config );

		$current_site_id = get_current_blog_id();

		$option = get_site_option( 'es_config_by_site', array() );

		$this->assertTrue( isset( $option[$current_site_id] ) );

		foreach ( $option[$current_site_id] as $key => $value ) {
			$this->assertEquals( $option[$current_site_id][$key], $config[$key] );
		}

		return $config;
	}

	public function testPostCreateDeleteSync() {
		$config = $this->_configureSingleSite();

		// First let's create a post to play with

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

		// Let's test to see if this post was sent to the index

		$es_id = get_post_meta( $post_id, 'es_id', true );

		$this->assertEquals( $es_id, $post_id );


		// Now let's delete the post. We aren't actually testing what this does
		// since deleting an ES post isn't recorded in WP

		wp_delete_post( $post_id );

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