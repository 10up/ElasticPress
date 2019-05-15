<?php
/**
 * Autosuggest feature
 *
 * @package elasticpress
 */

namespace ElasticPress\Feature\Autosuggest;

use ElasticPress\Feature as Feature;
use ElasticPress\Features as Features;
use ElasticPress\Utils as Utils;
use ElasticPress\FeatureRequirementsStatus as FeatureRequirementsStatus;
use ElasticPress\Indexables as Indexables;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Autosuggest feature class
 */
class Autosuggest extends Feature {

	/**
	 * Initialize feature setting it's config
	 *
	 * @since  3.0
	 */
	public function __construct() {
		$this->slug = 'autosuggest';

		$this->title = esc_html__( 'Autosuggest', 'elasticpress' );

		$this->requires_install_reindex = true;
		$this->default_settings         = [
			'endpoint_url'         => '',
			'defaults_enabled'     => 1,
			'autosuggest_selector' => '',
		];

		parent::__construct();
	}

	/**
	 * Output feature box summary
	 *
	 * @since 2.4
	 */
	public function output_feature_box_summary() {
		?>
		<p><?php esc_html_e( 'Suggest relevant content as text is entered into the search field.', 'elasticpress' ); ?></p>
		<?php
	}

	/**
	 * Output feature box long
	 *
	 * @since 2.4
	 */
	public function output_feature_box_long() {
		?>
		<p><?php esc_html_e( 'Input fields of type "search" or with the CSS class "search-field" or "ep-autosuggest" will be enhanced with autosuggest functionality. As text is entered into the search field, suggested content will appear below it, based on top search results for the text. Suggestions link directly to the content.', 'elasticpress' ); ?></p>
		<?php
	}

	/**
	 * Setup feature functionality
	 *
	 * @since  2.4
	 */
	public function setup() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_filter( 'ep_post_mapping', [ $this, 'mapping' ] );
		add_filter( 'ep_post_sync_args', [ $this, 'filter_term_suggest' ], 10 );
	}

	/**
	 * Display decaying settings on dashboard.
	 *
	 * @since 2.4
	 */
	public function output_feature_box_settings() {
		$settings = $this->get_settings();

		if ( ! $settings ) {
			$settings = [];
		}

		$settings = wp_parse_args( $settings, $this->default_settings );
		?>
		<div class="field js-toggle-feature" data-feature="<?php echo esc_attr( $this->slug ); ?>">
			<div class="field-name status"><?php esc_html_e( 'Post type/status term suggest', 'elasticpress' ); ?></div>
			<div class="input-wrap">
				<label for="defaults_enabled"><input name="defaults_enabled" id="defaults_enabled" data-field-name="defaults_enabled" class="setting-field" type="radio" <?php if ( (bool) $settings['defaults_enabled'] ) : ?>checked<?php endif; ?> value="1"><?php esc_html_e( 'Use plugin defaults', 'elasticpress' ); ?></label><br>
				<label for="defaults_disabled"><input name="defaults_enabled" id="defaults_disabled" data-field-name="defaults_enabled" class="setting-field" type="radio" <?php if ( ! (bool) $settings['defaults_enabled'] ) : ?>checked<?php endif; ?> value="0"><?php esc_html_e( 'Use safe values', 'elasticpress' ); ?></label>
			</div>
		</div>
		<div class="field js-toggle-feature" data-feature="<?php echo esc_attr( $this->slug ); ?>">
			<div class="field-name status"><label for="feature_autosuggest_selector"><?php esc_html_e( 'Autosuggest Selector', 'elasticpress' ); ?></label></div>
			<div class="input-wrap">
				<input value="<?php echo empty( $settings['autosuggest_selector'] ) ? 'ep-autosuggest' : esc_html( $settings['autosuggest_selector'] ); ?>" type="text" data-field-name="autosuggest_selector" class="setting-field" id="feature_autosuggest_selector">
				<p class="field-description"><?php esc_html_e( 'Input additional selectors where you would like to include autosuggest separated by a comma. Example: .custom-selector, #custom-id, input[type="text"]', 'elasticpress' ); ?></p>
			</div>
		</div>
		<?php

		if ( Utils\is_epio() ) {
			return;
		}

		$endpoint_url = ( defined( 'EP_AUTOSUGGEST_ENDPOINT' ) && EP_AUTOSUGGEST_ENDPOINT ) ? EP_AUTOSUGGEST_ENDPOINT : $settings['endpoint_url'];
		?>

		<div class="field js-toggle-feature" data-feature="<?php echo esc_attr( $this->slug ); ?>">
			<div class="field-name status"><label for="feature_autosuggest_endpoint_url"><?php esc_html_e( 'Endpoint URL', 'elasticpress' ); ?></label></div>
			<div class="input-wrap">
				<input <?php if ( defined( 'EP_AUTOSUGGEST_ENDPOINT' ) && EP_AUTOSUGGEST_ENDPOINT ) : ?>disabled<?php endif; ?> value="<?php echo esc_url( $endpoint_url ); ?>" type="text" data-field-name="endpoint_url" class="setting-field" id="feature_autosuggest_endpoint_url">
				<?php
				if ( defined( 'EP_AUTOSUGGEST_ENDPOINT' ) && EP_AUTOSUGGEST_ENDPOINT ) {
					?>
					<p class="field-description"><?php esc_html_e( 'Your autosuggest endpoint is set in wp-config.php', 'elasticpress' ); ?></p>
					<?php
				}
				?>
				<p class="field-description"><?php esc_html_e( 'This address will be exposed to the public.', 'elasticpress' ); ?></p>

			</div>
		</div>

		<?php
	}

	/**
	 * Add mapping for suggest fields
	 *
	 * @param  array $mapping ES mapping.
	 * @since  2.4
	 * @return array
	 */
	public function mapping( $mapping ) {
		$text_type = $mapping['mappings']['post']['properties']['post_content']['type'];

		$mapping['mappings']['post']['properties']['post_title']['fields']['suggest'] = array(
			'type'            => $text_type,
			'analyzer'        => 'edge_ngram_analyzer',
			'search_analyzer' => 'standard',
		);

		$mapping['settings']['analysis']['analyzer']['edge_ngram_analyzer'] = array(
			'type'      => 'custom',
			'tokenizer' => 'standard',
			'filter'    => array(
				'lowercase',
				'edge_ngram',
			),
		);

		$mapping['mappings']['post']['properties']['term_suggest'] = array(
			'type'            => $text_type,
			'analyzer'        => 'edge_ngram_analyzer',
			'search_analyzer' => 'standard',
		);

		return $mapping;
	}

	/**
	 * Add term suggestions to be indexed
	 *
	 * @param array $post_args Array of ES args.
	 * @since  2.4
	 * @return array
	 */
	public function filter_term_suggest( $post_args ) {
		$suggest = [];

		if ( ! empty( $post_args['terms'] ) ) {
			foreach ( $post_args['terms'] as $taxonomy ) {
				foreach ( $taxonomy as $term ) {
					$suggest[] = $term['name'];
				}
			}
		}

		if ( ! empty( $suggest ) ) {
			$post_args['term_suggest'] = $suggest;
		}

		return $post_args;
	}

	/**
	 * Enqueue our autosuggest script
	 *
	 * @since  2.4
	 */
	public function enqueue_scripts() {
		$host         = Utils\get_host();
		$endpoint_url = false;
		$feature      = Features::factory()->get_registered_feature( 'autosuggest' );
		$settings     = $this->get_settings();

		if ( defined( 'EP_AUTOSUGGEST_ENDPOINT' ) && EP_AUTOSUGGEST_ENDPOINT ) {
			$endpoint_url = EP_AUTOSUGGEST_ENDPOINT;
		} else {
			if ( Utils\is_epio() ) {
				$endpoint_url = $host . '/' . Indexables::factory()->get( 'post' )->get_index_name() . '/post/_search';
			} else {
				if ( ! $settings ) {
					$settings = [];
				}

				$settings = wp_parse_args( $settings, $this->default_settings );

				if ( empty( $settings['endpoint_url'] ) ) {
					return;
				}

				$endpoint_url = $settings['endpoint_url'];
			}
		}

		wp_enqueue_script(
			'elasticpress-autosuggest',
			EP_URL . 'dist/js/autosuggest.min.js',
			array( 'jquery' ),
			EP_VERSION,
			true
		);

		wp_enqueue_style(
			'elasticpress-autosuggest',
			EP_URL . 'dist/css/autosuggest.min.css',
			[],
			EP_VERSION
		);

		if ( ! isset( $settings['defaults_enabled'] ) || (bool) $settings['defaults_enabled'] ) {
			$post_types  = Indexables::factory()->get( 'post' )->get_indexable_post_types();
			$post_status = Indexables::factory()->get( 'post' )->get_indexable_post_status();
		} else {
			$post_types  = array( 'post', 'page' );
			$post_status = array( 'publish' );
		}

		/**
		 * Output variables to use in Javascript
		 * index: the Elasticsearch index name
		 * endpointUrl:  the Elasticsearch autosuggest endpoint url
		 * postType: which post types to use for suggestions
		 * action: the action to take when selecting an item. Possible values are "search" and "navigate".
		 */
		wp_localize_script(
			'elasticpress-autosuggest',
			'epas',
			apply_filters(
				'ep_autosuggest_options',
				array(
					'endpointUrl'  => esc_url( untrailingslashit( $endpoint_url ) ),
					'postType'     => apply_filters( 'ep_term_suggest_post_type', array_values( $post_types ) ),
					'postStatus'   => apply_filters( 'ep_term_suggest_post_status', array_values( $post_status ) ),
					'selector'     => esc_html( $settings['autosuggest_selector'] ),
					'searchFields' => apply_filters(
						'ep_term_suggest_search_fields',
						array(
							'post_title.suggest',
							'term_suggest',
						)
					),
					'action'       => 'navigate',
				)
			)
		);
	}

	/**
	 * Tell user whether requirements for feature are met or not.
	 *
	 * @since 2.4
	 */
	public function requirements_status() {
		$status = new FeatureRequirementsStatus( 0 );

		$status->message = [];

		$status->message[] = esc_html__( 'This feature modifies the site’s default user experience by presenting a list of suggestions below detected search fields as text is entered into the field.', 'elasticpress' );

		if ( ! Utils\is_epio() ) {
			$status->code      = 1;
			$status->message[] = wp_kses_post( __( "You aren't using <a href='https://elasticpress.io'>ElasticPress.io</a> so we can't be sure your host is properly secured. Autosuggest requires a publicly accessible endpoint, which can expose private content and allow data modification if improperly configured.", 'elasticpress' ) );
		}

		return $status;
	}
}

