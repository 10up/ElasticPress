<?php
/**
 * Test the failed writes journal.
 *
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress\FailedWrites;

/**
 * FailedWrites test class
 */
class TestFailedWrites extends BaseTestCase {

	/**
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();
		$this->create_table();
	}

	/**
	 * Tear down the test.
	 */
	public function tear_down() {
		$this->drop_table();
		parent::tear_down();
	}

	/**
	 * Create the table via dbDelta.
	 */
	protected function create_table() {
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
		dbDelta( FailedWrites::get_table_schema() );
	}

	/**
	 * Drop the journal table.
	 */
	protected function drop_table() {
		global $wpdb;
		$table = FailedWrites::get_table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}

	/**
	 * Test record() inserts a new row.
	 */
	public function testRecordInserts() {
		$journal = FailedWrites::factory();

		$id = $journal->record( 'post', 123, 'index', 'connection refused' );

		$this->assertIsInt( $id );
		$this->assertGreaterThan( 0, $id );

		$row = $journal->get_pending( 10 );

		$this->assertCount( 1, $row );
		$this->assertSame( 'post', $row[0]->indexable_slug );
		$this->assertSame( 123, (int) $row[0]->object_id );
		$this->assertSame( 'index', $row[0]->action );
		$this->assertSame( 'connection refused', $row[0]->error_message );
	}

	/**
	 * Test record() dedupes by slug + object_id.
	 */
	public function testRecordDedupes() {
		$journal = FailedWrites::factory();

		$first  = $journal->record( 'post', 123, 'index', 'first' );
		$second = $journal->record( 'post', 123, 'index', 'second' );

		$this->assertSame( $first, $second );

		$rows = $journal->get_pending( 10 );
		$this->assertCount( 1, $rows );
		$this->assertSame( 'second', $rows[0]->error_message );
	}

	/**
	 * Test record() keeps distinct slugs/ids.
	 */
	public function testRecordKeepsDistinctRows() {
		$journal = FailedWrites::factory();

		$journal->record( 'post', 1, 'index' );
		$journal->record( 'post', 2, 'delete' );
		$journal->record( 'comment', 1, 'index' );

		$this->assertSame( 3, $journal->count_pending() );
	}

	/**
	 * Test action is normalized to index.
	 */
	public function testRecordActionNormalization() {
		$journal = FailedWrites::factory();

		$journal->record( 'post', 1, 'INDEX' );
		$journal->record( 'post', 2, 'unknown' );

		$rows  = $journal->get_pending( 10 );
		$by_id = [];
		foreach ( $rows as $row ) {
			$by_id[ (int) $row->object_id ] = $row->action;
		}

		$this->assertSame( 'index', $by_id[1] );
		$this->assertSame( 'index', $by_id[2] );
	}

	/**
	 * Test get_pending() orders by id ASC and respects limit.
	 */
	public function testGetPendingOrderAndLimit() {
		$journal = FailedWrites::factory();

		$journal->record( 'post', 1, 'index' );
		$journal->record( 'post', 2, 'index' );
		$journal->record( 'post', 3, 'index' );

		$rows = $journal->get_pending( 2 );
		$this->assertCount( 2, $rows );
		$this->assertSame( 1, (int) $rows[0]->object_id );
		$this->assertSame( 2, (int) $rows[1]->object_id );
	}

	/**
	 * Test delete_entries() removes by id.
	 */
	public function testDeleteEntries() {
		$journal = FailedWrites::factory();

		$a = $journal->record( 'post', 1, 'index' );
		$b = $journal->record( 'post', 2, 'index' );

		$deleted = $journal->delete_entries( [ $a ] );
		$this->assertSame( 1, $deleted );

		$rows = $journal->get_pending( 10 );
		$this->assertCount( 1, $rows );
		$this->assertSame( $b, (int) $rows[0]->id );
	}

	/**
	 * Test update_entry() rewrites the error message.
	 */
	public function testUpdateEntry() {
		$journal = FailedWrites::factory();

		$id = $journal->record( 'post', 1, 'index', 'first' );

		$journal->update_entry( $id, [ 'error_message' => 'still failing' ] );

		$rows = $journal->get_pending( 10 );
		$this->assertSame( 'still failing', $rows[0]->error_message );
	}

	/**
	 * Test that maybe_capture_index ignores success returns.
	 */
	public function testMaybeCaptureIndexSkipsSuccess() {
		$journal = FailedWrites::factory();

		$journal->maybe_capture_index( [ 'ID' => 1 ], (object) [ 'result' => 'created' ] );

		$this->assertSame( 0, $journal->count_pending() );
	}

	/**
	 * Test that maybe_capture_index records on a false return.
	 */
	public function testMaybeCaptureIndexRecordsOnFailure() {
		$journal = FailedWrites::factory();

		do_action( 'ep_after_index_post', [ 'ID' => 42 ], false );

		$rows = $journal->get_pending( 10 );
		$this->assertCount( 1, $rows );
		$this->assertSame( 'post', $rows[0]->indexable_slug );
		$this->assertSame( 42, (int) $rows[0]->object_id );
	}

	/**
	 * Test that capture_delete records on a false return.
	 */
	public function testCaptureDeleteRecordsOnFailure() {
		$journal = FailedWrites::factory();

		do_action( 'ep_after_delete_post', 99, false, 'post' );

		$rows = $journal->get_pending( 10 );
		$this->assertCount( 1, $rows );
		$this->assertSame( 'delete', $rows[0]->action );
		$this->assertSame( 99, (int) $rows[0]->object_id );
	}

	/**
	 * Test that capture_delete ignores a true return.
	 */
	public function testCaptureDeleteSkipsSuccess() {
		$journal = FailedWrites::factory();

		do_action( 'ep_after_delete_post', 99, true, 'post' );

		$this->assertSame( 0, $journal->count_pending() );
	}

	/**
	 * Test that maybe_capture_bulk records every id on a WP_Error result.
	 */
	public function testMaybeCaptureBulkRecordsOnError() {
		$journal = FailedWrites::factory();

		$error = new \WP_Error( 'ep_bulk_failed', 'connection refused' );
		$journal->maybe_capture_bulk( [ 1, 2, 3 ], 'post', $error );

		$this->assertSame( 3, $journal->count_pending() );
	}

	/**
	 * Test that maybe_capture_bulk ignores array results.
	 */
	public function testMaybeCaptureBulkIgnoresArray() {
		$journal = FailedWrites::factory();

		$journal->maybe_capture_bulk( [ 1, 2 ], 'post', [ 'errors' => false ] );

		$this->assertSame( 0, $journal->count_pending() );
	}
}
