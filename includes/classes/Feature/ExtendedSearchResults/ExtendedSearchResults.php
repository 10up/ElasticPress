<?php
/**
 * Extended Search Results feature
 *
 * @since 5.0.0
 * @package elasticpress
 */

namespace ElasticPress\Feature\ExtendedSearchResults;

use ElasticPress\Feature;

/**
 * Extended Search Results feature class.
 */
class ExtendedSearchResults extends Feature {
	/**
	 * Feature slug
	 *
	 * @var string
	 */
	public $slug = 'extended_search_results';

	/**
	 * Extended results types (Terms, Users, etc.)
	 *
	 * @var array
	 */
	public $types = [];

	/**
	 * Class constructor
	 */
	public function __construct() {
		$this->title = esc_html__( 'Extended Search Results', 'elasticpress' );

		$types = [
			'term' => __NAMESPACE__ . '\Types\Term',
		];

		$types = apply_filters( 'ep_facet_types', $types );

		foreach ( $types as $type => $class ) {
			if ( is_a( $class, __NAMESPACE__ . '\EsrType', true ) ) {
				$this->types[ $type ] = new $class();
			}
		}

		parent::__construct();
	}

	/**
	 * Run on every page load for feature to set itself up
	 */
	public function setup() {
		foreach ( $this->types as $type => $class ) {
			$this->types[ $type ]->setup();
		}
	}

	/**
	 * Implement to output feature box long text
	 *
	 * @todo Needs to be implemented
	 */
	public function output_feature_box_long() {
		?>
		<p><?php esc_html_e( 'TBD', 'elasticpress' ); ?></p>
		<?php
	}
}
