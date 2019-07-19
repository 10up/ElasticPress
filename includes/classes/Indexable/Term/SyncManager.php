<?php
/**
 * Manage syncing of content between WP and Elasticsearch for Terms
 *
 * @since   3.1
 * @package elasticpress
 */

namespace ElasticPress\Indexable\Term;

use ElasticPress\Indexables as Indexables;
use ElasticPress\Elasticsearch as Elasticsearch;
use ElasticPress\SyncManager as SyncManagerAbstract;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Sync manager class
 */
class SyncManager extends SyncManagerAbstract {

	/**
	 * Setup actions and filters
	 *
	 * @since 3.1
	 */
	public function setup() {

	}

}
