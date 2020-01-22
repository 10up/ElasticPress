<?php
/**
 * Search feature
 *
 * @since  1.9
 * @package  elasticpress
 */

namespace ElasticPress\Feature\Search;

use ElasticPress\Features as Features;
use ElasticPress\Feature as Feature;
use ElasticPress\Indexables as Indexables;

/**
 * Search feature class
 */
class Search extends Feature {

	/**
	 * Weighting Class (Sub Feature)
	 *
	 * @var Weighting
	 */
	public $weighting;

	/**
	 * Initialize feature setting it's config
	 *
	 * @since  3.0
	 */
	public function __construct() {
		$this->slug = 'search';

		$this->title = esc_html__( 'Post Search', 'elasticpress' );

		$this->requires_install_reindex = false;
		$this->default_settings         = [
			'decaying_enabled' => true,
			'narrow_search'    => false,
		];

		parent::__construct();
	}

	/**
	 * We need to delay search setup up since it will fire after protected content and protected
	 * content filters into the search setup
	 *
	 * @since 2.2
	 */
	public function setup() {
		add_action( 'init', [ $this, 'search_setup' ] );

		// Set up weighting sub-module
		$this->weighting = new Weighting();
		$this->weighting->setup();
	}

	/**
	 * Setup feature on each page load
	 *
	 * @since  3.0
	 */
	public function search_setup() {
		/**
		 * By default EP will not integrate on admin or ajax requests. Since admin-ajax.php is
		 * technically an admin request, there is some weird logic here. If we are doing ajax
		 * and ep_ajax_wp_query_integration is filtered true, then we skip the next admin check.
		 */

		/**
		 * Filter to integrate with admin queries
		 *
		 * @hook ep_admin_wp_query_integration
		 * @param  {bool} $integrate True to integrate
		 * @return  {bool} New value
		 */
		$admin_integration = apply_filters( 'ep_admin_wp_query_integration', false );

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			/**
			 * Filter to integrate with admin ajax queries
			 *
			 * @hook ep_ajax_wp_query_integration
			 * @param  {bool} $integrate True to integrate
			 * @return  {bool} New value
			 */
			if ( ! apply_filters( 'ep_ajax_wp_query_integration', false ) ) {
				return;
			} else {
				$admin_integration = true;
			}
		}

		if ( is_admin() && ! $admin_integration ) {
			return;
		}

		add_filter( 'ep_elasticpress_enabled', [ $this, 'integrate_search_queries' ], 10, 2 );
		add_filter( 'ep_formatted_args', [ $this, 'weight_recent' ], 10, 2 );
		add_filter( 'ep_query_post_type', [ $this, 'filter_query_post_type_for_search' ], 10, 2 );
		add_filter( 'ep_formatted_args', [ $this, 'set_to_exact' ], 10, 2 );
	}

	/**
	 * Returns searchable post types for the current site
	 *
	 * @since 1.9
	 * @return mixed|void
	 */
	public function get_searchable_post_types() {
		$post_types = get_post_types( array( 'exclude_from_search' => false ) );

		/**
		 * Don't search attachments by default
		 *
		 * @since  3.0
		 */
		unset( $post_types['attachment'] );

		/**
		 * Filter searchable post types
		 *
		 * @hook ep_searchable_post_types
		 * @param  {array} $post_types Post types
		 * @return  {array} New post types
		 */
		return apply_filters( 'ep_searchable_post_types', $post_types );
	}

	/**
	 * Make sure we don't search for "any" on a search query
	 *
	 * @param  string   $post_type Post type
	 * @param  WP_Query $query WP Query
	 * @return string|array
	 */
	public function filter_query_post_type_for_search( $post_type, $query ) {
		if ( 'any' === $post_type && $query->is_search() ) {
			$searchable_post_types = $this->get_searchable_post_types();

			// If we have no searchable post types, there's no point going any further
			if ( empty( $searchable_post_types ) ) {

				// Have to return something or it improperly calculates the found_posts
				return false;
			}

			// Conform the post types array to an acceptable format for ES
			$post_types = [];

			foreach ( $searchable_post_types as $type ) {
				$post_types[] = $type;
			}

			// These are now the only post types we will search
			$post_type = $post_types;
		}

		return $post_type;
	}

	/**
	 * Returns true/false if decaying is/isn't enabled
	 *
	 * @return bool
	 */
	public function is_decaying_enabled() {
		$settings = $this->get_settings();

		$settings = wp_parse_args(
			$settings,
			[
				'decaying_enabled' => true,
			]
		);

		return (bool) $settings['decaying_enabled'];
	}

	/**
	 * Weight more recent content in searches
	 *
	 * @param  array $formatted_args Formatted ES args
	 * @param  array $args WP_Query args
	 * @since  2.1
	 * @return array
	 */
	public function weight_recent( $formatted_args, $args ) {
		if ( ! empty( $args['s'] ) ) {
			if ( $this->is_decaying_enabled() ) {
				$date_score = array(
					'function_score' => array(
						'query'      => $formatted_args['query'],
						'functions'  => array(
							array(
								'exp' => array(
									'post_date_gmt' => array(
										/**
										 * Filter search date weighting scale
										 *
										 * @hook epwr_scale
										 * @param  {string} $scale Current scale
										 * @param  {array} $formatted_args Formatted Elasticsearch arguments
										 * @param  {array} $args WP_Query arguments
										 * @return  {string} New scale
										 */
										'scale'  => apply_filters( 'epwr_scale', '14d', $formatted_args, $args ),
										/**
										 * Filter search date weighting decay
										 *
										 * @hook epwr_decay
										 * @param  {string} $decay Current decay
										 * @param  {array} $formatted_args Formatted Elasticsearch arguments
										 * @param  {array} $args WP_Query arguments
										 * @return  {string} New decay
										 */
										'decay'  => apply_filters( 'epwr_decay', .25, $formatted_args, $args ),
										/**
										 * Filter search date weighting offset
										 *
										 * @hook epwr_offset
										 * @param  {string} $offset Current offset
										 * @param  {array} $formatted_args Formatted Elasticsearch arguments
										 * @param  {array} $args WP_Query arguments
										 * @return  {string} New offset
										 */
										'offset' => apply_filters( 'epwr_offset', '7d', $formatted_args, $args ),
									),
								),
							),
						),
						'score_mode' => 'avg',
						/**
						 * Filter search date weighting boost mode
						 *
						 * @hook epwr_boost_mode
						 * @param  {string} $boost_mode Current boost mode
						 * @param  {array} $formatted_args Formatted Elasticsearch arguments
						 * @param  {array} $args WP_Query arguments
						 * @return  {string} New boost mode
						 */
						'boost_mode' => apply_filters( 'epwr_boost_mode', 'sum', $formatted_args, $args ),
					),
				);

				$formatted_args['query'] = $date_score;
			}
		}

		return $formatted_args;
	}

	/**
	 * Output feature box summary
	 *
	 * @since 3.0
	 */
	public function output_feature_box_summary() {
		?>
		<p><?php esc_html_e( 'Instantly find the content you’re looking for. The first time.', 'elasticpress' ); ?></p>
		<?php
	}

	/**
	 * Output feature box long text
	 *
	 * @since 3.0
	 */
	public function output_feature_box_long() {
		?>
		<p><?php esc_html_e( 'Overcome higher-end performance and functional limits posed by the traditional WordPress structured (SQL) database to deliver superior keyword search, instantly. ElasticPress indexes custom fields, tags, and other metadata to improve search results. Fuzzy matching accounts for misspellings and verb tenses.', 'elasticpress' ); ?></p>

		<?php
	}

	/**
	 * Enable integration on search queries
	 *
	 * @param  bool     $enabled Original enabled value
	 * @param  WP_Query $query WP Query
	 * @since  2.1
	 * @return bool
	 */
	public function integrate_search_queries( $enabled, $query ) {
		if ( ! is_a( $query, '\WP_Query' ) ) {
			return $enabled;
		}

		if ( method_exists( $query, 'is_search' ) && $query->is_search() && ! empty( $query->query_vars['s'] ) ) {
			$enabled = true;

			/**
			 * WordPress have to be version 4.6 or newer to have "fields" support
			 * since it requires the "posts_pre_query" filter.
			 *
			 * @see WP_Query::get_posts
			 */
			$fields = $query->get( 'fields' );
			if ( ! version_compare( get_bloginfo( 'version' ), '4.6', '>=' ) && ! empty( $fields ) ) {
				$enabled = false;
			}
		}

		return $enabled;
	}

	/**
	 * Display decaying settings on dashboard.
	 *
	 * @since 2.4
	 */
	public function output_feature_box_settings() {
		$settings = $this->get_settings();
		$settings = ! empty( $settings ) ? $settings : [];

		$settings = wp_parse_args( $settings, $this->default_settings );
		?>
		<div class="field js-toggle-feature" data-feature="<?php echo esc_attr( $this->slug ); ?>">
			<div class="field-name status"><?php esc_html_e( 'Weight results by date', 'elasticpress' ); ?></div>
			<div class="input-wrap">
				<label for="decaying_enabled"><input name="decaying_enabled" id="decaying_enabled" data-field-name="decaying_enabled" class="setting-field" type="radio" <?php if ( (bool) $settings['decaying_enabled'] ) : ?>checked<?php endif; ?> value="1"><?php esc_html_e( 'Enabled', 'elasticpress' ); ?></label><br>
				<label for="decaying_disabled"><input name="decaying_enabled" id="decaying_disabled" data-field-name="decaying_enabled" class="setting-field" type="radio" <?php if ( ! (bool) $settings['decaying_enabled'] ) : ?>checked<?php endif; ?> value="0"><?php esc_html_e( 'Disabled', 'elasticpress' ); ?></label>
			</div>
		</div>
		<div class="field js-toggle-feature" data-feature="<?php echo esc_attr( $this->slug ); ?>">
			<div class="field-name narrow-search"><?php esc_html_e( 'Narrow Search', 'elasticpress' ); ?></div>
			<div class="input-wrap">
				<label for="narrow_search_enabled"><input name="narrow_search" id="narrow_search_enabled" data-field-name="narrow_search" class="setting-field" type="radio" <?php if ( (bool) $settings['narrow_search'] ) : ?>checked<?php endif; ?> value="1" /><?php esc_html_e( 'Enabled', 'elasticpress' ); ?></label><br />
				<label for="narrow_search_disabled"><input name="narrow_search" id="narrow_search_disabled" data-field-name="narrow_search" class="setting-field" type="radio" <?php if ( ! (bool) $settings['narrow_search'] ) : ?>checked<?php endif; ?> value="0" /><?php esc_html_e( 'Disabled', 'elasticpress' ); ?></label>
			</div>
			<br class="clear">
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=elasticpress-weighting' ) ); ?>"><?php esc_html_e( 'Advanced fields and weighting settings', 'elasticpress' ); ?></a></p>
		</div>
		<?php
	}

	/**
	 * Narrow search results if enabled in settings.
	 *
	 * @param array $formatted_args Formatted query arguments.
	 * @param array $args           Query arguments.
	 * @return array                Modified formatted arguments.
	 */
	public function set_to_exact( $formatted_args, $args ) {
		// Bail early if not a search query.
		if ( empty( $args['s'] ) ) {
			return $formatted_args;
		}

		$feature  = Features::factory()->get_registered_feature( 'search' );
		$settings = method_exists( $feature, 'get_settings' ) ? $feature->get_settings() : [];

		$settings = wp_parse_args(
			$settings,
			[
				'decaying_enabled' => true,
				'narrow_search'    => false,
			]
		);

		// Bail early if not narrowing search.
		if ( ! $settings['narrow_search'] ) {
			return $formatted_args;
		}

		// Bail early if no should query.
		if ( empty( $formatted_args['query']['function_score']['query']['bool']['should'] ) ) {
			return $formatted_args;
		}

		// Create/change the bool query from should to must.
		$formatted_args['query']['function_score']['query']['bool']['must'] = $formatted_args['query']['function_score']['query']['bool']['should'];

		// Add the operator AND to the new bool query.
		$formatted_args['query']['function_score']['query']['bool']['must'][0]['multi_match']['operator'] = 'AND';

		// Erase the old should query.
		unset( $formatted_args['query']['function_score']['query']['bool']['should'] );

		// Erase the phrase matching (or not, if you don't want it).
		unset( $formatted_args['query']['function_score']['query']['bool']['must'][0]['multi_match']['type'] );

		return $formatted_args;
	}
}
