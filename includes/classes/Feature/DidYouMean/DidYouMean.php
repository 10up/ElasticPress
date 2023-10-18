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

		$this->summary = __( '<p>Recommend alternative search terms for misspelled queries or terms with no results.</p><p>"Did You Mean" search feature provides alternative suggestions for misspelled or ambiguous search queries, enhancing search accuracy and user experience. To display suggestions in your theme, please follow <a href="https://elasticpress.zendesk.com/hc/en-us/articles/16673223107085-Did-You-Mean">this tutorial</a>.</p>', 'elasticpress' );

		$this->docs_url = __( 'https://elasticpress.zendesk.com/hc/en-us/articles/16673223107085-Did-You-Mean', 'elasticpress' );

		$this->requires_install_reindex = true;

		$this->available_during_installation = true;

		$this->default_settings = [
			'search_behavior' => '0',
		];

		$this->requires_feature = 'search';

		$this->set_settings_schema();

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
		add_action( 'ep_suggestions', [ $this, 'the_output' ] );
	}

	/**
	 * Output feature box long.
	 *
	 * @return void
	 */
	public function output_feature_box_long() {
		?>
		<p>
			<?php
			echo wp_kses_post(
				sprintf(
					/* translators: Tutorial URL */
					__( '"Did You Mean" search feature provides alternative suggestions for misspelled or ambiguous search queries, enhancing search accuracy and user experience. To display suggestions in your theme, please follow <a href="%s">this tutorial</a>.', 'elasticpress' ),
					'https://elasticpress.zendesk.com/hc/en-us/articles/16673223107085-Did-You-Mean'
				)
			);
			?>
		</p>
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

		if ( version_compare( (string) Elasticsearch::factory()->get_elasticsearch_version(), '7.0', '<' ) ) {
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

		$html = sprintf( '<span class="ep-spell-suggestion">%s: <a href="%s">%s</a>?</span>', esc_html__( 'Did you mean', 'elasticpress' ), get_search_link( $term ), $term );

		$html .= $this->get_alternatives_terms( $query );
		$terms = $query->suggested_terms['options'] ?? [];

		/**
		 * Filter the did you mean suggested HTML.
		 *
		 * @since 4.6.0
		 * @hook ep_suggestion_html
		 * @param {string}   $html The HTML output.
		 * @param {array}    $terms All suggested terms.
		 * @param {WP_Query} $query The WP_Query object.
		 * @return {string}  New HTML output
		 */
		return apply_filters( 'ep_suggestion_html', $html, $terms, $query );
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
		return new FeatureRequirementsStatus( 1 );
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
			<div class="field-name status"><?php esc_html_e( 'Search behavior when no result is found', 'elasticpress' ); ?></div>
			<div class="input-wrap">
				<label><input name="settings[search_behavior]" type="radio" <?php checked( ! (bool) $settings['search_behavior'] ); ?> value="0"><?php esc_html_e( 'Display the top suggestion', 'elasticpress' ); ?></label><br>
				<label><input name="settings[search_behavior]" type="radio" <?php checked( $settings['search_behavior'], 'list' ); ?> value="list"><?php esc_html_e( 'Display all the suggestions', 'elasticpress' ); ?></label><br>
				<label><input name="settings[search_behavior]" type="radio" <?php checked( $settings['search_behavior'], 'redirect' ); ?> value="redirect"><?php esc_html_e( 'Automatically redirect the user to the top suggestion', 'elasticpress' ); ?></label><br>
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
		$url = add_query_arg(
			[
				'ep_suggestion_original_term' => $wp_query->query_vars['s'],
			],
			$url
		);

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Return a message to the user when the original search term has no results and the user is redirected to the suggested term.
	 *
	 * @param WP_Query $query WP_Query object
	 *
	 * @return string|void
	 */
	public function get_original_search_term( $query = null ) {
		global $wp_query;

		$settings = $this->get_settings();
		if ( empty( $settings['active'] ) ) {
			return false;
		}

		if ( ! $query && $wp_query->is_main_query() && $wp_query->is_search() ) {
			$query = $wp_query;
		}

		if ( ! is_a( $query, '\WP_Query' ) ) {
			return;
		}

		if ( ! isset( $_GET['ep_suggestion_original_term'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$settings = $this->get_settings();
		if ( 'redirect' !== $settings['search_behavior'] ) {
			return;
		}

		$original_term = sanitize_text_field( wp_unslash( $_GET['ep_suggestion_original_term'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$html = sprintf(
			'<div class="ep-original-search-term-message">
			<span class="result">%s</span><strong>%s</strong>
			<br/>
			<span class="no-result">%s</span><strong>%s</strong>
			</div>',
			esc_html__( 'Showing results for: ', 'elasticpress' ),
			esc_html( $query->query_vars['s'] ),
			esc_html__( 'No results for: ', 'elasticpress' ),
			esc_html( $original_term )
		);

		/**
		 * Filter the HTML output for the original search term.
		 *
		 * @since 4.6.0
		 * @hook ep_suggestion_original_search_term_html
		 * @param {string} $html HTML output
		 * @param {string} $search_term Suggested search term
		 * @param {string} $original_term Original search term
		 * @param {WP_Query} $query WP_Query object
		 * @return {string} New HTML output
		 */
		return apply_filters( 'ep_suggestion_original_search_term_html', $html, $query->query_vars['s'], $original_term, $query );
	}

	/**
	 * Returns the suggestion
	 *
	 * @param WP_Query $query WP_Query object
	 * @return void
	 */
	public function the_output( $query = null ) {
		$html  = $this->get_original_search_term( $query );
		$html .= $this->get_suggestion( $query );

		echo wp_kses_post( $html );
	}

	/**
	 * Set the `settings_schema` attribute
	 *
	 * @since 5.0.0
	 */
	protected function set_settings_schema() {
		$this->settings_schema = [
			[
				'default' => '0',
				'key'     => 'search_behavior',
				'label'   => __( 'Search behavior when no result is found', 'elasticpress' ),
				'options' => [
					[
						'label' => __( 'Display the top suggestion', 'elasticpress' ),
						'value' => '0',
					],
					[
						'label' => __( 'Display all the suggestions', 'elasticpress' ),
						'value' => 'list',
					],
					[
						'label' => __( 'Automatically redirect the user to the top suggestion', 'elasticpress' ),
						'value' => 'redirect',
					],
				],
				'type'    => 'radio',
			],
		];
	}
}
