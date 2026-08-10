<?php
/**
 * Vector Embeddings
 *
 * This feature enables storage of vector embeddings, a numerical representation of the
 * indexed content that can capture semantic relationships and similarities between data points.
 * These embeddings are often used by AI models to process and understand complex information
 * more efficiently and are used for features like natural language processing, recommendations and computer vision.
 *
 * @since 5.4.0
 * @package ElasticPress
 */

namespace ElasticPress\Feature\VectorEmbeddings;

use ElasticPress\Feature;
use ElasticPress\Elasticsearch;
use ElasticPress\Utils;
use ElasticPress\Traits\DisableAfterFailures;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Vector Embeddings feature
 */
class VectorEmbeddings extends Feature {
	use DisableAfterFailures;

	/**
	 * Group
	 *
	 * @var string $group.
	 */
	public $group = 'ai';

	/**
	 * Array of VectorEmbeddings\Indexable objects
	 *
	 * @var array
	 */
	protected $indexables = [];

	/**
	 * Default settings
	 *
	 * @var array $default_settings.
	 */
	public $default_settings = [
		'ep_embeddings_generator'       => 'openai',
		'ep_embeddings_api_key'         => '',
		'ep_embeddings_api_url'         => 'https://api.openai.com/v1/embeddings',
		'ep_embeddings_embedding_model' => 'text-embedding-3-small',
		'ep_embeddings_dimensions'      => 512,
	];

	/**
	 * Settings Page Module
	 *
	 * @var SettingsPage
	 */
	public $settings_page;

	/**
	 * Initialize feature setting it's config
	 */
	public function __construct() {
		$this->slug = 'vector_embeddings';

		$this->requires_install_reindex = true;

		try {
			if ( $this->is_epio_available() ) {
				$this->default_settings['ep_embeddings_generator'] = 'epio';
			}
		} catch ( \Exception $e ) {
			// no-op
		}

		parent::__construct();
	}

	/**
	 * Sets i18n strings.
	 *
	 * @return void
	 * @since 5.4.0
	 */
	public function set_i18n_strings(): void {
		$this->title = esc_html__( 'Vector Embeddings', 'elasticpress' );

		$this->summary = __(
			'This feature enables storage of vector embeddings, a numerical representation of the indexed content that can capture semantic relationships and similarities between data points. These embeddings are often used by AI models to process and understand complex information more efficiently and are used for features like natural language processing, recommendations and computer vision.',
			'elasticpress'
		);

		$this->field_group_map = [
			'ep_embeddings_openai' => [
				'label'           => esc_html__( 'OpenAI Connection Details', 'elasticpress' ),
				'requires_fields' => [
					'conditions' => [
						'ep_embeddings_generator' => 'openai',
					],
				],
			],
		];
	}

	/**
	 * Connects the Module with WordPress using Hooks and/or Filters.
	 *
	 * @return void
	 */
	public function setup() {
		$this->indexables['post'] = new Indexables\Post\Post( $this );
		$this->indexables['post']->setup();

		add_filter( 'ep_query_logger_allowed_log_types', [ $this, 'add_vector_embeddings_to_allowed_log_types' ] );

		if ( 'epio' === $this->get_setting( 'ep_embeddings_generator' ) ) {
			add_filter( 'ep_status_report_reports', [ $this, 'add_status_report' ] );
		}
	}

	/**
	 * Pre-handle feature activation
	 *
	 * @since 5.4.0
	 * @return void
	 */
	public function pre_handle_feature_activation() {
		$this->setup_failures_count();
	}

	/**
	 * Tell user whether requirements for feature are met or not.
	 *
	 * @return FeatureRequirementsStatus Requirements object
	 * @throws \Exception If the ElasticPress plugin is not updated. Handled by the method itself.
	 */
	public function requirements_status() {
		$status = new \ElasticPress\FeatureRequirementsStatus( 1 );

		$status->message = [];

		// Vector support was added in Elasticsearch 7.0.
		$es_version = Elasticsearch::factory()->get_elasticsearch_version();
		if ( $es_version && version_compare( $es_version, '7.0', '<' ) ) {
			$status->code      = 2;
			$status->message[] = esc_html__( 'You need to have Elasticsearch with version >7.0.', 'elasticpress' );
			return $status;
		}

		$recommended_bulk_setting = 30;
		if ( Utils\get_option( 'ep_bulk_setting', 350 ) > $recommended_bulk_setting ) {
			if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
				$url = admin_url( 'network/admin.php?page=elasticpress-settings' );
			} else {
				$url = admin_url( 'admin.php?page=elasticpress-settings' );
			}

			$status->message[] = wp_kses_post(
				sprintf(
					/* translators: 1: Settings URL, 2: Recommended bulk setting, 3: WP-CLI URL */
					__( 'While using OpenAI, as each content piece is processed individually, consider using a low number of items per sync cycle in the <a href="%1$s">Settings page</a>, like %2$d. You can also try running it via <a href="%3$s">WP-CLI</a>.', 'elasticpress' ),
					esc_url( $url ),
					absint( $recommended_bulk_setting ),
					esc_url( 'https://10up.github.io/ElasticPress/tutorial-wp-cli.html' )
				)
			);
		}

		if ( $this->should_disable_after_failures() ) {
			$status = $this->update_requirements_status( $status );
			return $status;
		}

		if ( ! $this->is_epio_beta_available() ) {
			return $status;
		}

		if ( Utils\is_epio() ) {
			try {
				if ( ! $this->is_epio_available() ) {
					$status->message[] = wp_kses_post(
						sprintf(
							/* translators: %s: Upgrade URL */
							__( 'Your current ElasticPress.io plan does not include the vector embeddings service. <a href="%s" target="_blank">Upgrade now!</a>', 'elasticpress' ),
							'https://www.elasticpress.io/resources/articles/how-to-upgrade-your-elasticpress-io-plan/'
						)
					);
				}
			} catch ( \Exception $e ) {
				$error_message = $e->getMessage();

				$status->message[] = 'old_ep_version' === $error_message
					? esc_html__( 'You need to update the ElasticPress plugin to use this feature.', 'elasticpress' )
					: $error_message;
			}
		}

		return $status;
	}

	/**
	 * Set the `settings_schema` attribute
	 */
	public function set_settings_schema() {
		$generator = [
			'key'     => 'ep_embeddings_generator',
			'label'   => __( 'Generator', 'elasticpress' ),
			'options' => [
				[
					'label' => __( 'External embedding processing', 'elasticpress' ),
					'value' => 'external',
				],
				[
					'label' => __( 'OpenAI', 'elasticpress' ),
					'value' => 'openai',
				],
			],
			'help'    => __( '<b>External embedding processing:</b> An external process is providing the vector_embeddings meta field.<br><b>OpenAI:</b> Use the fields below to generate the embeddings.', 'elasticpress' ),
			'default' => 'openai',
			'type'    => 'radio',
		];

		try {
			if ( $this->is_epio_available() ) {
				array_unshift(
					$generator['options'],
					[
						'label' => __( 'ElasticPress.io', 'elasticpress' ),
						'value' => 'epio',
					]
				);

				$generator['help']    = __( '<b>ElasticPress.io:</b> Your vectors will be generated by the ElasticPress.io service.<br>', 'elasticpress' ) . $generator['help'];
				$generator['default'] = 'epio';
			}
		} catch ( \Exception $e ) {
			// no-op
		}

		$this->settings_schema = [
			...$this->settings_schema,
			$generator,
			[
				'key'              => 'ep_embeddings_api_key',
				'label'            => __( 'OpenAI API Key', 'elasticpress' ),
				'help'             => sprintf(
					wp_kses(
						/* translators: 1: OpenAI sign up URL, 2: OpenAI API keys URL */
						__( 'Don\'t have an OpenAI account yet? <a title="Sign up for an OpenAI account" href="%1$s">Sign up for one</a> in order to get your API key.<br>If you already have an account, <a title="Get your API key from the OpenAI website" href="%2$s">generate an API key</a>.', 'elasticpress' ),
						[
							'a'  => [
								'href'  => [],
								'title' => [],
							],
							'br' => [],
						]
					),
					esc_url( 'https://platform.openai.com/signup' ),
					esc_url( 'https://platform.openai.com/api-keys' )
				),
				'type'             => 'text',
				'field_group_slug' => 'ep_embeddings_openai',
			],
			[
				'help'             => __( 'OpenAI Embeddings API Url', 'elasticpress' ),
				'key'              => 'ep_embeddings_api_url',
				'label'            => __( 'OpenAI Embeddings API Url', 'elasticpress' ),
				'type'             => 'text',
				'default'          => $this->default_settings['ep_embeddings_api_url'],
				'field_group_slug' => 'ep_embeddings_openai',
			],
			[
				'help'             => __( 'OpenAI Embedding model', 'elasticpress' ),
				'key'              => 'ep_embeddings_embedding_model',
				'label'            => __( 'The name of the embedding model to use', 'elasticpress' ),
				'type'             => 'text',
				'default'          => $this->default_settings['ep_embeddings_embedding_model'],
				'field_group_slug' => 'ep_embeddings_openai',
			],
			[
				'help'             => __( 'Embedding model dimensions', 'elasticpress' ),
				'key'              => 'ep_embeddings_dimensions',
				'label'            => __( 'The number of dimensions supported by your embedding model', 'elasticpress' ),
				'type'             => 'number',
				'default'          => $this->default_settings['ep_embeddings_dimensions'],
				'field_group_slug' => 'ep_embeddings_openai',
			],
		];
	}

	/**
	 * Add vector embeddings to the allowed log types.
	 *
	 * @param array $allowed_log_types The allowed log types.
	 * @return array The allowed log types.
	 */
	public function add_vector_embeddings_to_allowed_log_types( $allowed_log_types ) {
		$allowed_log_types['vector_embeddings'] = [ $this, 'is_query_error' ];

		return $allowed_log_types;
	}

	/**
	 * Check if the request is an error.
	 *
	 * @param array $query The query.
	 * @return boolean Whether the request is an error.
	 */
	public function is_query_error( $query ) {
		if ( is_wp_error( $query['request'] ) ) {
			return true;
		}

		$response_code = wp_remote_retrieve_response_code( $query['request'] );

		return ( $response_code < 200 || $response_code > 299 );
	}

	/**
	 * Add a new status report
	 *
	 * @param array $reports Status reports.
	 * @return array
	 */
	public function add_status_report( $reports ) {
		$reports[] = new StatusReport();

		return $reports;
	}

	/**
	 * Get an embedding from a given strings or array of strings.
	 *
	 * @param int          $object_id   The Object ID.
	 * @param string       $object_type The Object type.
	 * @param string|array $text        String or array of strings to get the embedding for.
	 * @return array|null|WP_Error
	 */
	public function get_embedding( int $object_id, string $object_type, $text ) {
		// Generate the embedding.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::debug(
				sprintf(
					/* translators: 1: object type, 2: object id. */
					__( 'Generating embedding for %1$s ID: %2$s', 'elasticpress' ),
					$object_type,
					$object_id
				)
			);
		}

		return $this->generate_embedding( $text );
	}

	/**
	 * Generate an embedding for a particular piece of text.
	 *
	 * @param string|array $text Text (or array of strings) to generate the embedding for.
	 * @return array|boolean|WP_Error
	 * @throws \Exception If trying to use ElasticPress.io as a generator.
	 */
	public function generate_embedding( $text = '' ) {
		if ( 'epio' === $this->get_setting( 'ep_embeddings_generator' ) ) {
			throw new \Exception( 'ElasticPress.io does not support vector embeddings requests.' );
		}

		$request  = new VectorEmbeddingsRequest( $this );
		$response = $request->send_request( $text );

		/**
		 * Filter the response of the request.
		 *
		 * @hook ep_embeddings_request_response
		 * @since 5.4.0
		 *
		 * @param {array|WP_Error} $response The request response.
		 * @param {array|string}   $text     The text that was sent to be processed.
		 * @return {array|WP_Error} The request response.
		 */
		$response = apply_filters( 'ep_embeddings_request_response', $response, $text );

		if ( is_wp_error( $response ) ) {
			$this->update_failures_count();
			return $response;
		}

		if ( empty( $response['data'] ) ) {
			$this->update_failures_count();
			return new WP_Error( 'no_data', esc_html__( 'No data returned from the embedding service.', 'elasticpress' ) );
		}

		$return = [];

		// Parse out the embeddings response.
		foreach ( $response['data'] as $data ) {
			if ( ! isset( $data['embedding'] ) || ! is_array( $data['embedding'] ) ) {
				continue;
			}

			if ( is_string( $text ) ) {
				$return = $data['embedding'];
				break;
			}

			$return[] = $data['embedding'];
		}

		return $return;
	}

	/**
	 * Normalizes content into plain text.
	 *
	 * @param string $content Content to normalize.
	 * @return string
	 */
	public function normalize_content( string $content = '' ): string {
		$content = apply_filters( 'the_content', $content );

		// Strip shortcodes but keep internal caption text.
		// Revert it if shortcodes are not balanced and preg_replace errors out.
		$pre_content = $content;
		$content     = preg_replace( '#\[.+\](.+)\[/.+\]#', '$1', $content );
		if ( null === $content ) {
			$content = $pre_content;
		}

		// Strip HTML entities.
		$content = preg_replace( '/&#?[a-z0-9]{2,8};/i', '', $content );

		// Replace HTML linebreaks with newlines.
		$content = preg_replace( '#<br\s?/?>#', "\n\n", $content );

		// Strip all HTML tags.
		$content = wp_strip_all_tags( $content );

		return $content;
	}

	/**
	 * Chunk content into smaller pieces with an overlap.
	 *
	 * @param string $content      Content to chunk.
	 * @param int    $chunk_size   Size of each chunk, in words.
	 * @param int    $overlap_size Overlap size for each chunk, in words.
	 * @return array
	 */
	public function chunk_content( string $content = '', int $chunk_size = 150, $overlap_size = 25 ): array {
		// Normalize our content.
		$content = $this->normalize_content( $content );
		if ( ! $content ) {
			return [];
		}

		// Remove multiple whitespaces.
		$content = preg_replace( '/[ \t\r\f]+/', ' ', $content );

		// Remove multiple new lines.
		$content = preg_replace( '/[\n\v]{2,}/', "\n\n", $content );

		// For ElasticPress.io, we chunk the content on the service side.
		if ( 'epio' === $this->get_setting( 'ep_embeddings_generator' ) ) {
			/**
			 * Filter a chunk of text.
			 *
			 * @hook ep_embeddings_chunk
			 * @since 5.4.0
			 * @param {string} $chunk The chunk being processed.
			 * @return {string} The modified chunk.
			 */
			return (array) apply_filters( 'ep_embeddings_chunk', $content );
		}

		// Split text by single whitespace.
		$words = explode( ' ', $content );

		$chunks     = [];
		$text_count = count( $words );

		// Iterate through & chunk data with an overlap.
		for ( $i = 0; $i < $text_count; $i += $chunk_size ) {
			// Join a set of words into a string.
			$chunk = implode(
				' ',
				array_slice(
					$words,
					max( $i - $overlap_size, 0 ),
					$chunk_size
				)
			);

			// This filter is documented above.
			$chunk = apply_filters( 'ep_embeddings_chunk', $chunk );

			array_push( $chunks, $chunk );
		}

		return $chunks;
	}

	/**
	 * Get the number of dimensions for the embeddings.
	 *
	 * @return int
	 */
	public function get_dimensions(): int {
		$calc_dimensions = max( 1, min( 4096, $this->get_setting( 'ep_embeddings_dimensions' ) ) );

		/**
		 * Filter the dimensions we want for each embedding.
		 *
		 * Useful if you want to increase or decrease the length
		 * of each embedding.
		 *
		 * @hook ep_embeddings_dimensions
		 * @since 5.4.0
		 *
		 * @param {int} $dimensions The default dimensions.
		 * @return {int} The dimensions.
		 */
		return (int) apply_filters( 'ep_embeddings_dimensions', $calc_dimensions );
	}

	/**
	 * Return the array of indexables.
	 *
	 * @return array
	 */
	public function get_indexables() {
		return $this->indexables;
	}

	/**
	 * Check if the Beta version of the ElasticPress.io service is available.
	 *
	 * @return bool
	 */
	protected function is_epio_beta_available(): bool {
		/**
		 * Filter an initial check if the ElasticPress.io service is available.
		 *
		 * @hook ep_embeddings_is_epio_beta_available
		 * @since 5.4.0
		 *
		 * @param {bool} $initial_check The check.
		 * @return {bool} The check new value.
		 */
		return (bool) apply_filters( 'ep_embeddings_is_epio_beta_available', false );
	}

	/**
	 * Check if the ElasticPress.io service is available and the vector embeddings service is enabled.
	 *
	 * @return bool
	 * @throws \ElasticPress\Exception\NotFoundException If the ElasticPress.io service is not available.
	 */
	protected function is_epio_available(): bool {
		if ( ! $this->is_epio_beta_available() ) {
			return false;
		}

		if ( ! Utils\is_epio() ) {
			return false;
		}

		// Try to get the ElasticPressIo service.
		$error_code = 'old_ep_version';
		try {
			$epio = \ElasticPress\get_container()->get( '\ElasticPress\ElasticPressIo' );
			if ( ! method_exists( $epio, 'is_service_available' ) ) {
				throw new \ElasticPress\Exception\NotFoundException( $error_code );
			}
		} catch ( \ElasticPress\Exception\NotFoundException $th ) { // This exception we know how to handle.
			throw new \ElasticPress\Exception\NotFoundException( $error_code ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		return $epio->is_service_available( 'vectorEmbeddings' );
	}
}
