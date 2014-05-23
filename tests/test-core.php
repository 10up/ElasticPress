<?php

class ESTestCore extends PHPUnit_Framework_TestCase {
	public function setUp() {
		$user = @wp_signon(
			array(
				'user_login' => 'admin',
				'user_password' => 'password'
			)
		);

		wp_set_current_user( $user->ID );
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

	public function testPostSync() {
		$config = $this->_configureSingleSite();

		$post_id = wp_insert_post( array(
			'post_type' => 'post',
			'post_status' => 'draft',
			'author' => 1,
			'post_title' => 'Test Post'
		) );

		$post = get_post( $post_id, ARRAY_A );

		$response = array(
			'headers' => array(
				'content-type' => 'application/json; charset=UTF-8',
				'content-length' => '75',
			),
			'body' => '{"_index":"test-index","_type":"post","_id":"' . $post_id . '","_version":1,"created":true}',
			'response' => array(
				'code' => 200,
				'message' => 'OK',
			),
			'cookies' => array(),
			'filename' => null,
		);

		\WP_Mock::wpFunction( 'wp_remote_request', array( 'return' => 5 ) );

		$test = get_post( 1 );
		var_dump( $test );

		wp_publish_post( $post_id );

		$es_id = get_post_meta( $post_id, 'es_id', true );

		$this->assertEquals( $es_id, 1 );
	}
}