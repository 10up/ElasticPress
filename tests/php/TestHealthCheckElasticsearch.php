<?php
/**
 * Test health check elasticsearch functionality.
 *
 * @since 4.4.1
 * @package elasticpress
 */

namespace ElasticPressTest;

use WP_Ajax_UnitTestCase;
use WPAjaxDieContinueException;

/**
 *  Health check elasticsearch test class
 */
class TestHealthCheckElasticsearch extends WP_Ajax_UnitTestCase {

	/**
	 * Test if the test is registered
	 */
	public function testIsRegistered() {
		$tests = \WP_Site_Health::get_tests();

		$this->assertArrayHasKey( 'elasticpress-health-check-elasticsearch', $tests['async'] );
	}

	/**
	 * Test ajax output.
	 */
	public function testAjaxOutput() {
		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		// Make the request.
		try {
			$this->_handleAjax( 'health-check-elasticpress-health-check-elasticsearch' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertEquals( true, $response['success'] );
		$this->assertEquals( 'Your site can connect to Elasticsearch.', $response['data']['label'] );
		$this->assertEquals( 'good', $response['data']['status'] );
		$this->assertEquals( 'ElasticPress', $response['data']['badge']['label'] );
		$this->assertEquals( 'green', $response['data']['badge']['color'] );
	}

	/**
	 * Test ajax output when host is not set.
	 */
	public function testAjaxOutPutWhenHostIsNotSet() {
		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		add_filter( 'ep_host', '__return_empty_string' );

		// Make the request.
		try {
			$this->_handleAjax( 'health-check-elasticpress-health-check-elasticsearch' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertEquals( true, $response['success'] );
		$this->assertEquals( 'Your site could not connect to Elasticsearch', $response['data']['label'] );
		$this->assertEquals( 'critical', $response['data']['status'] );
		$this->assertEquals( 'ElasticPress', $response['data']['badge']['label'] );
		$this->assertEquals( 'red', $response['data']['badge']['color'] );
		$this->assertEquals( 'The Elasticsearch host is not set.', $response['data']['description'] );

		remove_filter( 'ep_host', '__return_empty_string' );
	}

	/**
	 * Test ajax output when host is not valid.
	 */
	public function testAjaxOutPutWhenHostIsNotValid() {
		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		add_filter( 'ep_elasticsearch_version', '__return_false' );

		// Make the request.
		try {
			$this->_handleAjax( 'health-check-elasticpress-health-check-elasticsearch' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertEquals( true, $response['success'] );
		$this->assertEquals( 'Your site could not connect to Elasticsearch', $response['data']['label'] );
		$this->assertEquals( 'critical', $response['data']['status'] );
		$this->assertEquals( 'ElasticPress', $response['data']['badge']['label'] );
		$this->assertEquals( 'red', $response['data']['badge']['color'] );
		$this->assertEquals( 'Check if your Elasticsearch host URL is correct and you have the right access to the host.', $response['data']['description'] );

		remove_filter( 'ep_elasticsearch_version', '__return_false' );
	}

	/**
	 * Test ajax output when elasticpress.io host is not valid.
	 */
	public function testAjaxOutPutWhenEpioHostIsNotValid() {
		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$ep_host = function () {
			return 'elasticpress.io/random-string';
		};
		add_filter( 'ep_host', $ep_host );
		add_filter( 'ep_elasticsearch_version', '__return_false' );

		// Make the request.
		try {
			$this->_handleAjax( 'health-check-elasticpress-health-check-elasticsearch' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertEquals( true, $response['success'] );
		$this->assertEquals( 'Your site could not connect to Elasticsearch', $response['data']['label'] );
		$this->assertEquals( 'critical', $response['data']['status'] );
		$this->assertEquals( 'ElasticPress', $response['data']['badge']['label'] );
		$this->assertEquals( 'red', $response['data']['badge']['color'] );
		$this->assertEquals( 'Check if your credentials to ElasticPress.io host are correct.', $response['data']['description'] );

		remove_filter( 'ep_host', $ep_host );
		remove_filter( 'ep_elasticsearch_version', '__return_false' );
	}
}
