<?php
/**
 * Documents feature
 *
 * @package elasticpress
 */

namespace ElasticPress\Feature\Documents;

use ElasticPress\Feature as Feature;
use ElasticPress\Elasticsearch as Elasticsearch;
use ElasticPress\FeatureRequirementsStatus as FeatureRequirementsStatus;
use ElasticPress\Indexables as Indexables;

/**
 * Documents feature class.
 */
class Documents extends Feature {
	/**
	 * Initialize feature setting it's config
	 *
	 * @since  2.6
	 */
	public function __construct() {
		$this->slug = 'documents';

		$this->title = esc_html__( 'Documents', 'elasticpress' );

		$this->requires_install_reindex = false;

		parent::__construct();
	}

	/**
	 * Setup feature filters
	 *
	 * @since  2.3
	 */
	public function setup() {
		add_filter( 'ep_search_fields', [ $this, 'search_fields' ] );
		add_filter( 'ep_index_request_path', [ $this, 'index_request_path' ], 999, 3 );
		add_filter( 'ep_post_sync_args', [ $this, 'post_sync_args' ], 999, 2 );
		add_filter( 'ep_indexable_post_status', [ $this, 'indexable_post_status' ], 999, 1 );
		add_filter( 'ep_bulk_index_request_path', [ $this, 'bulk_index_request_path' ], 999, 3 );
		add_filter( 'pre_get_posts', [ $this, 'setup_document_search' ] );
		add_filter( 'ep_post_mapping', [ $this, 'attachments_mapping' ] );
		add_action( 'ep_cli_put_mapping', [ $this, 'create_pipeline' ] );
		add_action( 'ep_dashboard_put_mapping', [ $this, 'create_pipeline' ] );
		add_filter( 'ep_indexable_post_types', [ $this, 'index_attachment_post_type' ] );
		add_filter( 'ep_searchable_post_types', [ $this, 'search_attachment_post_type' ] );

		// Autosuggest Compatibility
		add_filter( 'ep_autosuggest_options', [ $this, 'filter_autosuggest_options' ] );
		add_filter( 'ep_term_suggest_post_status', [ $this, 'filter_autosuggest_post_status' ] );

		add_filter( 'ep_weighting_fields_for_post_type', [ $this, 'filter_weightable_fields_for_post_type' ], 10, 2 );
		add_filter( 'ep_weighting_default_post_type_weights', [ $this, 'filter_attachment_post_type_weights' ], 10, 2 );
	}

	/**
	 * Add attachment post type to be searched. We used to search these by default.
	 *
	 * @param array $post_types List of indexable post types
	 * @since  2.6
	 * @return array
	 */
	public function search_attachment_post_type( $post_types ) {
		$post_types['attachment'] = 'attachment';

		return $post_types;
	}

	/**
	 * Add attachment post type to be indexed. We used to index these by default.
	 *
	 * @param array $post_types List of indexable post types
	 * @since  2.6
	 * @return array
	 */
	public function index_attachment_post_type( $post_types ) {
		$post_types['attachment'] = 'attachment';

		return $post_types;
	}

	/**
	 * Add attachments mapping
	 *
	 * @param  array $mapping Mapping to add to.
	 * @since  2.3
	 * @return array
	 */
	public function attachments_mapping( $mapping ) {
		if ( version_compare( Elasticsearch::factory()->get_elasticsearch_version(), '7.0', '<' ) ) {
			$mapping['mappings']['post']['properties']['attachments'] = array(
				'type' => 'object',
			);
		} else {
			$mapping['mappings']['properties']['attachments'] = array(
				'type' => 'object',
			);
		}

		return $mapping;
	}

	/**
	 * This is some complex logic to handle the front end search query. If we have a search query,
	 * add the attachment post type to post_type and inherit to post_status. If post_status is not set,
	 * we assume publish/inherit is wanted. post_type should always be set. We also add allowed mime types.
	 * If mime types are already set, append.
	 *
	 * @param  WP_Query $query WP_Query to modify to search.
	 * @since  2.3
	 */
	public function setup_document_search( $query ) {
		if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		$s = $query->get( 's', false );

		if ( empty( $s ) ) {
			return;
		}

		$post_status = $query->get( 'post_status', [] );
		$post_type   = $query->get( 'post_type', [] );
		$mime_types  = $query->get( 'post_mime_type', [] );

		if ( ! empty( $post_type ) ) {
			if ( 'any' !== $post_type ) {
				if ( is_string( $post_type ) ) {
					$post_type   = explode( ' ', $post_type );
					$post_type[] = 'attachment';

					$query->set( 'post_type', array_unique( $post_type ) );
				}
			}
		}

		if ( empty( $post_status ) ) {
			$post_status = array_values(
				get_post_stati(
					[
						'public'              => true,
						'exclude_from_search' => false,
					]
				)
			);

			// Add inherit for documents
			$post_status[] = 'inherit';
		} else {
			if ( is_string( $post_status ) ) {
				$post_status = explode( ' ', $post_status );
			}

			$post_status[] = 'inherit';
		}

		$query->set( 'post_status', array_unique( $post_status ) );

		if ( ! empty( $mime_types ) && is_string( $mime_types ) ) {
			$mime_types = explode( ' ', $mime_types );
		}

		$mime_types   = array_merge( $mime_types, $this->get_allowed_ingest_mime_types() );
		$mime_types[] = ''; // This let's us query non-attachments as well as attachments.

		$query->set( 'post_mime_type', array_unique( array_values( $mime_types ) ) );
	}

	/**
	 * Change Elasticsearch request path if processing attachment
	 *
	 * @param string $path Path to request.
	 * @param array  $post Post array.
	 * @param  string $type Type of document
	 * @since  2.6
	 * @return string
	 */
	public function index_request_path( $path, $post, $type ) {
		if ( 'post' !== $type ) {
			return $path;
		}

		if ( 'attachment' === $post['post_type'] ) {
			if ( ! empty( $post['attachments'][0]['data'] ) && isset( $post['post_mime_type'] ) && in_array( $post['post_mime_type'], $this->get_allowed_ingest_mime_types(), true ) ) {
				$index = Indexables::factory()->get( 'post' )->get_index_name();
				/**
				 * Filter documents pipeline ID
				 *
				 * @hook ep_documents_pipeline_id
				 * @param  {string} $id Pipeline ID
				 * @return  {string} new ID
				 */
				$path = trailingslashit( $index ) . 'post/' . $post['ID'] . '?pipeline=' . apply_filters( 'ep_documents_pipeline_id', Indexables::factory()->get( 'post' )->get_index_name() . '-attachment' );
			}
		}

		return $path;
	}

	/**
	 * Add attachment data in post sync args
	 *
	 * @param array $post_args Post arguments to be synced.
	 * @param int   $post_id Post id.
	 * @since  2.3
	 * @return mixed
	 */
	public function post_sync_args( $post_args, $post_id ) {
		global $wp_filesystem;

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$post_args['attachments'] = [];

		if ( ! WP_Filesystem() ) {
			return $post_args;
		}

		$allowed_ingest_mime_types = $this->get_allowed_ingest_mime_types();

		if ( 'attachment' === get_post_type( $post_id ) && in_array( get_post_mime_type( $post_id ), $allowed_ingest_mime_types, true ) ) {
			$file_name = get_attached_file( $post_id );
			$exist     = $wp_filesystem->exists( $file_name, false, 'f' );
			if ( $exist ) {
				$file_content = $wp_filesystem->get_contents( $file_name );

				$post_args['attachments'][] = array(
					// phpcs:disable
					'data' => base64_encode( $file_content ),
					// phpcs:enable
				);
			}
		}

		return $post_args;
	}

	/**
	 * Add attachment field for search
	 *
	 * @param array $search_fields Search fields.
	 * @since  2.3
	 * @return array
	 */
	public function search_fields( $search_fields ) {
		if ( ! is_array( $search_fields ) ) {
			return $search_fields;
		}
		$search_fields[] = 'attachments.attachment.content';
		return $search_fields;
	}

	/**
	 * Add "inherit" post status for indexable post status
	 *
	 * @param array $statuses Array of post statuses.
	 * @since  2.3
	 * @return array
	 */
	public function indexable_post_status( $statuses ) {
		if ( ! array_search( 'inherit', $statuses, true ) ) {
			$statuses[] = 'inherit';
		}

		return $statuses;
	}

	/**
	 * Set attachment pipeline in Elaticsearch request path for bulk index
	 *
	 * @param  string $path Existing request path.
	 * @param  string $body JSON to index.
	 * @param  string $type Type of documents.
	 * @since  2.6
	 * @return string
	 */
	public function bulk_index_request_path( $path, $body, $type ) {
		if ( 'post' !== $type ) {
			return $path;
		}

		return add_query_arg(
			array(
				/**
				 * Filter documents pipeline ID
				 *
				 * @hook ep_documents_pipeline_id
				 * @param  {string} $id Pipeline ID
				 * @return  {string} new ID
				 */
				'pipeline' => apply_filters( 'ep_documents_pipeline_id', Indexables::factory()->get( 'post' )->get_index_name() . '-attachment' ),
			),
			$path
		);
	}

	/**
	 * Determine Documents feature requirement status
	 *
	 * @since  2.3
	 * @return mixed
	 */
	public function requirements_status() {
		$status = new FeatureRequirementsStatus( 1 );

		$plugins = Elasticsearch::factory()->get_elasticsearch_plugins();

		$status->message = [];

		// Ingest attachment plugin is required for this feature.
		if ( empty( $plugins ) || empty( $plugins['ingest-attachment'] ) ) {
			$status->code      = 2;
			$status->message[] = __( 'The <a href="https://www.elastic.co/guide/en/elasticsearch/plugins/master/ingest-attachment.html">Ingest Attachment plugin</a> for Elasticsearch is not installed. To get the most out of ElasticPress, without the hassle of Elasticsearch management, check out <a href="https://elasticpress.io">ElasticPress.io</a> hosting.', 'elasticpress' );
		} else {
			$status->code      = 1;
			$status->message[] = __( 'This feature modifies the default user experience for your visitors by adding popular document file types to search results. <strong>All supported documents</strong> uploaded to your media library will appear in search results.', 'elasticpress' );
		}

		return $status;
	}

	/**
	 * Output feature box summary
	 *
	 * @since  2.3
	 */
	public function output_feature_box_summary() {
		?>
		<p><?php esc_html_e( 'Indexes text inside of popular file types, and adds those files types to search results.', 'elasticpress' ); ?></p>
		<?php
	}

	/**
	 * Output feature box long
	 *
	 * @since  2.3
	 */
	public function output_feature_box_long() {
		?>
		<p><?php esc_html_e( 'Website search results will include popular document file types, using file names as well as their content. Supported file types include: ppt, pptx, doc, docx, xls, xlsx, pdf.', 'elasticpress' ); ?></p>
		<?php
	}

	/**
	 * Make sure to create pipeline after activation
	 *
	 * @since  2.6
	 */
	public function post_activation() {
		$this->create_pipeline();
	}

	/**
	 * Put attachment pipeline
	 *
	 * @since  2.3
	 */
	public function create_pipeline() {
		$args = array(
			'description' => 'Extract attachment information',
			'processors'  => array(
				array(
					'foreach' => array(
						'field'     => 'attachments',
						'processor' => array(
							'attachment' => array(
								'target_field'   => '_ingest._value.attachment',
								'field'          => '_ingest._value.data',
								'ignore_missing' => true,
								'indexed_chars'  => -1,
							),
						),
					),
				),
				array(
					'foreach' => array(
						'field'     => 'attachments',
						'processor' => array(
							'remove' => array(
								'field' => '_ingest._value.data',
							),
						),
					),
				),
			),
		);

		/**
		 * Filter documents pipeline ID
		 *
		 * @hook ep_documents_pipeline_id
		 * @param  {string} $id Pipeline ID
		 * @return  {string} new ID
		 */
		Elasticsearch::factory()->create_pipeline( apply_filters( 'ep_documents_pipeline_id', Indexables::factory()->get( 'post' )->get_index_name() . '-attachment' ), $args );
	}

	/**
	 * Get allowed mime types for feature
	 *
	 * @since  2.3
	 * @return array
	 */
	public function get_allowed_ingest_mime_types() {
		/**
		 * Filter allowed mime types for documents
		 *
		 * @hook ep_allowed_documents_ingest_mime_types
		 * @param  {array} $mime_types Allowed mime types
		 * @return  {array} New types
		 */
		return apply_filters(
			'ep_allowed_documents_ingest_mime_types',
			array(
				'pdf'  => 'application/pdf',
				'ppt'  => 'application/vnd.ms-powerpoint',
				'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
				'xls'  => 'application/vnd.ms-excel',
				'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
				'doc'  => 'application/msword',
				'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			)
		);
	}

	/**
	 * Filters autosuggest options to add the mime type filters
	 *
	 * @param array $options Current autosuggest options
	 *
	 * @return array
	 */
	public function filter_autosuggest_options( $options ) {
		$mime_types = isset( $options['mimeTypes'] ) && is_array( $options['mimeTypes'] ) ? $options['mimeTypes'] : array();

		$mime_types = array_merge( $mime_types, $this->get_allowed_ingest_mime_types(), [ '' ] ); // Empty type matches any other post type without mime type set

		$options['mimeTypes'] = $mime_types;

		return $options;
	}

	/**
	 * Adds the "inherit" post status to allowed post statuses for autosuggest searches
	 *
	 * @param array $post_statuses Current post statuses
	 *
	 * @return array
	 */
	public function filter_autosuggest_post_status( $post_statuses ) {
		$post_statuses[] = 'inherit';

		return $post_statuses;
	}

	/**
	 * Filters the weightable fields for attachments.
	 *
	 * Adds the document content field and changes the post_content and post_excerpt labels to "Description" and "Caption"
	 *
	 * @param array  $fields    Current weightable fields
	 * @param string $post_type The post type the weightable fields apply to
	 *
	 * @return array Final weightable fields for post type
	 */
	public function filter_weightable_fields_for_post_type( $fields, $post_type ) {
		if ( 'attachment' === $post_type ) {
			// Updates labels for description and caption
			// @todo this might need to move to Protected Content if attachments are enabled there
			$fields['attributes']['children']['post_content']['label'] = __( 'Description', 'elasticpress' );
			$fields['attributes']['children']['post_excerpt']['label'] = __( 'Caption', 'elasticpress' );

			// Adds new field
			$fields['attributes']['children']['attachments.attachment.content'] = [
				'key'   => 'attachments.attachment.content',
				'label' => __( 'Document Content', 'elasticpress' ),
			];
		}

		return $fields;
	}

	/**
	 * Filters the default weight values to add attachment-specific weights
	 *
	 * @param array  $weights   Current weight settings
	 * @param string $post_type The post type the weights apply to
	 *
	 * @return array Final weights
	 */
	public function filter_attachment_post_type_weights( $weights, $post_type ) {
		if ( 'attachment' === $post_type ) {
			$weights['attachments.attachment.content'] = [
				'enabled' => true,
				'weight'  => 0,
			];
		}

		return $weights;
	}
}


