<?php
/**
 * Global indexable
 *
 * @since 5.0.0
 * @package elasticpress
 */

namespace ElasticPressTest\GlobalIndexable;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * User indexable class
 */
class Indexable extends \ElasticPress\Indexable {
	/**
	 * Set as global indexable
	 *
	 * @var boolean
	 */
	public $global = true;

	/**
	 * Indexable slug
	 *
	 * @var string
	 */
	public $slug = 'global';

	/**
	 * Create indexable
	 */
	public function __construct() {
		$this->labels = [
			'plural'   => esc_html__( 'Global', 'elasticpress' ),
			'singular' => esc_html__( 'Global', 'elasticpress' ),
		];
	}

	/**
	 * Generate the mapping array
	 *
	 * @return array
	 */
	public function generate_mapping() {
		$mapping = require __DIR__ . '/mapping.php';
		return $mapping;
	}

	/**
	 * Prepare a user document for indexing
	 *
	 * @param  int $object_id Object id
	 * @return array
	 */
	public function prepare_document( $object_id ) {
		return [
			'ID' => $object_id,
		];
	}

	/**
	 * Query DB for users
	 *
	 * @param  array $args Query arguments
	 * @return array
	 */
	public function query_db( $args ) {
		return [
			'objects'       => [ 1, 2 ],
			'total_objects' => 2,
		];
	}
}
