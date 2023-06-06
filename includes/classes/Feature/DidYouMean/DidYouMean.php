<?php
/**
 * Did You Mean feature.
 *
 * @since   4.6.0
 * @package elasticpress
 */

namespace ElasticPress\Feature\DidYouMean;

use ElasticPress\{Elasticsearch, Feature, FeatureRequirementsStatus, Features };

/**
 * Did You Mean feature class.
 */
class DidYouMean extends Feature {

	/**
	 * Initialize feature, setting it's config.
	 */
	public function __construct() {
		$this->slug = 'did-you-mean';

		$this->title = esc_html__( 'Did You Mean', 'elasticpress' );

		$this->summary = __( '"Did You Mean" search feature provides alternative suggestions for misspelled or ambiguous search queries, enhancing search accuracy and user experience.', 'elasticpress' );

		$this->requires_install_reindex = true;

		$this->available_during_installation = true;

		$this->default_settings = [
			'search_behavior' => false,
		];

		parent::__construct();
	}

	/**
	 * Setup search functionality.
	 *
	 * @return void
	 */
	public function setup() {
		add_filter( 'ep_post_mapping', [ $this, 'add_mapping' ] );
		add_filter( 'ep_post_formatted_args', [ $this, 'add_query_args' ], 10, 3 );
		add_filter( 'ep_integrate_search_queries', [ $this, 'set_ep_suggestion' ], 10, 2 );
		add_action( 'template_redirect', [ $this, 'automatically_redirect_user' ] );
	}

	/**
	 * Output feature box long.
	 *
	 * @return void
	 */
	public function output_feature_box_long() {
		?>
		<p><?php esc_html_e( '"Did You Mean" search feature provides alternative suggestions for misspelled or ambiguous search queries, enhancing search accuracy and user experience.', 'elasticpress' ); ?></p>
		<?php
	}

	/**
	 * Add mapping.
	 *
	 * @param array $mapping Post mapping.
	 */
	public function add_mapping( $mapping ) : array {
		// Shingle token filter.
		$mapping['settings']['analysis']['filter']['shingle_filter'] = [
			'type'             => 'shingle',
			'min_shingle_size' => 2,
			'max_shingle_size' => 3,
		];

		// Custom analyzer.
		$mapping['settings']['analysis']['analyzer']['trigram'] = [
			'type'      => 'custom',
			'tokenizer' => 'standard',
			'filter'    => [
				'lowercase',
				'shingle_filter',
			],
		];

		if ( version_compare( Elasticsearch::factory()->get_elasticsearch_version(), '7.0', '<' ) ) {
			$mapping['mappings']['post']['properties']['post_content']['fields'] = [
				'shingle' => [
					'type'     => 'text',
					'analyzer' => 'trigram',
				],
			];
		} else {
			$mapping['mappings']['properties']['post_content']['fields'] = [
				'shingle' => [
					'type'     => 'text',
					'analyzer' => 'trigram',
				],
			];
		}

		return $mapping;
	}

	/**
	 * Return the suggested search term.
	 *
	 * @param WP_Query $query WP_Query object
	 * @return string|false
	 */
	public function get_suggestion( $query = null ) {
		global $wp_query;

		$settings = $this->get_settings();
		if ( empty( $settings['active'] ) ) {
			return false;
		}

		if ( ! $query && $wp_query->is_main_query() && $wp_query->is_search() ) {
			$query = $wp_query;
		}

		if ( ! is_a( $query, '\WP_Query' ) ) {
			return false;
		}

		$term = $this->get_suggested_term( $query );
		if ( empty( $term ) ) {
			return false;
		}

		$html = sprintf( '<span class="ep-suggested-spell-term">%s: <a href="%s">%s</a>?</span>', esc_html__( 'Did you mean', 'elasticpress' ), get_search_link( $term ), $term );

		$html .= $this->get_alternatives_terms( $query );
		$terms = $query->suggested_terms['options'] ?? [];

		/**
		 * Filter the did you mean suggested term HTML.
		 *
		 * @since 4.6.0
		 * @hook ep_did_you_mean_suggested_term_html
		 * @param {string}   $html The HTML output.
		 * @param {array}    $terms All suggested terms.
		 * @param {WP_Query} $query The WP_Query object.
		 * @return {string}  New $html value
		 */
		return apply_filters( 'ep_did_you_mean_suggestion_html', $html, $terms, $query );
	}

	/**
	 * If needed set the `suggest` to ES query clause.
	 *
	 * @param array $formatted_args Formatted Elasticsearch query.
	 * @param array $args           WP_Query arguments
	 * @param array $wp_query       WP_Query object
	 */
	public function add_query_args( $formatted_args, $args, $wp_query ) : array {
		$search_analyzer = [
			'phrase' => [
				'field'            => 'post_content.shingle',
				'max_errors'       => 2,
				'direct_generator' => [
					[
						'field' => 'post_content.shingle',
					],
				],
			],
		];

		/**
		 * Filter the search analyzer use for the did you mean feature.
		 *
		 * @since 4.6.0
		 * @hook ep_search_suggestion_analyzer
		 * @param {array} $search_analyzer Search analyzer
		 * @param {array} $formatted_args Formatted Elasticsearch query
		 * @param {array} $args WP_Query arguments
		 * @param {WP_Query} $wp_query WP_Query object
		 * @return {array} New search analyzer
		 */
		$search_analyzer = apply_filters( 'ep_search_suggestion_analyzer', $search_analyzer, $formatted_args, $args, $wp_query );

		if ( ! empty( $args['s'] ) ) {
			$formatted_args['suggest'] = array(
				'text'          => $args['s'],
				'ep_suggestion' => $search_analyzer,
			);
		}

		return $formatted_args;
	}

	/**
	 * Set the ep_suggestion flag to true if the query is a search query.
	 *
	 * @param bool     $enabled Whether to enable the search queries integration.
	 * @param WP_Query $query   The WP_Query object.
	 */
	public function set_ep_suggestion( $enabled, $query ) : bool {
		if ( $query->is_search() && ! empty( $query->query_vars['s'] ) ) {
			$query->set( 'ep_suggestion', true );
		}

		return $enabled;
	}

	/**
	 * Returns requirements status of feature
	 *
	 * Requires the search feature to be activated
	 */
	public function requirements_status() : FeatureRequirementsStatus {
		$features = Features::factory();
		$search   = $features->get_registered_feature( 'search' );

		if ( ! $search->is_active() ) {
			return new FeatureRequirementsStatus( 2, esc_html__( 'This feature requires the "Post Search" feature to be enabled', 'elasticpress' ) );
		}

		return parent::requirements_status();
	}

	/**
	 * Display feature settings.
	 *
	 * @return void
	 */
	public function output_feature_box_settings() {
		$settings = $this->get_settings();
		?>
		<div class="field">
			<div class="field-name status"><?php esc_html_e( 'Search Behavior when no result found.', 'elasticpress' ); ?></div>
			<div class="input-wrap">
				<label><input name="settings[search_behavior]" type="radio" <?php checked( ! (bool) $settings['search_behavior'] ); ?> value="0"><?php esc_html_e( 'Default', 'elasticpress' ); ?></label><br>
				<label><input name="settings[search_behavior]" type="radio" <?php checked( $settings['search_behavior'], 'list' ); ?> value="list"><?php esc_html_e( 'Display all the suggestions', 'elasticpress' ); ?></label><br>
				<label><input name="settings[search_behavior]" type="radio" <?php checked( $settings['search_behavior'], 'redirect' ); ?> value="redirect"><?php esc_html_e( 'Automatically redirects user to top suggestion', 'elasticpress' ); ?></label><br>
			</div>
		</div>
		<?php
	}

	/**
	 * Returns the list of other suggestions
	 *
	 * @param WP_Query $query WP_Query object
	 * @return string|false
	 */
	protected function get_alternatives_terms( $query ) {
		global $wp_query;

		if ( ! $query && $wp_query->is_main_query() && $wp_query->is_search() ) {
			$query = $wp_query;
		}

		if ( ! is_a( $query, '\WP_Query' ) ) {
			return false;
		}

		$settings = $this->get_settings();

		// If there are posts, we don't need to show the list of suggestions.
		if ( 'list' !== $settings['search_behavior'] || $query->found_posts ) {
			return false;
		}

		$options = $query->suggested_terms['options'] ?? [];
		array_shift( $options );

		if ( empty( $options ) ) {
			return '';
		}

		$html  = '<div class="ep-spell-suggestions">';
		$html .= esc_html__( 'Other suggestions:', 'elasticpress' );
		$html .= '<ul class="ep-suggestions-list">';
		foreach ( $options as $option ) {
			$html .= sprintf( '<li><a href="%s">%s</a></li>', get_search_link( $option['text'] ), $option['text'] );
		}
		$html .= '</ul>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Returns the top suggested term
	 *
	 * @param WP_Query $query WP_Query object
	 * @return string|bool
	 */
	public function get_suggested_term( $query ) {
		$options = $query->suggested_terms['options'] ?? [];
		return ! empty( $options ) ? $options[0]['text'] : false;
	}

	/**
	 * Redirect user to suggested search term if no results found and search_behavior is set to redirect.
	 *
	 * @return void
	 */
	public function automatically_redirect_user() {
		global $wp_query;

		if ( ! $wp_query->is_main_query() || ! $wp_query->is_search() ) {
			return;
		}

		if ( $wp_query->found_posts ) {
			return;
		}

		$settings = $this->get_settings();
		if ( 'redirect' !== $settings['search_behavior'] ) {
			return;
		}

		$term = $this->get_suggested_term( $wp_query );
		if ( empty( $term ) ) {
			return;
		}

		$url = get_search_link( $term );
		wp_safe_redirect( $url );
		exit;
	}
}
