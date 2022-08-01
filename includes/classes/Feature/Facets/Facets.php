<?php
/**
 * Facets feature
 *
 * @since  2.5
 * @package  elasticpress
 */

namespace ElasticPress\Feature\Facets;

use ElasticPress\Feature as Feature;
use ElasticPress\Features as Features;
use ElasticPress\Utils as Utils;
use ElasticPress\FeatureRequirementsStatus as FeatureRequirementsStatus;
use ElasticPress\Indexables as Indexables;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Facets feature class
 */
class Facets extends Feature {
	/**
	 * Facet types (taxonomy, meta fields, etc.)
	 *
	 * @since 4.3.0
	 * @var array
	 */
	public $types = [];

	/**
	 * Initialize feature setting it's config
	 *
	 * @since  3.0
	 */
	public function __construct() {
		$this->slug = 'facets';

		$this->title = esc_html__( 'Facets', 'elasticpress' );

		$this->summary = __( 'Add controls to your website to filter content by one or more taxonomies.', 'elasticpress' );

		$this->docs_url = __( 'https://elasticpress.zendesk.com/hc/en-us/articles/360050447492-Configuring-ElasticPress-via-the-Plugin-Dashboard#facets', 'elasticpress' );

		$this->requires_install_reindex = false;

		$this->default_settings = [
			'match_type' => 'all',
		];

		$types = [
			'taxonomy' => __NAMESPACE__ . '\Types\Taxonomy\FacetType',
		];

		foreach ( $types as $type => $class ) {
			$this->types[ $type ] = new $class();
		}

		parent::__construct();
	}

	/**
	 * Setup hooks and filters for feature
	 *
	 * @since 2.5
	 */
	public function setup() {
		global $pagenow;

		// This feature should not run while in the editor.
		if ( in_array( $pagenow, [ 'post-new.php', 'post.php' ], true ) ) {
			return;
		}

		foreach ( $this->types as $type => $class ) {
			$this->types[ $type ]->setup();
		}

		add_action( 'ep_valid_response', [ $this, 'get_aggs' ], 10, 4 );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_scripts' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'front_scripts' ] );
		add_action( 'ep_feature_box_settings_facets', [ $this, 'settings' ], 10, 1 );
	}

	/**
	 * Dashboard facet settings
	 *
	 * @since 2.5
	 */
	public function output_feature_box_settings() {
		$settings = $this->get_settings();

		if ( ! $settings ) {
			$settings = [];
		}

		$settings = wp_parse_args( $settings, $this->default_settings );
		?>
		<div class="field">
			<div class="field-name status"><?php esc_html_e( 'Match Type', 'elasticpress' ); ?></div>
			<div class="input-wrap">
				<label><input name="settings[match_type]" type="radio" <?php checked( $settings['match_type'], 'all' ); ?> value="all"><?php echo wp_kses_post( __( 'Show any content tagged to <strong>all</strong> selected terms', 'elasticpress' ) ); ?></label><br>
				<label><input name="settings[match_type]" type="radio" <?php checked( $settings['match_type'], 'any' ); ?> value="any"><?php echo wp_kses_post( __( 'Show all content tagged to <strong>any</strong> selected term', 'elasticpress' ) ); ?></label>
				<p class="field-description"><?php esc_html_e( '"All" will only show content that matches all facets. "Any" will show content that matches any facet.', 'elasticpress' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * If we are doing or matches, we need to remove filters from aggs
	 *
	 * @param  array    $args ES arguments
	 * @param  array    $query_args Query arguments
	 * @param  WP_Query $query WP Query instance
	 * @since  2.5, deprecated in 4.3.0
	 * @return array
	 */
	public function set_agg_filters( $args, $query_args, $query ) {
		_deprecated_function( __METHOD__, '4.3.0', "\ElasticPress\Features::factory()->get_registered_feature( 'facets' )->types['taxonomy']->set_agg_filters()" );

		return $this->types['taxonomy']->set_agg_filters( $args, $query_args, $query );

	}
	/**
	 * Output scripts for widget admin
	 *
	 * @param  string $hook WP hook
	 * @since  2.5
	 */
	public function admin_scripts( $hook ) {
		if ( 'widgets.php' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'elasticpress-facets-admin',
			EP_URL . 'dist/css/facets-admin-styles.min.css',
			Utils\get_asset_info( 'facets-admin-styles', 'dependencies' ),
			Utils\get_asset_info( 'facets-admin-styles', 'version' )
		);
	}

	/**
	 * Output front end facets styles
	 *
	 * @since 2.5
	 */
	public function front_scripts() {
		wp_register_script(
			'elasticpress-facets',
			EP_URL . 'dist/js/facets-script.min.js',
			Utils\get_asset_info( 'facets-script', 'dependencies' ),
			Utils\get_asset_info( 'facets-script', 'version' ),
			true
		);

		wp_register_style(
			'elasticpress-facets',
			EP_URL . 'dist/css/facets-styles.min.css',
			Utils\get_asset_info( 'facets-styles', 'dependencies' ),
			Utils\get_asset_info( 'facets-styles', 'version' )
		);
	}

	/**
	 * Figure out if we can/should facet the query
	 *
	 * @param  WP_Query $query WP Query
	 * @since  2.5
	 * @return bool
	 */
	public function is_facetable( $query ) {

		/**
		 * Bypass the standard checks and set a query to be facetable
		 *
		 * @hook ep_is_facetable
		 * @param  {bool}     $bypass Defaults to false.
		 * @param  {WP_Query} $query  The current WP_Query.
		 * @return {bool}     true to bypass, false to ignore
		 */
		if ( \apply_filters( 'ep_is_facetable', false, $query ) ) {
			return true;
		}

		if ( is_admin() ) {
			return false;
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return false;
		}

		if ( ! $query->is_main_query() ) {
			return false;
		}

		$ep_integrate = $query->get( 'ep_integrate', null );

		if ( false === $ep_integrate ) {
			return false;
		}

		$woocommerce = Features::factory()->get_registered_feature( 'woocommerce' );

		if ( ! $woocommerce->is_active() && ( function_exists( 'is_product_category' ) && is_product_category() ) ) {
			return false;
		}

		if ( ! $this->is_facetable_page( $query ) ) {
			return false;
		}

		return true;
	}

	/**
	 * We enable ElasticPress facet on all archive/search queries as well as non-static home pages. There is no way to know
	 * when a facet widget is used before the main query is executed so we enable EP
	 * everywhere where a facet widget could be used.
	 *
	 * @param  WP_Query $query WP Query
	 * @since  2.5, deprecated in 4.3.0
	 */
	public function facet_query( $query ) {
		_deprecated_function( __METHOD__, '4.3.0', "\ElasticPress\Features::factory()->get_registered_feature( 'facets' )->types['taxonomy']->facet_query()" );

		return $this->types['taxonomy']->facet_query( $query );
	}

	/**
	 * Hacky. Save aggregation data for later in a global
	 *
	 * @param  array $response ES response
	 * @param  array $query Prepared Elasticsearch query
	 * @param  array $query_args Current WP Query arguments
	 * @param  mixed $query_object Could be WP_Query, WP_User_Query, etc.
	 * @since  2.5
	 */
	public function get_aggs( $response, $query, $query_args, $query_object ) {
		if ( empty( $query_object ) || 'WP_Query' !== get_class( $query_object ) || ! $this->is_facetable( $query_object ) ) {
			return;
		}

		$GLOBALS['ep_facet_aggs'] = false;

		if ( ! empty( $response['aggregations'] ) ) {
			$GLOBALS['ep_facet_aggs'] = [];

			if ( isset( $response['aggregations']['terms'] ) && is_array( $response['aggregations']['terms'] ) ) {
				foreach ( $response['aggregations']['terms'] as $key => $agg ) {
					if ( 'doc_count' === $key ) {
						continue;
					}

					if ( ! is_array( $agg ) || empty( $agg['buckets'] ) ) {
						continue;
					}

					$GLOBALS['ep_facet_aggs'][ $key ] = [];

					foreach ( $agg['buckets'] as $bucket ) {
						$GLOBALS['ep_facet_aggs'][ $key ][ $bucket['key'] ] = $bucket['doc_count'];
					}
				}
			}
		}
	}

	/**
	 * Get currently selected facets from query args
	 *
	 * @since  2.5, deprecated in 4.3.0
	 * @return array
	 */
	public function get_selected() {
		_deprecated_function( __METHOD__, '4.3.0', "\ElasticPress\Features::factory()->get_registered_feature( 'facets' )->types['taxonomy']->get_selected()" );

		return $this->types['taxonomy']->get_selected();
	}

	/**
	 * Build query url
	 *
	 * @since  2.5, deprecated in 4.3.0
	 * @param  array $filters Facet filters
	 * @return string
	 */
	public function build_query_url( $filters ) {
		_deprecated_function( __METHOD__, '4.3.0', "\ElasticPress\Features::factory()->get_registered_feature( 'facets' )->types['taxonomy']->build_query_url()" );

		return $this->types['taxonomy']->build_query_url( $filters );
	}

	/**
	 * Register facet widget(s)
	 *
	 * @since 2.5, deprecated in 4.3.0
	 */
	public function register_widgets() {
		_deprecated_function( __METHOD__, '4.3.0', "\ElasticPress\Features::factory()->get_registered_feature( 'facets' )->types[ \$type ]->register_widgets()" );
	}

	/**
	 * Output feature box long
	 *
	 * @since 2.5
	 */
	public function output_feature_box_long() {
		?>
		<p>
			<?php
			// translators: URL
			echo wp_kses_post( sprintf( __( "Adds a <a href='%s'>Facet widget</a> that administrators can add to the website's sidebars (widgetized areas), so that visitors can filter applicable content and search results by one or more taxonomy terms.", 'elasticpress' ), esc_url( admin_url( 'widgets.php' ) ) ) );
			?>
		</p>
		<?php
	}

	/**
	 * Returns allowed query args for facets
	 *
	 * @return mixed|void
	 * @since 3.6.0
	 */
	public function get_allowed_query_args() {
		$args = array( 's', 'post_type' );

		/**
		 * Filter allowed query args
		 *
		 * @hook    ep_facet_allowed_query_args
		 * @since 3.6.0
		 * @param   {array} $args Post types
		 * @return  {array} New post types
		 */
		return apply_filters( 'ep_facet_allowed_query_args', $args );
	}

	/**
	 * Get the facet filter name.
	 *
	 * @return string The filter name.
	 */
	protected function get_filter_name() {
		_deprecated_function( __METHOD__, '4.3.0', "\ElasticPress\Features::factory()->get_registered_feature( 'facets' )->types['taxonomy']->get_filter_name()" );

		return $this->types['taxonomy']->get_filter_name();
	}

	/**
	 * Get all taxonomies that could be selected for a facet.
	 *
	 * @since 4.2.0, deprecated in 4.3.0
	 * @return array
	 */
	public function get_facetable_taxonomies() {
		_deprecated_function( __METHOD__, '4.3.0', "\ElasticPress\Features::factory()->get_registered_feature( 'facets' )->types['taxonomy']->get_facetable_taxonomies()" );

		return $this->types['taxonomy']->get_filter_name();

	}

	/**
	 * Figure out if Facet widget can display on page.
	 *
	 * @param  WP_Query $query WP Query
	 * @since  4.2.1
	 * @return bool
	 */
	protected function is_facetable_page( $query ) {
		return $query->is_home() || $query->is_search() || $query->is_tax() || $query->is_tag() || $query->is_category() || $query->is_post_type_archive();
	}
}
