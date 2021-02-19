<?php
/**
 * Geolocation based searches and filtering
 *
 * @since  3.4.3
 * @package elasticpress
 */

// TODO: add styling to the button
// TODO: test indexing across multiple ES versions
// TODO: test the filters

namespace ElasticPress\Feature\Geolocation;

use ElasticPress\Feature as Feature;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Extends the ElasticPress Feature class
 */
class Geolocation extends Feature {

	/**
	 * Construct
	 */
	public function __construct() {
		$this->slug = 'ep_geo';

		$this->title = esc_html__( 'Geolocation Search', 'elasticpress' );

		$this->requires_install_reindex = true;

		parent::__construct();
	}

	/**
	 * Setup the geo filters
	 */
	public function setup() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_filter( 'ep_config_mapping', [ $this, 'config_mapping' ] );
		add_filter( 'ep_post_sync_args', [ $this, 'post_sync_args' ], 99, 2 );
		add_filter( 'ep_formatted_args', [ $this, 'formatted_args' ], 99, 2 );
		add_filter( 'query_vars', [ $this, 'location_query_vars' ] );
		add_filter( 'pre_get_posts', [ $this, 'edit_query' ], 99, 2 );
	}

	/**
	 * Short description to appear on the EP settings page
	 */
	public function output_feature_box_summary() {
		echo '<p>' . esc_html_e( 'Integrate geolocation data with ElasticSearch, and enable geolocation queries.', 'elasticpress' ) . '</p>';
	}

	/**
	 * Long description to appear on the EP settings page
	 */
	public function output_feature_box_long() {}

	/**
	 * Display decaying settings on dashboard.
	 *
	 * @since 3.4.3
	 */
	public function output_feature_box_settings() {
		$settings = $this->get_settings();

		if ( ! $settings ) {
			$settings = [];
		}

		$settings = wp_parse_args( $settings, $this->default_settings );
		?>

		<div class="field js-toggle-feature" data-feature="<?php echo esc_attr( $this->slug ); ?>">
			<div class="field-name status"><label for="feature_location_selector"><?php esc_html_e( '"Location Me" selector', 'elasticpress' ); ?></label></div>
			<div class="input-wrap">
				<input value="<?php echo empty( $settings['geolocation_selector'] ) ? 'ep-geolocation' : esc_html( $settings['geolocation_selector'] ); ?>" type="text" data-field-name="geolocation_selector" class="setting-field" id="feature_geolocation_selector">
				<p class="field-description"><?php esc_html_e( 'Input selectors where you would like to have the "Locate Me" button appended separated by a comma. Example: .custom-selector, #custom-id', 'elasticpress' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Alter ES index to add location property.
	 *
	 * @param array $mapping Elasticsearch mapping
	 *
	 * @return array
	 */
	public function config_mapping( $mapping ) {
		// Index geo_point:
		$mapping['mappings']['properties']['pin'] = [
			'properties' => [
				'location' => [
					'type' => 'geo_point',
				],
			],
		];

		return $mapping;
	}

	/**
	 * Alter ES sync data to post geo_points.
	 *
	 * @param array $post_args meta and additional post data
	 * @param int   $post_id id of the post
	 *
	 * @return array
	 */
	public function post_sync_args( $post_args, $post_id ) {
		// Sync geo_point:
		$geo_point = [
			'lat' => '',
			'lon' => '',
		];

		/**
		 * Filter name of the meta field where the latitude is stored
		 *
		 * @hook ep_latitude_meta
		 * @param  {array} $meta_field_name name of meta field where latitude is stored
		 * @param  {array} $post_id id of the post
		 * @return  {array} new name of meta field where latitude is stored
		 */
		$lat_meta_key = apply_filters( 'ep_latitude_meta', 'latitude', $post_id );

		/**
		 * Filter name of the meta field where the longitude is stored
		 *
		 * @hook ep_longitude_meta
		 * @param  {array} $meta_field_name name of meta field where longitude is stored
		 * @param  {array} $post_id id of the post
		 * @return  {array} new name of meta field where longitude is stored
		 */
		$lon_meta_key = apply_filters( 'ep_longitude_meta', 'longitude', $post_id );

		if ( isset( $post_args['meta'] ) ) {
			$meta = $post_args['meta'];

			if ( ! empty( $meta[ $lat_meta_key ][0]['double'] ) ) {
				$geo_point['lat'] = (string) $meta[ $lat_meta_key ][0]['double'];
			}

			if ( ! empty( $meta[ $lon_meta_key ][0]['double'] ) ) {
				$geo_point['lon'] = (string) $meta[ $lon_meta_key ][0]['double'];
			}
		} elseif ( isset( $post_args['post_meta'] ) ) {
			// Handle legacy post_meta property, for older versions of elasticpress.
			$post_meta = $post_args['post_meta'];

			if ( isset( $post_meta[ $lat_meta_key ][0] ) ) {
				$geo_point['lat'] = (string) $post_meta[ $lat_meta_key ][0];
			}

			if ( isset( $post_meta[ $lon_meta_key ][0] ) ) {
				$geo_point['lon'] = (string) $post_meta[ $lon_meta_key ][0];
			}
		}

		/**
		 * Filter pin location to be indexed
		 *
		 * @hook ep_geo_post_sync_geo_point
		 * @param  {array} $geo_point latitude and longitude points to be indexed
		 * @param  {array} $post_args meta and additional post data
		 * @param  {array} $post_id id of the post
		 * @return  {array} new latitude and longitude points
		 */
		$post_args['pin']['location'] = apply_filters( 'ep_geo_post_sync_geo_point', $geo_point, $post_args, $post_id );

		return $post_args;
	}

	/**
	 * Alter formatted WP query args for geo filter.
	 *
	 * @param array $formatted_args EP query args
	 * @param array $args           EP arguments
	 *
	 * @return array
	 */
	public function formatted_args( $formatted_args, $args ) {
		if ( ! isset( $args['geo_distance'] ) || ! isset( $formatted_args['post_filter']['bool'] ) ) {
			return $formatted_args;
		}

		$formatted_args['post_filter']['bool']['filter']['geo_distance'] = $args['geo_distance'];

		if ( isset( $formatted_args['sort'] ) && is_array( $formatted_args['sort'] ) ) {
			$formatted_args['sort'] = [];
		}

		$formatted_args['sort'][0]['_geo_distance'] = [
			'pin.location' => $formatted_args['post_filter']['bool']['filter']['geo_distance']['pin.location'],
			'order'        => 'asc',
			'unit'         => 'mi',
		];

		return $formatted_args;
	}

	/**
	 * Enqueue location scripts and styles
	 */
	public function enqueue_scripts() {
		wp_enqueue_script(
			'elasticpress-geolocation',
			EP_URL . 'dist/js/geolocation-script.min.js',
			[],
			EP_VERSION,
			true
		);

		wp_enqueue_style(
			'elasticpress-geolocation',
			EP_URL . 'dist/css/geolocation-styles.min.css',
			[],
			EP_VERSION
		);

		$settings = $this->get_settings();

		$epgl_options = [
			'selector'                => empty( $settings['geolocation_selector'] ) ? 'ep-geolocation' : esc_html( $settings['geolocation_selector'] ),
			'buttonText'              => esc_html__( 'Use My Location', 'elasticpress' ),
			'getLocationErrorMessage' => esc_html__( 'Geolocation is not supported by this browser.', 'elasticpress' ),
			'locationSetMessage'      => esc_html__( 'Using Current location', 'elasticpress' ),
			'removeButtonText'        => esc_html__( 'Stop using my location', 'elasticpress' ),
		];

		wp_localize_script(
			'elasticpress-geolocation',
			'epgl',
			/**
			 * Filter geolocation JavaScript options
			 *
			 * @hook ep_geolocation_options
			 * @param  {array} $options Geolocation options to be localized
			 * @return  {array} New options
			 */
			apply_filters(
				'ep_geolocation_options',
				$epgl_options
			)
		);
	}

	/**
	 * Set query vars used for location
	 *
	 * @param array $vars current query vars
	 *
	 * @return array
	 */
	public function location_query_vars( $vars ) {
		$vars[] = 'epgl_latitude';
		$vars[] = 'epgl_longitude';
		return $vars;
	}

	/**
	 * Alter the query for geolocation search
	 *
	 * @param WP_Query $query current wp query
	 */
	public function edit_query( $query ) {
		if ( $query->is_search() && true === $query->query_vars['ep_integrate'] ) {
			$lat  = get_query_var( 'epgl_latitude', '' );
			$long = get_query_var( 'epgl_longitude', '' );

			// use the data from the cookie as a fallback
			if ( empty( $lat ) || empty( $long ) ) {
				/**
				 * Option to use the location cookie as a fallback if the query vars are not set.
				 *
				 * @hook ep_geo_use_cookie_fallback
				 * @param {bool} whether or not to use the lat long saved in the cookie as a fallback
				 * @param  {WP_Query} $query the search query
				 * @return  {bool} whether or not to use the lat long saved in the cookie as a fallback
				 */
				if ( apply_filters( 'ep_geo_use_cookie_fallback', true, $query ) && isset( $_COOKIE['epgl'] ) ) {
					$lat_long = explode( ',', $_COOKIE['epgl'] );
					$lat      = $lat_long[0];
					$long     = $lat_long[1];
				}
			}

			if ( ! empty( $lat ) && ! empty( $long ) ) {
				$query->set( 'orderby', 'geo_distance' );
				$query->set( 'order', 'ASC' );
				$query->set(
					'geo_distance',
					array(
						/**
						 * Filter the maximum distance radius for location results
						 *
						 * @hook ep_geolocation_options
						 * @param  {string} $radius the maximum distance radius for location results (Example: 30mi, 20km)
						 * @param  {WP_Query} $query the search query
						 * @return  {string} the maximum distance radius for location results
						 */
						'distance'     => apply_filters( 'ep_geo_mile_radius', '30mi', $query ),
						'pin.location' => array(
							'lat' => (string) sanitize_text_field( $lat ),
							'lon' => (string) sanitize_text_field( $long ),
						),
					)
				);
			}
		}
	}
}
