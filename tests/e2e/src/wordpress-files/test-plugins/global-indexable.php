<?php
/**
 * Plugin Name: Global Indexable Sync Fixture
 * Description: Registers the generic shared indexable used by the sync tests.
 *
 * @package ElasticPress_Tests_E2e
 */

add_action(
	'plugins_loaded',
	static function () {
		require_once EP_PATH . 'tests/php/includes/classes/mock/Global/Indexable.php';
		\ElasticPress\Indexables::factory()->register( new \ElasticPressTest\GlobalIndexable\Indexable() );
	},
	20
);
