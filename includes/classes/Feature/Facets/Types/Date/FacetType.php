<?php
/**
 * Date facet type
 *
 * @since 5.0.0
 * @package elasticpress
 */

namespace ElasticPress\Feature\Facets\Types\Date;

use \ElasticPress\Features;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Date facet type class
 */
class FacetType extends \ElasticPress\Feature\Facets\FacetType {

	/**
	 * Block instance.
	 *
	 * @var Block
	 */
	public $block;

	/**
	 * Setup hooks and filters for feature
	 */
	public function setup() {
		add_filter( 'ep_facet_query_filters', [ $this, 'add_query_filters' ] );
		add_filter( 'ep_facets_date_script_data', [ $this, 'add_filter_name' ] );

		$this->block = new Block();
		$this->block->setup();
	}

	/**
	 * Get the facet filter name.
	 *
	 * @return string The filter name.
	 */
	public function get_filter_name() : string {
		/**
		 * Filter the facet filter name that's added to the URL
		 *
		 * @hook ep_facet_date_filter_name
		 * @since 5.0.0
		 * @param   {string} Facet filter name
		 * @return  {string} New facet filter name
		 */
		return apply_filters( 'ep_facet_date_filter_name', 'ep_date_filter' );
	}

	/**
	 * Get the facet filter type.
	 *
	 * @return string The filter name.
	 */
	public function get_filter_type() : string {
		/**
		 * Filter the facet filter type. Used by the Facet feature to organize filters.
		 *
		 * @hook ep_facet_date_filter_type
		 * @since 5.0.0
		 * @param   {string} Facet filter type
		 * @return  {string} New facet filter type
		 */
		return apply_filters( 'ep_facet_date_filter_type', 'ep_date' );
	}

	/**
	 * Add selected filters to the Facet filter in the ES query
	 *
	 * @param array $filters Current Facet filters
	 * @return array
	 */
	public function add_query_filters( $filters ) {
		$feature = Features::factory()->get_registered_feature( 'facets' );

		$selected_filters = $feature->get_selected();

		if ( empty( $selected_filters ) || empty( $selected_filters[ $this->get_filter_type() ] ) ) {
			return $filters;
		}

		$dates = $this->parse_dates( array_keys( $selected_filters[ $this->get_filter_type() ]['terms'] ) );

		$start_date = $dates[0] ?? null;
		$end_date   = $dates[1] ?? null;

		if ( ! empty( $start_date ) ) {
			$filters[] = [
				'range' => [
					'post_date' => [
						'gte' => $start_date,
					],
				],
			];
		}

		if ( ! empty( $end_date ) ) {
			$filters[] = [
				'range' => [
					'post_date' => [
						'lte' => $end_date,
					],
				],
			];
		}

		return $filters;
	}

	/**
	 * Format selected values.
	 *
	 * @param string $facet   Facet name
	 * @param mixed  $value   Facet value
	 * @param array  $filters Selected filters
	 * @return array
	 */
	public function format_selected( string $facet, $value, array $filters ) {
		$terms = explode( ',', rtrim( $value, ',' ) );

		$filters[ $this->get_filter_type() ] = [
			'terms' => array_fill_keys( array_map( $this->get_sanitize_callback(), $terms ), true ),
		];

		return $filters;
	}

	/**
	 * Add selected filters to the query string.
	 *
	 * @param array $query_params Existent query parameters
	 * @param array $filters      Selected filters
	 * @return array
	 */
	public function add_query_params( array $query_params, array $filters ) : array {
		$selected = $filters[ $this->get_filter_type() ] ?? [];

		if ( ! empty( $selected['terms'] ) ) {
			$query_params[ $this->get_filter_name() ] = implode( ',', array_keys( $selected['terms'] ) );
		}

		return $query_params;
	}

	/**
	 * Get the options for the date facet.
	 *
	 * @return array The options for the date facet.
	 */
	public function get_facet_options() {
		/**
		 * The options array for the date facet.
		 *
		 * Each option is an associative array with the following keys:
		 * - 'label': The display name of the option.
		 * - 'value': The relative date string for the option. This string is used to modify a DateTime object.
		 *            It should be in a format recognized by the PHP strtotime function, e.g., '-3 months'.
		 * - 'urlSlug': The URL parameter for the option.
		 */
		$options = [
			[
				'label'     => __( 'Last 3 months', 'elasticpress' ),
				'value'     => '-3 months',
				'url-param' => 'last-3-months',
			],
			[
				'label'     => __( 'Last 6 months', 'elasticpress' ),
				'value'     => '-6 months',
				'url-param' => 'last-6-months',
			],
			[
				'label'     => __( 'Last 12 months', 'elasticpress' ),
				'value'     => '-12 months',
				'url-param' => 'last-12-months',
			],
		];

		/**
		 * Filter the options for the date facet.
		 *
		 * Example:
		 * ```
		 * add_filter(
		 *   'ep_facet_date_options',
		 *   function( $options ) {
		 *       $options = [
		 *            [
		 *               'label'     => esc_html__( 'Last 7 days', 'elasticpress' ),
		 *               'value'     => '-7 days',
		 *               'url-param' => 'last-7-days',
		 *           ],
		 *           [
		 *               'label'     => esc_html__( 'Last 1 month', 'elasticpress' ),
		 *               'value'     => '-1 month',
		 *               'url-param' => 'last-1-month',
		 *           ],
		 *           [
		 *               'label'     => esc_html__( 'Last 6 months', 'elasticpress' ),
		 *               'value'     => '-6 months',
		 *               'url-param' => 'last-6-months',
		 *           ],
		 *           [
		 *               'label'     => esc_html__( 'Last 1 year', 'elasticpress' ),
		 *               'value'     => '-1 year',
		 *               'url-param' => 'last-1-year',
		 *           ],
		 *           [
		 *               'label'     => esc_html__( 'Last 5 years', 'elasticpress' ),
		 *               'value'     => '-5 years',
		 *               'url-param' => 'last-5-years',
		 *           ],
		 *       ];
		 *
		 *       return $options;
		 *   }
		 * );
		 * ```
		 *
		 * @since 5.0.0
		 *
		 * @param {array} $options The options for the date facet.
		 * @return {array} The options for the date facet.
		 */
		return apply_filters( 'ep_facet_date_options', $options );
	}

	/**
	 * Parses an array of dates and returns an array of formatted dates.
	 *
	 * @param array $dates An array of dates to parse.
	 *
	 * @return array An array of formatted dates.
	 */
	public function parse_dates( $dates ) : array {
		$options = array_column( $this->get_facet_options(), 'value', 'url-param' );

		// Only use the first two dates.
		$dates = array_slice( $dates, 0, 2 );

		foreach ( $dates as $index => $date ) {
			$date_string = isset( $options[ $date ] ) ? $options[ $date ] : $date;

			if ( empty( $date_string ) || ! strtotime( $date_string ) ) {
				$formatted_dates[] = '';
				continue;
			}

			$date = new \DateTime();
			$date->modify( $date_string );

			$date_format       = 1 === $index ? 'Y-m-d 23:59:59' : 'Y-m-d 00:00:00';
			$formatted_dates[] = $date->format( $date_format );
		}

		return $formatted_dates;
	}

	/**
	 * Adds the filter name.
	 *
	 * @param array $data The data array passed to localize script.
	 * @return array The updated data array with the filter name added.
	 */
	public function add_filter_name( $data ) {
		$data['dateFilterName'] = $this->get_filter_name();
		return $data;
	}

}
