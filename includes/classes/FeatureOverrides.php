<?php
/**
 * Ensures ElasticPress wins the slugs it owns when another plugin ships the same features.
 *
 * @since   5.3.3
 * @package elasticpress
 */

namespace ElasticPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Ensures ElasticPress wins the slugs it owns when another plugin ships the same features.
 */
class FeatureOverrides {

	/**
	 * Slugs ElasticPress owns, mapped to the class to re-register if overwritten.
	 *
	 * @var array<string, class-string<\ElasticPress\Feature>>
	 */
	protected $protected_features = [
		'semantic_search'   => \ElasticPress\Feature\SemanticSearch\SemanticSearch::class,
		'vector_embeddings' => \ElasticPress\Feature\VectorEmbeddings\VectorEmbeddings::class,
		'ai_search_summary' => \ElasticPress\Feature\AISearchSummary::class,
	];

	/**
	 * Add-on slugs absorbed into a differently-named ElasticPress feature.
	 *
	 * Maps the absorbed slug to the ElasticPress slug whose presence supersedes it.
	 *
	 * @var array<string, string>
	 */
	protected $superseded_features = [
		'search_algorithm' => 'semantic_search',
	];

	/**
	 * Fully-qualified name of the ElasticPress Labs feature-loader callback.
	 *
	 * @var string
	 */
	const EP_LABS_HOOK = 'ElasticPressLabs\Core\maybe_load_features';

	/**
	 * Register hooks.
	 */
	public function setup() {
		add_action( 'plugins_loaded', [ $this, 'reclaim_protected_features' ], $this->get_reclaim_priority() );

		add_filter( 'ep_feature_active', [ $this, 'suppress_superseded_active' ], 10, 3 );
		add_filter( 'ep_feature_is_visible', [ $this, 'suppress_superseded_visible' ], 10, 2 );
	}

	/**
	 * Re-register ElasticPress's own instance for any protected slug another plugin overwrote.
	 */
	public function reclaim_protected_features() {
		$features = Features::factory();

		foreach ( $this->protected_features as $slug => $class_name ) {
			if ( ! class_exists( $class_name ) ) {
				continue;
			}

			if ( ! $features->get_registered_feature( $slug ) instanceof $class_name ) {
				$features->register_feature( new $class_name() );
			}
		}
	}

	/**
	 * Force a superseded feature inactive so its `setup()` never runs.
	 *
	 * @param bool                  $active   Whether the feature is active.
	 * @param array                 $settings Current feature settings.
	 * @param \ElasticPress\Feature $feature  Current feature.
	 * @return bool
	 */
	public function suppress_superseded_active( $active, $settings, $feature ) {
		return $this->is_superseded( $feature->slug ) ? false : $active;
	}

	/**
	 * Hide the now-redundant dashboard card for a superseded feature.
	 *
	 * @param bool   $visible Whether the feature is visible.
	 * @param string $slug    Feature slug.
	 * @return bool
	 */
	public function suppress_superseded_visible( $visible, $slug ) {
		return $this->is_superseded( $slug ) ? false : $visible;
	}

	/**
	 * Whether a slug is superseded by a registered ElasticPress feature.
	 *
	 * @param string $slug Feature slug.
	 * @return bool
	 */
	protected function is_superseded( string $slug ): bool {
		if ( ! isset( $this->superseded_features[ $slug ] ) ) {
			return false;
		}

		return (bool) Features::factory()->get_registered_feature( $this->superseded_features[ $slug ] );
	}

	/**
	 * Determine the `plugins_loaded` priority for the reclaim.
	 *
	 * Runs immediately after the ElasticPress Labs feature loader when present,
	 * otherwise at 20 as a safety net. All plugin files are included before
	 * `plugins_loaded` fires, so the add-on callback is already registered here.
	 *
	 * @return int
	 */
	protected function get_reclaim_priority(): int {
		global $wp_filter;

		$fallback = 20;

		if ( empty( $wp_filter['plugins_loaded'] ) ) {
			return $fallback;
		}

		foreach ( $wp_filter['plugins_loaded']->callbacks as $priority => $callbacks ) {
			if ( in_array( self::EP_LABS_HOOK, array_column( $callbacks, 'function' ), true ) ) {
				return (int) $priority + 1;
			}
		}

		return $fallback;
	}

	/**
	 * Return singleton instance of class
	 *
	 * @return object
	 */
	public static function factory() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
			$instance->setup();
		}

		return $instance;
	}
}
