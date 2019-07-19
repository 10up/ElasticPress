<?php
/**
 * Integrate with WP_Term_Query
 *
 * @since   3.1
 * @package elasticpress
 */

namespace ElasticPress\Indexable\Term;

use ElasticPress\Indexables as Indexables;
use \WP_Term_Query as WP_Term_Query;
use ElasticPress\Utils as Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Query integration class
 */
class QueryIntegration {

	/**
	 * Sets up the appropriate actions and filters.
	 *
	 * @since 3.1
	 */
	public function __construct() {
		// Check if we are currently indexing
		if ( Utils\is_indexing() ) {
			return;
		}

	}

}
