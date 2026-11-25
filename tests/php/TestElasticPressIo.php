<?php
/**
 * Test the ElasticPressIo class methods.
 *
 * @since 5.3.6
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress\ElasticPressIo;

/**
 * TestElasticPressIo test class
 */
class TestElasticPressIo extends BaseTestCase {

	/**
	 * Setup the test environment
	 */
	public function set_up(): void {
		delete_transient( ElasticPressIo::STATUS_TRANSIENT_NAME );
		parent::set_up();
	}

	/**
	 * Tear down the test environment
	 */
	public function tear_down(): void {
		delete_transient( ElasticPressIo::STATUS_TRANSIENT_NAME );
		putenv( 'IS_EPIO_ENVIRONMENT' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv
		parent::tear_down();
	}

	/**
	 * Test that methods return empty structures when not on an ElasticPress.io environment.
	 *
	 * @since 5.3.6
	 * @group epio
	 */
	public function test_non_epio_environment_returns_empty() {
		putenv( 'IS_EPIO_ENVIRONMENT=0' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv
		update_option( 'ep_host', 'http://127.0.0.1:9200' );

		$epio = new ElasticPressIo();

		$this->assertSame( [], $epio->get_endpoint_status() );
		$this->assertSame( [], $epio->get_endpoint_messages() );
		$this->assertSame( [], $epio->get_endpoint_available_services() );
		$this->assertFalse( $epio->is_service_available( 'synonyms' ) );
	}

	/**
	 * Test retrieving endpoint status, messages, and services from cached transient.
	 *
	 * @since 5.3.6
	 * @group epio
	 */
	public function test_get_endpoint_status_from_transient() {
		putenv( 'IS_EPIO_ENVIRONMENT=1' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv

		$expected_status = [
			'messages'         => [
				'System operational',
				'Maintenance complete',
			],
			'avaiableServices' => [
				'synonyms'     => true,
				'autosuggest'  => true,
				'disabled_svc' => false,
				'empty_svc'    => '',
			],
		];

		set_transient( ElasticPressIo::STATUS_TRANSIENT_NAME, $expected_status );

		$epio = new ElasticPressIo();

		$status = $epio->get_endpoint_status();
		$this->assertSame( $expected_status, $status );

		$messages = $epio->get_endpoint_messages();
		$this->assertSame( $expected_status['messages'], $messages );

		$services = $epio->get_endpoint_available_services();
		$this->assertSame( $expected_status['avaiableServices'], $services );

		$this->assertTrue( $epio->is_service_available( 'synonyms' ) );
		$this->assertTrue( $epio->is_service_available( 'autosuggest' ) );
		$this->assertFalse( $epio->is_service_available( 'disabled_svc' ) );
		$this->assertFalse( $epio->is_service_available( 'empty_svc' ) );
		$this->assertFalse( $epio->is_service_available( 'non_existent_service' ) );
	}
}
