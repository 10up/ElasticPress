<?php
/**
 * Semantic Search Feature
 *
 * @since 5.4.0
 * @package ElasticPress
 */

namespace ElasticPress\Feature\SemanticSearch;

use ElasticPress\Feature;
use ElasticPress\FeatureRequirementsStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Semantic Search Feature
 */
class SemanticSearch extends Feature {
	/**
	 * Group
	 *
	 * @var string $group.
	 */
	public $group = 'ai';

	/**
	 * Default settings
	 *
	 * @var array $default_settings.
	 */
	public $default_settings = [
		'search_algorithm_version' => 'hybrid_knn',
		'search_min_score'         => 0.7,
	];

	/**
	 * The algorithms supported by the feature.
	 *
	 * @var array $algorithms.
	 */
	protected $algorithms = [];

	/**
	 * Initialize feature setting it's config
	 */
	public function __construct() {
		$this->slug = 'semantic_search';

		$this->requires_feature = 'vector_embeddings';

		parent::__construct();
	}

	/**
	 * Tell user whether requirements for feature are met or not.
	 *
	 * @return FeatureRequirementsStatus Requirements object
	 */
	public function requirements_status() {
		$status = new FeatureRequirementsStatus( 1 );

		$es_version = \ElasticPress\Elasticsearch::factory()->get_elasticsearch_version();

		// Vector support was added in Elasticsearch 7.0.
		if ( $es_version && version_compare( $es_version, '7.0', '<' ) ) {
			$status->code    = 2;
			$status->message = esc_html__( 'You need to have Elasticsearch with version >7.0.', 'elasticpress' );
		}

		if ( $es_version && version_compare( $es_version, '7.0', '>' ) && version_compare( $es_version, '8.0', '<' ) ) {
			$status->code    = 1;
			$status->message = esc_html__( 'With Elasticsearch version 7, only the "kNN Cosine" algorithm is available.', 'elasticpress' );
		}

		return $status;
	}

	/**
	 * Sets i18n strings.
	 *
	 * @return void
	 * @since 5.4.0
	 */
	public function set_i18n_strings(): void {
		$this->title = esc_html__( 'Semantic Search', 'elasticpress' );

		$this->summary = __( 'Enable kNN (k-Nearest Neighbors) search and choose which vector search algorithm to use. Requires the Vector Embeddings feature to be enabled.', 'elasticpress' );
	}

	/**
	 * Pre-handle feature activation
	 *
	 * @since 5.4.0
	 * @return void
	 */
	public function pre_handle_feature_activation() {
		$vector_embeddings = \ElasticPress\Features::factory()->get_registered_feature( 'vector_embeddings' );
		if ( ! $vector_embeddings->is_active() ) {
			return;
		}

		if ( ! $this->is_active() ) {
			return;
		}

		$this->maybe_set_algorithms();

		add_filter( 'ep_feature_requirements_status_message', [ $this, 'filter_search_algorithm_requirements_status_message' ], 10, 2 );
		add_filter( 'ep_feature_requirements_status_message', [ $this, 'filter_temp_disabled_features_status_message' ], 10, 2 );
		add_filter( 'ep_feature_requirements_status_code', [ $this, 'maybe_disable_autosuggest_and_instant_results' ], 10, 2 );
	}

	/**
	 * Connects the Module with WordPress using Hooks and/or Filters.
	 *
	 * @return void
	 */
	public function setup() {
		// In older versions of ElasticPress, the algorithms were not set in the pre_handle_feature_activation method.
		$this->maybe_set_algorithms();

		add_filter( 'ep_post_search_algorithm', [ $this, 'get_search_algorithm_version' ] );

		$vector_embeddings = \ElasticPress\Features::factory()->get_registered_feature( 'vector_embeddings' );
		$is_epio           = 'epio' === $vector_embeddings->get_setting( 'ep_embeddings_generator' );
		$search_algorithm  = \ElasticPress\Indexables::factory()->get( 'post' )->get_search_algorithm( '', [], [] );

		if ( $is_epio && in_array( $search_algorithm, $this->algorithms, true ) ) {
			add_filter( 'ep_query_request_args', [ $this, 'add_vector_embeddings_header' ], 10, 6 );
			add_action( 'wp_enqueue_scripts', [ $this, 'add_autosuggest_http_header' ] );
		}
	}

	/**
	 * Maybe set the algorithms
	 *
	 * @return void
	 */
	protected function maybe_set_algorithms() {
		if ( ! empty( $this->algorithms ) ) {
			return;
		}

		$this->algorithms = [
			new SearchAlgorithm\KnnCosine(),
		];

		$es_version = \ElasticPress\Elasticsearch::factory()->get_elasticsearch_version();

		if ( version_compare( $es_version, '8.0', '>=' ) ) {
			$this->algorithms[] = new SearchAlgorithm\Hybrid();
			$this->algorithms[] = new SearchAlgorithm\Knn();
		}

		foreach ( $this->algorithms as $algorithm ) {
			\ElasticPress\SearchAlgorithms::factory()->register( $algorithm );
		}
	}

	/**
	 * Set the `settings_schema` attribute
	 */
	public function set_settings_schema() {
		$this->maybe_set_algorithms();

		$algorithm_options = [];
		foreach ( $this->algorithms as $algorithm ) {
			$algorithm_options[] = [
				'label' => $algorithm->get_name() . '<br><small>' . $algorithm->get_description() . '</small>',
				'value' => $algorithm->get_slug(),
			];
		}

		$this->settings_schema = [
			[
				'default' => $this->get_default_algorithm(),
				'key'     => 'search_algorithm_version',
				'label'   => __( 'Search algorithm', 'elasticpress' ),
				'options' => $algorithm_options,
				'type'    => 'radio',
			],
			[
				'key'     => 'search_min_score',
				'label'   => __( 'Minimum score', 'elasticpress' ),
				'help'    => __( 'The minimum score to be used by kNN searches. Input a number between 0 and 1.', 'elasticpress' ),
				'type'    => 'number',
				'default' => $this->default_settings['search_min_score'],
			],
		];
	}

	/**
	 * Get the slugs of the search algorithms supported by the feature.
	 *
	 * @since 5.4.0
	 * @return string[] Array of algorithm slugs.
	 */
	protected function get_algorithm_slugs() {
		$this->maybe_set_algorithms();

		return array_map(
			function ( $algorithm ) {
				return $algorithm->get_slug();
			},
			$this->algorithms
		);
	}

	/**
	 * Get the default search algorithm slug.
	 *
	 * Falls back to the first available algorithm when the preferred default
	 * (Hybrid) is not supported by the current Elasticsearch version.
	 *
	 * @since 5.4.0
	 * @return string The default algorithm slug.
	 */
	protected function get_default_algorithm() {
		$available = $this->get_algorithm_slugs();
		$preferred = $this->default_settings['search_algorithm_version'];

		if ( in_array( $preferred, $available, true ) ) {
			return $preferred;
		}

		return ! empty( $available ) ? reset( $available ) : $preferred;
	}

	/**
	 * Get the currently selected search algorithm slug.
	 *
	 * Hooked to `ep_post_search_algorithm` while the feature is active. If the
	 * stored selection is not a currently available algorithm, the default is
	 * used instead.
	 *
	 * @since 5.4.0
	 * @param string $search_algorithm The fallback search algorithm slug.
	 * @return string The search algorithm slug.
	 */
	public function get_search_algorithm_version( $search_algorithm ) {
		$version = $this->get_setting( 'search_algorithm_version' );

		if ( ! in_array( $version, $this->get_algorithm_slugs(), true ) ) {
			$version = $this->get_default_algorithm();
		}

		return $version;
	}

	/**
	 * Get the min_score setting.
	 *
	 * @return float
	 */
	public function get_min_score() {
		/**
		 * Filters the minimum score for KNN (k-Nearest Neighbors) search.
		 *
		 * @since 5.4.0
		 * @param {float} $min_score The minimum score for KNN search. Default is retrieved from the 'search_min_score' setting.
		 * @return {float} The minimum score for KNN search.
		 */
		return (float) apply_filters( 'ep_semantic_search_min_score', $this->get_setting( 'search_min_score' ) );
	}

	/**
	 * Add the vector embeddings header to the request arguments.
	 *
	 * @param array  $request_args The request arguments.
	 * @param string $path The path of the request.
	 * @param string $index The index of the request.
	 * @param string $type The type of the request.
	 * @param array  $query The query of the request.
	 * @param array  $query_args The query arguments of the request.
	 * @return array The request arguments.
	 */
	public function add_vector_embeddings_header( $request_args, $path, $index, $type, $query, $query_args ) {
		$request_args['headers']['EP-Vector-Embeddings-Search-Term'] = $query_args['s'] ? rawurlencode( $query_args['s'] ) : '';

		return $request_args;
	}

	/**
	 * Add the vector embeddings header to the request arguments.
	 *
	 * @return void
	 */
	public function add_autosuggest_http_header() {
		wp_add_inline_script(
			'elasticpress-autosuggest',
			"const epAutosuggestFetchOptions = (fetchOptions) => {
				fetchOptions.headers['EP-Vector-Embeddings-Search-Term'] = fetchOptions.headers['EP-Search-Term'];
				return fetchOptions;
			};
			wp.hooks.addFilter('ep.Autosuggest.fetchOptions', 'myTheme/epAutosuggestFetchOptions', epAutosuggestFetchOptions);",
			'before'
		);
	}

	/**
	 * Filter the Semantic Search feature requirements status message
	 *
	 * Warns that the selected kNN algorithm is incompatible with Autosuggest
	 * and Instant Results when either of those features is active.
	 *
	 * @since 5.4.0
	 * @param string|array              $message The message to display
	 * @param FeatureRequirementsStatus $status The feature requirements status object
	 * @return string|array The message to display
	 */
	public function filter_search_algorithm_requirements_status_message( $message, $status ) {
		$feature = $status->get_feature();
		if ( ! $feature || 'semantic_search' !== $feature->slug ) {
			return $message;
		}

		$autosuggest            = \ElasticPress\Features::factory()->get_registered_feature( 'autosuggest' );
		$autosuggest_active     = $autosuggest && $autosuggest->is_active();
		$instant_results        = \ElasticPress\Features::factory()->get_registered_feature( 'instant-results' );
		$instant_results_active = $instant_results && $instant_results->is_active();
		if ( ! $autosuggest_active && ! $instant_results_active ) {
			return $message;
		}

		$algorithms_list = array_map(
			function ( $algorithm ) {
				return '<code>' . $algorithm->get_name() . '</code>';
			},
			$this->algorithms
		);

		$message   = (array) $message;
		$message[] = wp_sprintf(
			esc_html__( 'Please note that Semantic Search algorithms (%l) are not compatible with the Autosuggest and Instant Results features. Autosuggest and Instant Results will be disabled while those algorithms are selected.', 'elasticpress' ),
			$algorithms_list
		);
		return $message;
	}

	/**
	 * Maybe disable Autosuggest and Instant Results features
	 *
	 * @since 5.4.0
	 * @param int                       $code   The code of the feature requirements status
	 * @param FeatureRequirementsStatus $status The feature requirements status object
	 * @return int The new code of the feature requirements status
	 */
	public function maybe_disable_autosuggest_and_instant_results( $code, $status ) {
		if ( ! $this->should_disable_autosuggest_and_instant_results( $status->get_feature() ) ) {
			return $code;
		}

		return defined( '\ElasticPress\FeatureRequirementsStatus::TEMPORARILY_DISABLED' )
			? FeatureRequirementsStatus::TEMPORARILY_DISABLED
			: 2;
	}

	/**
	 * Filter the temporarily disabled features status message
	 *
	 * @since 5.4.0
	 * @param string|array              $message The message to display
	 * @param FeatureRequirementsStatus $status The feature requirements status object
	 * @return string|array The message to display
	 */
	public function filter_temp_disabled_features_status_message( $message, $status ) {
		if ( ! $this->should_disable_autosuggest_and_instant_results( $status->get_feature() ) ) {
			return $message;
		}

		$message   = (array) $message;
		$message[] = esc_html__( 'This feature is temporarily disabled because it is incompatible with Semantic Search algorithms.', 'elasticpress' );
		return $message;
	}

	/**
	 * Check if Autosuggest and Instant Results features should be temporarily disabled
	 *
	 * @since 5.4.0
	 * @param Feature $feature The feature object
	 * @return bool True if the feature should be disabled, false otherwise
	 */
	protected function should_disable_autosuggest_and_instant_results( $feature ) {
		if ( ! $feature || ! isset( $feature->slug ) || ! in_array( $feature->slug, [ 'autosuggest', 'instant-results' ], true ) ) {
			return false;
		}

		if ( ! $this->is_active() ) {
			return false;
		}

		return in_array( $this->get_search_algorithm_version( '' ), $this->get_algorithm_slugs(), true );
	}
}
