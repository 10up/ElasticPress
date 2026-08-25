<?php
/**
 * Failed writes journal.
 *
 * Captures index/delete operations that failed due to Elasticsearch being
 * unreachable so an admin can replay them later via the WP-CLI command.
 *
 * @since 5.4.0
 * @package elasticpress
 */

namespace ElasticPress;

use ElasticPress\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Failed writes journal class.
 *
 * @since 5.4.0
 */
class FailedWrites {

	/**
	 * Database table name (without prefix).
	 *
	 * @var string
	 * @since 5.4.0
	 */
	const TABLE = 'ep_failed_writes';

	/**
	Singleton instance.
	 *
	 * @var FailedWrites
	 * @since 5.4.0
	 */
	protected static $instance = null;

	/**
	 * Return singleton instance of class.
	 *
	 * @since 5.4.0
	 * @return FailedWrites
	 */
	public static function factory() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->setup();
		}

		return self::$instance;
	}

	/**
	 * Initialize hooks.
	 *
	 * @since 5.4.0
	 */
	public function setup() {
		add_action( 'ep_after_index_post', [ $this, 'maybe_capture_index' ], 10, 2 );
		add_action( 'ep_after_index_comment', [ $this, 'maybe_capture_index' ], 10, 2 );
		add_action( 'ep_after_index_term', [ $this, 'maybe_capture_index' ], 10, 2 );
		add_action( 'ep_after_delete_post', [ $this, 'capture_delete' ], 10, 2 );
		add_action( 'ep_after_delete_comment', [ $this, 'capture_delete' ], 10, 2 );
		add_action( 'ep_after_delete_term', [ $this, 'capture_delete' ], 10, 2 );
		add_action( 'ep_after_bulk_index', [ $this, 'maybe_capture_bulk' ], 10, 3 );
	}

	/**
	 * Return the fully prefixed table name.
	 *
	 * @since 5.4.0
	 * @return string
	 */
	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Return the SQL used to (re)create the table via dbDelta.
	 *
	 * @since 5.4.0
	 * @return string
	 */
	public static function get_table_schema() {
		global $wpdb;

		$table   = self::get_table_name();
		$charset = $wpdb->get_charset_collate();

		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			indexable_slug varchar(32) NOT NULL,
			object_id bigint(20) unsigned NOT NULL,
			action varchar(16) NOT NULL,
			blog_id bigint(20) unsigned NOT NULL,
			error_message text NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY indexable_object (indexable_slug, object_id),
			KEY created_at (created_at)
		) {$charset};";
	}

	/**
	 * Maybe capture a single index failure.
	 *
	 * @param array|object $document Document that was sent.
	 * @param mixed        $response ES response. `false` indicates failure.
	 * @since 5.4.0
	 */
	public function maybe_capture_index( $document, $response ) {
		if ( ! empty( $response ) ) {
			return;
		}

		$slug = $this->detect_indexable_slug();

		if ( ! $slug ) {
			return;
		}

		$object_id = is_array( $document ) ? (int) ( $document['ID'] ?? 0 ) : (int) ( $document->ID ?? 0 );

		if ( ! $object_id ) {
			return;
		}

		$this->record( $slug, $object_id, 'index' );
	}

	/**
	 * Capture a delete failure.
	 *
	 * @param int   $object_id Object id.
	 * @param mixed $response  ES response. `false` indicates failure.
	 * @since 5.4.0
	 */
	public function capture_delete( $object_id, $response ) {
		if ( ! empty( $response ) ) {
			return;
		}

		$slug = $this->detect_slug_from_current_filter();

		if ( ! $slug ) {
			return;
		}

		$this->record( $slug, (int) $object_id, 'delete' );
	}

	/**
	 * Maybe capture a bulk index failure.
	 *
	 * @param array  $object_ids Object ids.
	 * @param string $slug       Indexable slug.
	 * @param mixed  $result     Bulk result. WP_Error or array.
	 * @since 5.4.0
	 */
	public function maybe_capture_bulk( $object_ids, $slug, $result ) {
		if ( is_wp_error( $result ) ) {
			foreach ( (array) $object_ids as $object_id ) {
				$this->record( $slug, (int) $object_id, 'index', $result->get_error_message() );
			}
		}
	}

	/**
	 * Insert or refresh a journal entry.
	 *
	 * Dedupes by (indexable_slug, object_id) so a post failing repeatedly
	 * doesn't pile up rows.
	 *
	 * @param string      $slug  Indexable slug.
	 * @param int         $id    Object id.
	 * @param string      $action 'index' or 'delete'.
	 * @param string|null $error Optional error message.
	 * @since 5.4.0
	 * @return int|false Inserted id or false.
	 */
	public function record( $slug, $id, $action, $error = null ) {
		global $wpdb;

		$slug   = sanitize_key( $slug );
		$action = ( 'delete' === $action ) ? 'delete' : 'index';
		$id     = (int) $id;
		$blog   = (int) get_current_blog_id();

		if ( ! $slug || ! $id ) {
			return false;
		}

		$existing = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT id FROM %i WHERE indexable_slug = %s AND object_id = %d',
				$this->table(),
				$slug,
				$id
			)
		);

		$data = [
			'indexable_slug' => $slug,
			'object_id'      => $id,
			'action'         => $action,
			'blog_id'        => $blog,
			'error_message'  => $error ? sanitize_text_field( $error ) : null,
		];

		$format = [ '%s', '%d', '%s', '%d', '%s' ];

		if ( $existing ) {
			$wpdb->update( $this->table(), $data, [ 'id' => (int) $existing ], $format, [ '%d' ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return (int) $existing;
		}

		$wpdb->insert( $this->table(), $data, $format ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->insert_id;
	}

	/**
	 * Pull pending entries in FIFO order.
	 *
	 * @param int $limit Max rows.
	 * @since 5.4.0
	 * @return array
	 */
	public function get_pending( $limit = 50 ) {
		global $wpdb;

		$limit = max( 1, (int) $limit );

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT * FROM %i ORDER BY id ASC LIMIT %d',
				$this->table(),
				$limit
			)
		);

		return is_array( $rows ) ? $rows : [];
	}

	/**
	 * Delete journal entries by id.
	 *
	 * @param int[] $ids Entry ids.
	 * @since 5.4.0
	 * @return int Rows deleted.
	 */
	public function delete_entries( array $ids ) {
		global $wpdb;

		$ids = array_filter( array_map( 'intval', $ids ) );

		if ( ! $ids ) {
			return 0;
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$params       = array_merge( [ $this->table() ], $ids );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $wpdb->prepare( "DELETE FROM %i WHERE id IN ({$placeholders})", $params );

		return (int) $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Count pending entries.
	 *
	 * @since 5.4.0
	 * @return int
	 */
	public function count_pending() {
		global $wpdb;

		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i', $this->table() )
		);
	}

	/**
	 * Drop a row by id. Used by replay when an error persists.
	 *
	 * @param int   $id    Entry id.
	 * @param array $data  Fields to update.
	 * @since 5.4.0
	 * @return int Rows affected.
	 */
	public function update_entry( $id, array $data ) {
		global $wpdb;

		if ( empty( $data ) ) {
			return 0;
		}

		$formats = [];
		foreach ( $data as $key => $value ) {
			if ( 'error_message' === $key ) {
				$data[ $key ] = $value ? sanitize_text_field( $value ) : null;
				$formats[]    = '%s';
			} elseif ( in_array( $key, [ 'object_id', 'blog_id' ], true ) ) {
				$formats[] = '%d';
			} else {
				$formats[] = '%s';
			}
		}

		return (int) $wpdb->update( $this->table(), $data, [ 'id' => (int) $id ], $formats, [ '%d' ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Resolve the indexable slug from the current action hook.
	 *
	 * @since 5.4.0
	 * @return string|null
	 */
	protected function detect_slug_from_current_filter() {
		$current = current_action();

		if ( ! $current || 0 !== strpos( $current, 'ep_after_delete_' ) ) {
			return null;
		}

		return substr( $current, strlen( 'ep_after_delete_' ) );
	}

	/**
	 * Resolve the indexable slug from the current action hook.
	 *
	 * @since 5.4.0
	 * @return string|null
	 */
	protected function detect_indexable_slug() {
		$current = current_action();

		if ( $current && 0 === strpos( $current, 'ep_after_index_' ) ) {
			return substr( $current, strlen( 'ep_after_index_' ) );
		}

		return null;
	}

	/**
	 * Resolve the table name with safe prefix interpolation.
	 *
	 * @since 5.4.0
	 * @return string
	 */
	protected function table() {
		return self::get_table_name();
	}
}
