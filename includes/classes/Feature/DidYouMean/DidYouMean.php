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

		$this->group = 'core-search';

		$this->requires_install_reindex = true;

		$this->available_during_installation = true;

		$this->default_settings = [
			'search_behavior' => '0',
		];

		$this->requires_feature = 'search';

		parent::__construct();
	}

	/**
	 * Sets i18n strings.
	 *
	 * @return void
	 * @since 5.2.0
	 */
	public function set_i18n_strings(): void {
		$this->title = esc_html__( 'Did You Mean', 'elasticpress' );

		$this->summary = '<p>' . __( '"Did You Mean" search feature provides alternative suggestions for misspelled or ambiguous search queries, enhancing search accuracy and user experience. To display suggestions in your theme, please follow <a href="https://www.elasticpress.io/documentation/article/did-you-mean/">this tutorial</a>.', 'elasticpress' ) . '</p>';

		$this->docs_url = __( 'https://www.elasticpress.io/documentation/article/did-you-mean/', 'elasticpress' );
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
	 * Add mapping.
	 *
	 * @param array $mapping Post mapping.
	 */
	public function add_mapping( $mapping ): array {
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
	public function add_query_args( $formatted_args, $args, $wp_query ): array {
		if ( ! empty( $args['ep_did_you_mean_exact_check'] ) || ( isset( $args['ep_suggestion'] ) && false === $args['ep_suggestion'] ) ) {
			return $formatted_args;
		}

		/**
		 * Filter whether Did You Mean should be skipped when an exact match exists in the current search scope.
		 *
		 * @since 5.3.0
		 * @hook ep_did_you_mean_skip_for_exact_match
		 * @param {bool}     $skip          Whether to skip Did You Mean for exact matches.
		 * @param {WP_Query} $wp_query      WP_Query object.
		 * @param {array}    $args          WP_Query arguments.
		 * @param {array}    $formatted_args Formatted Elasticsearch query.
		 * @return {bool} New value.
		 */
		$skip_for_exact_match = apply_filters( 'ep_did_you_mean_skip_for_exact_match', false, $wp_query, $args, $formatted_args );

		if ( ! $skip_for_exact_match && $this->has_exact_match_in_search_scope( $wp_query ) ) {
			return $formatted_args;
		}

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
	public function set_ep_suggestion( $enabled, $query ): bool {
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
	public function requirements_status(): FeatureRequirementsStatus {
		return new FeatureRequirementsStatus( 1, null, $this );
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
	 * Whether the current search term has an exact match in the current search scope.
	 *
	 * @param WP_Query $query WP_Query object.
	 * @return bool
	 */
	protected function has_exact_match_in_search_scope( $query ): bool {
		$search_term = trim( (string) ( $query->query_vars['s'] ?? '' ) );

		if ( '' === $search_term ) {
			return false;
		}

		$query_args                                = $query->query_vars;
		$query_args['s']                           = $search_term;
		$query_args['ep_did_you_mean_exact_check'] = true;
		$query_args['ep_integrate']                = true;
		$query_args['posts_per_page']              = 1;
		$query_args['no_found_rows']               = false;
		$query_args['fields']                      = 'ids';

		$exact_match_query_filter = function ( $query_clause, $query_vars ) {
			if ( empty( $query_vars['ep_did_you_mean_exact_check'] ) ) {
				return $query_clause;
			}

			return $this->remove_fuzziness_from_query( $query_clause );
		};

		add_filter( 'ep_post_formatted_args_query', $exact_match_query_filter, 20, 4 );
		$exact_match_query = new \WP_Query( $query_args );
		remove_filter( 'ep_post_formatted_args_query', $exact_match_query_filter, 20 );

		if ( empty( $exact_match_query->elasticsearch_success ) ) {
			return false;
		}

		return ! empty( $exact_match_query->found_posts );
	}

	/**
	 * Remove fuzziness from the query tree so exact-check queries follow the same field scope as regular search.
	 *
	 * @param array $query_clause Elasticsearch query clause.
	 * @return array
	 */
	protected function remove_fuzziness_from_query( array $query_clause ): array {
		foreach ( $query_clause as $key => $value ) {
			if ( 'fuzziness' === $key ) {
				unset( $query_clause[ $key ] );
				continue;
			}

			if ( is_array( $value ) ) {
				$query_clause[ $key ] = $this->remove_fuzziness_from_query( $value );
			}
		}

		return $query_clause;
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
