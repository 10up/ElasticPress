<?php
/**
 * GlobalFeature feature
 *
 * @since 5.0.0
 * @package elasticpress
 */

namespace ElasticPressTest\GlobalIndexable;

use ElasticPress\Indexables;

require_once __DIR__ . '/Indexable.php';

/**
 * Global feature class
 */
class GlobalFeature extends \ElasticPress\Feature {
	/**
	 * Initialize feature setting it's config
	 */
	public function __construct() {
		$this->slug                     = 'global';
		$this->title                    = 'Global';
		$this->requires_install_reindex = true;

		Indexables::factory()->register( new Indexable(), false );

		parent::__construct();
	}

	/**
	 * Activate the indexable
	 */
	public function setup() {
		Indexables::factory()->activate( 'global' );
	}


	/**
	 * Determine feature reqs status
	 */
	public function requirements_status() {
		return new \ElasticPress\FeatureRequirementsStatus( 1, null, $this );
	}
}

\ElasticPress\Features::factory()->register_feature(
	new GlobalFeature()
);
