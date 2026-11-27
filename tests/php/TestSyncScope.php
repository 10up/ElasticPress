<?php
/**
 * Sync scope persistence and reporting tests.
 *
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress\IndexHelper;
use ElasticPress\StatusReport\LastSync;
use ElasticPress\Utils;

/**
 * Target scope must remain independent of rebuild mode.
 */
class TestSyncScope extends BaseTestCase {
	/**
	 * Build a job without processing its targets.
	 *
	 * @param array $args Job arguments.
	 * @return array Saved job metadata.
	 */
	private function build_job( array $args ): array {
		$helper = new IndexHelper();
		$this->set_property(
			$helper,
			'args',
			$args + [
				'site_context' => true,
				'method'       => 'web',
			]
		);
		$this->invoke( $helper, 'build_index_meta' );
		$property = new \ReflectionProperty( $helper, 'index_meta' );
		$property->setAccessible( true );

		return $property->getValue( $helper );
	}

	/**
	 * Set isolated lifecycle state.
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
	 * Invoke a lifecycle method.
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
	 * Selected scope survives consumed queues, continuation, and changed site names.
	 *
	 * @group sync-scope
	 */
	public function test_selected_scope_survives_continuation() {
		$site_id = self::factory()->blog->create( [ 'title' => 'Original target' ] );

		foreach ( [ false, true ] as $fresh ) {
			$meta = $this->build_job(
				[
					'network_wide' => 0,
					'site_id'      => $site_id,
					'put_mapping'  => $fresh,
				]
			);
			$this->assertSame( [ $site_id ], array_column( $meta['scope']['sites'], 'id' ) );
			$this->assertSame( [], $meta['scope']['shared'] );
			$scope              = $meta['scope'];
			$meta['sync_stack'] = [];
			update_blog_option( $site_id, 'blogname', 'Renamed target' );
			$resumed = new IndexHelper();
			$this->set_property( $resumed, 'index_meta', $meta );
			$this->invoke( $resumed, 'update_last_index' );
			$saved = $resumed->get_last_sync();
			$this->assertSame( $scope, $saved['scope'] );
			$this->assertSame( $fresh, $saved['is_full_sync'] );
			$this->assertSame( $scope, Utils\get_option( 'ep_last_cli_index' )['scope'] );
		}
	}

	/**
	 * CLI current-site work does not imply a network run.
	 *
	 * @group sync-scope
	 */
	public function test_cli_current_site_scope() {
		$site_id = self::factory()->blog->create();
		switch_to_blog( $site_id );
		$meta = $this->build_job(
			[
				'method'     => 'cli',
				'indexables' => [ 'post' ],
			]
		);
		restore_current_blog();
		$this->assertSame( [ $site_id ], array_column( $meta['scope']['sites'], 'id' ) );
		$this->assertSame( [], $meta['scope']['shared'] );
	}

	/**
	 * Network scope records resolved eligible targets, including count limits.
	 *
	 * @group sync-scope
	 */
	public function test_network_scope_uses_resolved_sites() {
		self::factory()->blog->create();
		$excluded = self::factory()->blog->create();
		update_site_meta( $excluded, 'ep_indexable', 'no' );

		foreach ( [ 0, 1 ] as $limit ) {
			$meta     = $this->build_job(
				[
					'network_wide' => $limit,
					'indexables'   => [ 'post' ],
				]
			);
			$expected = array_map( 'intval', array_column( Utils\get_sites( $limit, true ), 'blog_id' ) );
			$this->assertSame( $expected, array_column( $meta['scope']['sites'], 'id' ) );
			$this->assertNotContains( $excluded, array_column( $meta['scope']['sites'], 'id' ) );
		}
	}

	/**
	 * Shared-only jobs do not claim site-content scope.
	 *
	 * @group sync-scope
	 */
	public function test_shared_only_scope_and_mixed_cli_scope() {
		$meta = $this->build_job(
			[
				'network_wide' => 0,
				'indexables'   => [ 'global' ],
			]
		);
		$this->assertSame( [], $meta['scope']['sites'] );
		$this->assertSame( [ 'Global' ], $meta['scope']['shared'] );
		$meta = $this->build_job(
			[
				'method'     => 'cli',
				'indexables' => [ 'post', 'global' ],
			]
		);
		$this->assertSame( [ get_current_blog_id() ], array_column( $meta['scope']['sites'], 'id' ) );
		$this->assertSame( [ 'Global' ], $meta['scope']['shared'] );
	}

	/**
	 * Reports retain known mode and do not invent missing legacy scope.
	 *
	 * @group sync-scope
	 */
	public function test_report_formats_scope_and_legacy_records() {
		$meta                              = $this->build_job( [ 'indexables' => [ 'post' ] ] );
		$meta['scope']['sites'][0]['name'] = '<b>Saved name</b>';
		$meta['sync_stack']                = [];
		$helper                            = new IndexHelper();
		$this->set_property( $helper, 'index_meta', $meta );
		$this->invoke( $helper, 'update_last_index', [ 'aborted' ] );
		$report = new LastSync();
		$fields = $report->get_groups()[0]['fields'];
		$this->assertSame( 'Ordinary sync', $fields['is_full_sync']['value'] );
		$this->assertStringContainsString( '&lt;b&gt;Saved name&lt;/b&gt;', $fields['scope']['value'] );
		$this->assertStringContainsString( 'ID ' . get_current_blog_id(), $fields['scope']['value'] );
		$this->assertSame( 'aborted', $fields['final_status']['value'] );
		unset( $meta['scope'] );
		$meta['put_mapping'] = true;
		$this->set_property( $helper, 'index_meta', $meta );
		$this->invoke( $helper, 'update_last_index' );
		$fields = $report->get_groups()[0]['fields'];
		$this->assertSame( 'Not recorded', $fields['scope']['value'] );
		$this->assertSame( 'Fresh rebuild', $fields['is_full_sync']['value'] );
	}
}
