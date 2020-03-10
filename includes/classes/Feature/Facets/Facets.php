<?php
/**
 * Facets feature
 *
 * @since  2.5
 * @package  elasticpress
 */

namespace ElasticPress\Feature\Facets;

use ElasticPress\Feature as Feature;
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
	 * Facet query
	 *
	 * @var array
	 */
	public $placeholder_query = [];

	/**
	 * Initialize feature setting it's config
	 *
	 * @since  3.0
	 */
	public function __construct() {
		$this->slug = 'facets';

		$this->title = esc_html__( 'Facets', 'elasticpress' );

		$this->requires_install_reindex = false;
		$this->default_settings         = [
			'match_type'   => 'all',
			'ajax_enabled' => false,
		];

		parent::__construct();
	}

	/**
	 * Setup hooks and filters for feature
	 *
	 * @since 2.5
	 */
	public function setup() {
		add_action( 'widgets_init', [ $this, 'register_widgets' ] );
		add_action( 'ep_valid_response', [ $this, 'get_aggs' ], 10, 4 );
		add_filter( 'ep_post_formatted_args', [ $this, 'set_agg_filters' ], 10, 3 );
		add_action( 'pre_get_posts', [ $this, 'facet_query' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_scripts' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'front_scripts' ] );
		add_action( 'ep_feature_box_settings_facets', [ $this, 'settings' ], 10, 1 );

		$settings = $this->get_settings();

		if ( true === (bool) $settings['ajax_enabled'] ) {
			add_filter( 'the_posts', [ $this, 'inject_templating_post' ], PHP_INT_MAX, 2 );
			add_action( 'the_post', [ $this, 'maybe_buffer_template_item' ] );
			add_action( 'loop_end', [ $this, 'maybe_capture_template_output' ] );
			add_action( 'wp_footer', [ $this, 'output_templating_post' ] );
			add_filter( 'post_link', [ $this, 'maybe_replace_post_link' ], 10, 2 );
		}

	}

	/**
	 * Replace permalink for templating purpose
	 *
	 * @param String   $link Post permalink
	 * @param \WP_Post $post Post Object
	 *
	 * @return string
	 */
	public function maybe_replace_post_link( $link, $post ) {
		if ( - 99999999999 === $post->ID ) {
			$link = '{{PERMALINK}}';
		}

		return $link;
	}

	/**
	 * HTML Output of the templating post
	 */
	public function output_templating_post() {
		global $ep_facet_output;

		$settings = $this->get_settings();

		if( ! empty( $settings['ajax_template'] ) ) {
			$template = file_get_contents( locate_template( $settings['ajax_template'] ) );
			echo $template;
		} else{
			if ( ! empty( $ep_facet_output ) ) {
				echo '<template id="ep-facet-sample-result">' . $ep_facet_output . '</template>'; // phpcs:ignore
			}
		}

	}

	/**
	 * Start output buffering for template post
	 *
	 * @param \WP_Post $post Post object
	 */
	public function maybe_buffer_template_item( $post ) {
		global $ep_facet_buffer;
		if ( - 99999999999 === $post->ID ) {
			ob_start();
			$ep_facet_buffer = true;
		}
	}

	/**
	 * Stop autput buffering for template post
	 *
	 * @param \WP_Query $query WP Query
	 */
	public function maybe_capture_template_output( $query ) {
		global $ep_facet_buffer, $ep_facet_output;
		if ( true === $ep_facet_buffer ) {
			$ep_facet_output = ob_get_clean();
			$ep_facet_buffer = false;
		}
	}

	/**
	 * Inject fake post to search loop
	 *
	 * @param \WP_Post  $post  Post object
	 * @param \WP_Query $query WP Query
	 *
	 * @return array
	 */
	public function inject_templating_post( $post, $query ) {
		if ( $query->is_main_query() && $query->is_search() ) {
			$fake_post                        = new \stdClass();
			$fake_post->ID                    = - 99999999999;
			$fake_post->post_author           = 1;
			$fake_post->post_date             = current_time( 'mysql' );
			$fake_post->post_date_gmt         = current_time( 'mysql' );
			$fake_post->post_title            = '{{POST_TITLE}}';
			$fake_post->post_excerpt          = '{{POST_EXCERPT}}';
			$fake_post->post_content          = '{{POST_CONTENT}}';
			$fake_post->post_content_filtered = '{{POST_CONTENT_FILTERED}}';
			$fake_post->post_status           = 'publish';
			$fake_post->comment_status        = 'closed';
			$fake_post->ping_status           = 'closed';
			$fake_post->post_name             = 'ep-facets-templating-post';
			$fake_post->post_type             = 'post';
			$fake_post->filter                = 'raw';
			$fake_post->permalink             = '{{PERMALINK}}';

			$templating_post = new \WP_Post( $fake_post );

			$post[]          = $templating_post;
		}

		return $post;
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
		echo '<pre>';
		print_r( $settings );
		echo '</pre>';
		?>
		<div class="field js-toggle-feature" data-feature="<?php echo esc_attr( $this->slug ); ?>">
			<div class="field-name status"><?php esc_html_e( 'Match Type', 'elasticpress' ); ?></div>
			<div class="input-wrap">
				<label for="match_type_all"><input name="match_type" id="match_type_all" data-field-name="match_type" class="setting-field" type="radio" <?php if ( 'all' === $settings['match_type'] ) : ?>checked<?php endif; ?> value="all"><?php echo wp_kses_post( __( 'Show any content tagged to <strong>all</strong> selected terms', 'elasticpress' ) ); ?></label><br>
				<label for="match_type_any"><input name="match_type" id="match_type_any" data-field-name="match_type" class="setting-field" type="radio" <?php if ( 'any' === $settings['match_type'] ) : ?>checked<?php endif; ?> value="any"><?php echo wp_kses_post( __( 'Show all content tagged to <strong>any</strong> selected term', 'elasticpress' ) ); ?></label>
				<p class="field-description"><?php esc_html_e( '"All" will only show content that matches all facets. "Any" will show content that matches any facet.', 'elasticpress' ); ?></p>
			</div>
		</div>

		<div class="field js-toggle-feature" data-feature="<?php echo esc_attr( $this->slug ); ?>">
			<div class="field-name status"><?php esc_html_e( 'AJAX', 'elasticpress' ); ?></div>
			<div class="input-wrap">
				<label for="ajax_enabled"><input name="ajax_enabled" id="ajax_enabled" data-field-name="ajax_enabled" class="setting-field" type="radio" <?php if ( (bool) $settings['ajax_enabled'] ) : ?>checked<?php endif; ?> value="1"><?php esc_html_e( 'Enabled', 'elasticpress' ); ?></label><br>
				<label for="ajax_disabled"><input name="ajax_enabled" id="ajax_disabled" data-field-name="ajax_enabled" class="setting-field" type="radio" <?php if ( ! (bool) $settings['ajax_enabled'] ) : ?>checked<?php endif; ?> value="0"><?php esc_html_e( 'Disabled', 'elasticpress' ); ?></label>
			</div>
		</div>

		<div class="field js-toggle-feature" data-feature="<?php echo esc_attr( $this->slug ); ?>">
			<div class="field-name status"><label for="feature_ajax_selector"><?php esc_html_e( 'DOM Selector', 'elasticpress' ); ?></label></div>
			<div class="input-wrap">
				<input value="<?php echo empty( $settings['ajax_selector'] ) ? '#main' : esc_html( $settings['ajax_selector'] ); ?>" type="text" data-field-name="ajax_selector" class="setting-field" id="feature_ajax_selector">
				<p class="field-description"><?php esc_html_e( 'Target selector to replace the content.', 'elasticpress' ); ?></p>
			</div>
		</div>

		<div class="field js-toggle-feature" data-feature="<?php echo esc_attr( $this->slug ); ?>">
			<div class="field-name status"><label for="feature_ajax_template"><?php esc_html_e( 'Template File', 'elasticpress' ); ?></label></div>
			<div class="input-wrap">
				<input value="<?php echo empty( $settings['ajax_template'] ) ? '' : esc_html( $settings['ajax_template'] ); ?>" type="text" data-field-name="ajax_template" class="setting-field" id="feature_ajax_template">
				<p class="field-description"><?php esc_html_e( 'Add a path from the root of your theme to use as the template for showing a post in the ajax results. E.g. partials/file-name.php', 'elasticpress' ); ?></p>
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
	 * @since  2.5
	 * @return array
	 */
	public function set_agg_filters( $args, $query_args, $query ) {
		if ( empty( $query_args['ep_facet'] ) ) {
			return $args;
		}

		/**
		 * Catch current query for FE use
		 */
		$this->placeholder_query = $args;

		// @todo For some reason these are appearing in the query args, need to investigate
		unset( $query_args['category_name'] );
		unset( $query_args['cat'] );
		unset( $query_args['tag'] );
		unset( $query_args['tag_id'] );
		unset( $query_args['taxonomy'] );
		unset( $query_args['term'] );

		$facet_query_args = $query_args;

		$settings = $this->get_settings();

		$settings = wp_parse_args(
			$settings,
			array(
				'match_type' => 'all',
			)
		);

		if ( ! empty( $facet_query_args['tax_query'] ) ) {
			remove_filter( 'ep_post_formatted_args', [ $this, 'set_agg_filters' ], 10, 3 );

			foreach ( $facet_query_args['tax_query'] as $key => $taxonomy ) {
				if ( is_array( $taxonomy ) ) {
					if ( 'any' === $settings['match_type'] ) {
						unset( $facet_query_args['tax_query'][ $key ] );
					}
				}
			}

			$facet_formatted_args = Indexables::factory()->get( 'post' )->format_args( $facet_query_args, $query );

			$args['aggs']['terms']['filter'] = $facet_formatted_args['post_filter'];

			add_filter( 'ep_post_formatted_args', [ $this, 'set_agg_filters' ], 10, 3 );
		}

		return $args;
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
			[],
			EP_VERSION
		);
	}

	/**
	 * Output front end facets styles
	 *
	 * @since 2.5
	 */
	public function front_scripts() {
		wp_enqueue_script(
			'elasticpress-facets',
			EP_URL . 'dist/js/facets-script.min.js',
			[ 'jquery', 'underscore' ],
			EP_VERSION,
			true
		);

		wp_enqueue_style(
			'elasticpress-facets',
			EP_URL . 'dist/css/facets-styles.min.css',
			[],
			EP_VERSION
		);

		$settings = $this->get_settings();

		$facet_options = [
			'ajax_enabled' => (int) ( $settings['ajax_enabled'] ),
			'selector'     => empty( $settings['ajax_selector'] ) ? '' : esc_html( $settings['ajax_selector'] ),
			'query'        => $this->placeholder_query,
			'match_type'   => $settings['match_type'],
			'endpointUrl'  => trailingslashit( Utils\get_host() ) . Indexables::factory()->get( 'post' )->get_index_name() . '/_search',
		];

		if ( Utils\is_epio() ) {
			$facet_options['addFacetsHeader'] = true;
		}

		wp_localize_script(
			'elasticpress-facets',
			'epfacets',
			$facet_options
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

		if ( ! ( ( function_exists( 'is_product_category' ) && is_product_category() )
			|| $query->is_post_type_archive()
			|| $query->is_search()
			|| ( is_home() && empty( $query->get( 'page_id' ) ) ) )
		) {
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
	 * @since  2.5
	 */
	public function facet_query( $query ) {
		if ( ! $this->is_facetable( $query ) ) {
			return;
		}

		$taxonomies = get_taxonomies( array( 'public' => true ), 'object' );

		/**
		 * Filter taxonomies made available for faceting
		 *
		 * @hook ep_facet_include_taxonomies
		 * @param  {array} $taxonomies Taxonomies
		 * @return  {array} New taxonomies
		 */
		$taxonomies = apply_filters( 'ep_facet_include_taxonomies', $taxonomies );

		if ( empty( $taxonomies ) ) {
			return;
		}

		$query->set( 'ep_integrate', true );
		$query->set( 'ep_facet', true );

		$facets = [];

		foreach ( $taxonomies as $slug => $taxonomy ) {
			$facets[ $slug ] = array(
				'terms' => array(
					'size'  => 10000,
					'field' => 'terms.' . $slug . '.slug',
				),
			);
		}

		$aggs = array(
			'name'       => 'terms',
			'use-filter' => true,
			'aggs'       => $facets,
		);

		$query->set( 'aggs', $aggs );

		$selected_filters = $this->get_selected();

		$settings = $this->get_settings();

		$settings = wp_parse_args(
			$settings,
			array(
				'match_type' => 'all',
			)
		);

		$tax_query = $query->get( 'tax_query', [] );

		// Account for taxonomies that should be woocommerce attributes, if WC is enabled
		$attribute_taxonomies = [];
		if ( function_exists( 'wc_attribute_taxonomy_name' ) ) {
			$all_attr_taxonomies = wc_get_attribute_taxonomies();

			foreach ( $all_attr_taxonomies as $attr_taxonomy ) {
				$attribute_taxonomies[ $attr_taxonomy->attribute_name ] = wc_attribute_taxonomy_name( $attr_taxonomy->attribute_name );
			}
		}

		foreach ( $selected_filters['taxonomies'] as $taxonomy => $filter ) {
			$tax_query[] = [
				'taxonomy' => isset( $attribute_taxonomies[ $taxonomy ] ) ? $attribute_taxonomies[ $taxonomy ] : $taxonomy,
				'field'    => 'slug',
				'terms'    => array_keys( $filter['terms'] ),
				'operator' => ( 'any' === $settings['match_type'] ) ? 'or' : 'and',
			];
		}

		if ( ! empty( $selected_filters['taxonomies'] ) && 'any' === $settings['match_type'] ) {
			$tax_query['relation'] = 'or';
		}

		$query->set( 'tax_query', $tax_query );
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
		if ( empty( $query_object ) || 'WP_Query' !== get_class( $query_object ) || ! $query_object->is_main_query() ) {
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
	 * @since  2.5
	 * @return array
	 */
	public function get_selected() {
		$filters = array(
			'taxonomies' => [],
		);

		foreach ( $_GET as $key => $value ) { // phpcs:ignore WordPress.Security.NonceVerification
			if ( 0 === strpos( $key, 'filter' ) ) {
				$taxonomy = str_replace( 'filter_', '', $key );

				$filters['taxonomies'][ $taxonomy ] = array(
					'terms' => array_fill_keys( array_map( 'trim', explode( ',', trim( $value, ',' ) ) ), true ),
				);
			}
		}

		return $filters;
	}

	/**
	 * Build query url
	 *
	 * @since  2.5
	 * @param  array $filters Facet filters
	 * @return string
	 */
	public function build_query_url( $filters ) {
		$query_string = '';

		$s = get_search_query();

		if ( ! empty( $s ) ) {
			$query_string .= 's=' . $s;
		}

		if ( ! empty( $filters['taxonomies'] ) ) {
			$tax_filters = $filters['taxonomies'];

			foreach ( $tax_filters as $taxonomy => $filter ) {
				if ( ! empty( $filter['terms'] ) ) {
					if ( ! empty( $query_string ) ) {
						$query_string .= '&';
					}

					$query_string .= 'filter_' . $taxonomy . '=' . implode( ',', array_keys( $filter['terms'] ) );
				}
			}
		}

		/**
		 * Filter facet query string
		 *
		 * @hook ep_facet_query_string
		 * @param  {string} $query_string Current query string
		 * @return  {string} New query string
		 */
		$query_string = apply_filters( 'ep_facet_query_string', $query_string );

		if ( is_post_type_archive() ) {
			$pagination = strpos( $_SERVER['REQUEST_URI'], '/page' );

			if ( false !== $pagination ) {
				$url = substr( $_SERVER['REQUEST_URI'], 0, $pagination );
				return strtok( $url, '?' ) . ( ( ! empty( $query_string ) ) ? '/?' . $query_string : '' );
			}
		}

		return strtok( $_SERVER['REQUEST_URI'], '?' ) . ( ( ! empty( $query_string ) ) ? '?' . $query_string : '' );
	}

	/**
	 * Register facet widget(s)
	 *
	 * @since 2.5
	 */
	public function register_widgets() {
		register_widget( __NAMESPACE__ . '\Widget' );
	}

	/**
	 * Output feature box summary
	 *
	 * @since 2.5
	 */
	public function output_feature_box_summary() {
		?>
		<p><?php esc_html_e( 'Add controls to your website to filter content by one or more taxonomies.', 'elasticpress' ); ?></p>
		<?php
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
}
