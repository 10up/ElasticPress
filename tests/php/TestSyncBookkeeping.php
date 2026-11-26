<?php
/**
 * Sync requirement fulfillment tests.
 *
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress\IndexHelper;
use ElasticPress\Utils;

/**
 * Requirements are fulfilled only by successful complete jobs.
 */
class TestSyncBookkeeping extends BaseTestCase {
	/**
	 * Seed outstanding requirements.
	 */
	private function seed_requirements(): void {
		Utils\update_option( 'ep_last_sync', 123 );
		Utils\update_option( 'ep_need_upgrade_sync', true );
		Utils\update_option( 'ep_feature_auto_activated_sync', 'woocommerce' );
		Utils\delete_option( 'ep_feature_settings_draft' );
	}

	/**
	 * Start a job without processing its content.
	 *
	 * @param array $args Job arguments.
	 * @return IndexHelper Helper instance.
	 */
	private function start_job( array $args = [] ): IndexHelper {
		$helper = new IndexHelper();
		$this->set_property(
			$helper,
			'args',
			array_merge(
				[
					'method'        => 'dashboard',
					'network_wide'  => defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ? 0 : null,
					'site_context'  => true,
					'output_method' => static function () {},
				],
				$args
			)
		);
		$this->invoke( $helper, 'build_index_meta' );

		return $helper;
	}

	/**
	 * Set helper state for isolated continuation/failure tests.
	 *
	 * @param IndexHelper $helper Helper.
	 * @param string      $name Property.
	 * @param mixed       $value Value.
	 */
	private function set_property( IndexHelper $helper, string $name, $value ): void {
		$property = new \ReflectionProperty( $helper, $name );
		$property->setAccessible( true );
		$property->setValue( $helper, $value );
	}

	/**
	 * Read saved job state.
	 *
	 * @param IndexHelper $helper Helper.
	 * @return array Job state.
	 */
	private function meta( IndexHelper $helper ): array {
		$property = new \ReflectionProperty( $helper, 'index_meta' );
		$property->setAccessible( true );

		return $property->getValue( $helper );
	}

	/**
	 * Invoke a lifecycle method without exposing production internals.
	 *
	 * @param IndexHelper $helper Helper.
	 * @param string      $name Method.
	 * @param array       $args Arguments.
	 */
	private function invoke( IndexHelper $helper, string $name, array $args = [] ): void {
		$method = new \ReflectionMethod( $helper, $name );
		$method->setAccessible( true );
		$method->invokeArgs( $helper, $args );
	}

	/**
	 * Queue creation and cancellation retain all requirements.
	 *
	 * @group sync-bookkeeping
	 */
	public function test_queue_and_stop_do_not_fulfill_requirements() {
		$this->seed_requirements();
		$helper = $this->start_job();
		$this->assertSame( 123, Utils\get_option( 'ep_last_sync' ) );
		$this->assertTrue( Utils\get_option( 'ep_need_upgrade_sync' ) );
		$this->assertSame( 'woocommerce', Utils\get_option( 'ep_feature_auto_activated_sync' ) );
		$helper->clear_index_meta();
		$this->assertTrue( Utils\get_option( 'ep_need_upgrade_sync' ) );
		$this->assertSame( 123, Utils\get_option( 'ep_last_sync' ) );
	}

	/**
	 * Successful ordinary work fulfills only an already-applied feature requirement.
	 *
	 * @group sync-bookkeeping
	 */
	public function test_complete_ordinary_job_preserves_upgrade_requirement() {
		$this->seed_requirements();
		$helper = $this->start_job();
		$this->seed_requirements();
		$this->invoke( $helper, 'full_index_complete' );
		$this->assertTrue( Utils\get_option( 'ep_need_upgrade_sync' ) );
		$this->assertFalse( Utils\get_option( 'ep_feature_auto_activated_sync', false ) );
		$this->assertGreaterThan( 123, Utils\get_option( 'ep_last_sync' ) );
	}

	/**
	 * Completion uses persisted coverage and rebuild mode, not continuation args.
	 *
	 * @group sync-bookkeeping
	 */
	public function test_fresh_completion_uses_saved_job_evidence() {
		$this->seed_requirements();
		$helper = $this->start_job( [ 'put_mapping' => true ] );
		$this->seed_requirements();
		$resumed = new IndexHelper();
		$this->set_property( $resumed, 'index_meta', $this->meta( $helper ) );
		$this->set_property(
			$resumed,
			'args',
			[
				'method'        => 'dashboard',
				'site_context'  => true,
				'output_method' => static function () {},
			]
		);
		$this->invoke( $resumed, 'full_index_complete' );
		$this->assertFalse( Utils\get_option( 'ep_need_upgrade_sync', false ) );
		$this->assertFalse( Utils\get_option( 'ep_feature_auto_activated_sync', false ) );
		$this->assertGreaterThan( 123, Utils\get_option( 'ep_last_sync' ) );
	}

	/**
	 * Selected sites and restricted content cannot fulfill network requirements.
	 *
	 * @group sync-bookkeeping
	 */
	public function test_partial_jobs_retain_requirements() {
		$restrictions = [
			[ 'site_id' => get_current_blog_id() ],
			[ 'network_wide' => 1 ],
			[ 'network_wide' => null ],
			[ 'indexables' => [ 'post' ] ],
			[ 'post_type' => [ 'post' ] ],
			[ 'include' => [ 1 ] ],
			[ 'post-ids' => [ 1 ] ],
			[ 'offset' => 1 ],
			[ 'lower_limit_object_id' => 1 ],
			[ 'upper_limit_object_id' => 100 ],
		];

		foreach ( $restrictions as $args ) {
			$this->seed_requirements();
			$helper = $this->start_job( $args + [ 'put_mapping' => true ] );
			$this->seed_requirements();
			$this->invoke( $helper, 'full_index_complete' );
			$this->assertSame( 123, Utils\get_option( 'ep_last_sync' ) );
			$this->assertTrue( Utils\get_option( 'ep_need_upgrade_sync' ) );
			$this->assertSame( 'woocommerce', Utils\get_option( 'ep_feature_auto_activated_sync' ) );
		}
	}

	/**
	 * Generic errors and object failures both prevent fulfillment.
	 *
	 * @group sync-bookkeeping
	 */
	public function test_errors_and_legacy_jobs_retain_requirements() {
		foreach ( [ 'objects', 'generic', 'legacy' ] as $case ) {
			$this->seed_requirements();
			$helper = $this->start_job( [ 'put_mapping' => true ] );
			$this->seed_requirements();
			$meta = $this->meta( $helper );

			if ( 'objects' === $case ) {
				$meta['totals']['failed'] = 1;
			} elseif ( 'legacy' === $case ) {
				unset( $meta['bookkeeping'] );
			}

			$this->set_property( $helper, 'index_meta', $meta );

			if ( 'generic' === $case ) {
				$this->invoke( $helper, 'output_error', [ 'Network alias creation failed' ] );
			}

			$this->invoke( $helper, 'full_index_complete' );
			$this->assertSame( 123, Utils\get_option( 'ep_last_sync' ) );
			$this->assertTrue( Utils\get_option( 'ep_need_upgrade_sync' ) );
		}
	}

	/**
	 * Pending drafts and a newer requirement must not be silently satisfied.
	 *
	 * @group sync-bookkeeping
	 */
	public function test_pending_settings_and_new_requirement_are_preserved() {
		$this->seed_requirements();
		Utils\update_option( 'ep_feature_settings_draft', [ 'woocommerce' => [ 'orders' => '1' ] ] );
		$helper = $this->start_job();
		Utils\update_option( 'ep_feature_auto_activated_sync', 'woocommerce' );
		$this->invoke( $helper, 'full_index_complete' );
		$this->assertSame( 'woocommerce', Utils\get_option( 'ep_feature_auto_activated_sync' ) );

		$this->seed_requirements();
		$helper = $this->start_job();
		Utils\update_option( 'ep_feature_settings_draft', [ 'woocommerce' => [ 'orders' => '1' ] ] );
		$this->invoke( $helper, 'full_index_complete' );
		$this->assertSame( 'woocommerce', Utils\get_option( 'ep_feature_auto_activated_sync' ) );
		$this->assertSame( 123, Utils\get_option( 'ep_last_sync' ) );

		$this->seed_requirements();
		$helper = $this->start_job( [ 'put_mapping' => true ] );
		Utils\update_option( 'ep_feature_auto_activated_sync', 'comments' );
		$this->invoke( $helper, 'full_index_complete' );
		$this->assertSame( 'comments', Utils\get_option( 'ep_feature_auto_activated_sync' ) );
	}

	/**
	 * Initial setup does not become complete just because a queue exists.
	 *
	 * @group sync-bookkeeping
	 */
	public function test_initial_setup_marker_is_absent_until_completion() {
		$this->seed_requirements();
		Utils\delete_option( 'ep_last_sync' );
		$helper = $this->start_job( [ 'put_mapping' => true ] );
		$this->assertFalse( Utils\get_option( 'ep_last_sync', false ) );
		$this->invoke( $helper, 'full_index_complete' );
		$this->assertNotEmpty( Utils\get_option( 'ep_last_sync', false ) );
	}
}
