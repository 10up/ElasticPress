<?php
/**
 * Setup feature filters
 *
 * @since  2.3
 */
function ep_documents_setup() {
	add_filter( 'ep_search_fields', 'ep_filter_ep_search_fields' );
	add_filter( 'ep_index_post_request_path', 'ep_documents_index_post_request_path', 999, 2 );
	add_filter( 'ep_post_sync_args', 'ep_documents_post_sync_args', 999, 2 );
	add_filter( 'ep_indexable_post_status', 'ep_documents_indexable_post_status', 999, 1 );
	add_filter( 'ep_bulk_index_post_request_path', 'ep_documents_bulk_index_post_request_path', 999, 1 );
	add_filter( 'pre_get_posts', 'search_attachment_post_type' );
	add_filter( 'ep_config_mapping', 'attachments_mapping' );
	add_action( 'ep_cli_put_mapping', 'ep_documents_create_pipeline' );
	add_action( 'ep_dashboard_put_mapping', 'ep_documents_create_pipeline' );
}

/**
 * Add attachments mapping
 * 
 * @param  array $mapping
 * @since  2.3
 * @return array
 */
function attachments_mapping( $mapping ) {
	$mapping['mappings']['post']['properties']['attachments'] = array(
		'type' => 'object',
	);

	return $mapping;
}

/**
 * This is some complex logic to handle the front end search query. If we have a search query,
 * add the attachment post type to post_type and inherit to post_status. If post_status is not set,
 * we assume publish/inherit is wanted. post_type should always be set. We also add allowed mime types.
 * If mime types are already set, append.
 * 
 * @param  WP_Query $query
 * @since  2.3
 */
function search_attachment_post_type( $query ) {
	if ( is_admin() ) {
		return;
	}

	$s = $query->get( 's', false );

	if ( empty( $s ) ) {
		return;
	}

	$post_status = $query->get( 'post_status' , array() );
	$post_type = $query->get( 'post_type' , array() );
	$mime_types = $query->get( 'post_mime_type' , array() );

	if ( ! empty( $post_type ) ) {
		if ( 'any' !== $post_type ) {
			if ( is_string( $post_type ) ) {
				$post_type = explode( ' ', $post_type );
				$post_type[] = 'attachment';

				$query->set( 'post_type', array_unique( $post_type ) );
			}
		}
	}

	if ( empty( $post_status ) ) {
		$post_status = array( 'inherit', 'publish' );
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

	$mime_types = array_merge( $mime_types, ep_documents_get_allowed_ingest_mime_types() );
	$mime_types[] = ''; // This let's us query non-attachments as well as attachments

	$query->set( 'post_mime_type', array_unique( array_values( $mime_types ) ) );
}

/**
 * Change Elasticsearch request path if processing attachment
 *
 * @param string $path
 * @param array $post
 * @since  2.3
 * @return string
 */
function ep_documents_index_post_request_path( $path, $post ) {
	if ( 'attachment' === $post['post_type'] ) {
		if ( ! empty( $post['attachments'][0]['data'] ) && isset( $post['post_mime_type'] ) && in_array( $post['post_mime_type'], ep_documents_get_allowed_ingest_mime_types() ) ) {
			$index = ep_get_index_name();
			$path = trailingslashit( $index ) . 'post/' . $post['ID'] . '?pipeline=' . apply_filters( 'ep_documents_pipeline_id', ep_get_index_name() . '-attachment' );
		}
	}
	
	return $path;
}

/**
 * Add attachment data in post sync args
 *
 * @param array $post_args
 * @param int $post_id
 * @since  2.3
 * @return mixed
 */
function ep_documents_post_sync_args( $post_args, $post_id ) {
	global $wp_filesystem;

	require_once( ABSPATH . 'wp-admin/includes/file.php' );

	$post_args['attachments'] = array();

	if ( ! WP_Filesystem() ) {
		return $post_args;
	}

	$allowed_ingest_mime_types = ep_documents_get_allowed_ingest_mime_types();
	
	if ( 'attachment' == get_post_type( $post_id ) && in_array( get_post_mime_type( $post_id ), $allowed_ingest_mime_types ) ) {
		$file_name = get_attached_file( $post_id );
		$exist = $wp_filesystem->exists( $file_name, false, 'f' );
		if ( $exist ) {
			$file_content = $wp_filesystem->get_contents( $file_name );
			
			$post_args['attachments'][] = array(
				'data' => base64_encode( $file_content ),
			);
		}
	}
	
	return $post_args;
}

/**
 * Add attachment field for search
 *
 * @param $search_fields
 * @since  2.3
 * @return array
 */
function ep_filter_ep_search_fields( $search_fields ) {
	if ( ! is_array( $search_fields ) ) {
		return $search_fields;
	}
	
	$search_fields[] = 'attachments.attachment.content';
	
	return $search_fields;
}

/**
 * Add "inherit" post status for indexable post status
 *
 * @param $statuses
 * @since  2.3
 * @return array
 */
function ep_documents_indexable_post_status( $statuses ) {
	if ( ! array_search( 'inherit', $statuses ) ) {
		$statuses[] = 'inherit';
	}
	
	return $statuses;
}

/**
 * Set attachment pipeline in Elaticsearch request path for bulk index
 *
 * @param $path
 * @since  2.3
 * @return string
 */
function ep_documents_bulk_index_post_request_path( $path ) {
	return add_query_arg( array(
		'pipeline' => apply_filters( 'ep_documents_pipeline_id', ep_get_index_name() . '-attachment' ),
	), $path );
}

/**
 * Determine Documents feature requirement status
 *
 * @param $status
 * @since  2.3
 * @return mixed
 */
function ep_documents_requirements_status( $status ) {
	$plugins = ep_get_elasticsearch_plugins();

	$status->code = 1;
	$status->message = [];
	
	// Ingest attachment plugin is required for this feature
	if ( empty( $plugins ) || empty( $plugins['ingest-attachment'] ) ) {
		$status->code = 2;
		$status->message[] = __( 'The <a href="https://www.elastic.co/guide/en/elasticsearch/plugins/master/ingest-attachment.html">Ingest Attachment plugin</a> for Elasticsearch is not installed. To get the most out of ElasticPress, without the hassle of Elasticsearch management, check out <a href="https://elasticpress.io">ElasticPress.io</a> hosting.', 'elasticpress' );
	} else {
		$status->message[] = __( "This feature modifies the default user experience for your visitors by adding popular document file types to search results. <strong>All supported documents</strong> uploaded to your media library will appear in search results.", 'elasticpress' );
	}
	
	return $status;
}

/**
 * Output feature box summary
 *
 * @since  2.3
 */
function ep_documents_feature_box_summary() {
	?>
	<p><?php esc_html_e( 'Indexes text inside of popular file types, and adds those files types to search results.', 'elasticpress' ) ?></p>
	<?php
}

/**
 * Output feature box long
 * 
 * @since  2.3
 */
function ep_documents_feature_box_long() {
	?>
	<p><?php esc_html_e( 'Website search results will include popular document file types, using file names as well as their content. Supported file types include: ppt, pptx, doc, docx, xls, xlsx, pdf.', 'elasticpress' ) ?></p>
	<?php
}

/**
 * Put attachment pipeline
 *
 * @since  2.3
 */
function ep_documents_create_pipeline() {
	$args = array(
		'description' => 'Extract attachment information',
		'processors' => array(
			array(
				'foreach' => array(
					'field' => 'attachments',
					'processor' => array(
						'attachment' => array(
							'target_field' => '_ingest._value.attachment',
							'field' => '_ingest._value.data',
							'ignore_missing' => true,
							'indexed_chars' => -1,
						)
					)
				)
			),
		),
	);
	
	ep_create_pipeline( apply_filters( 'ep_documents_pipeline_id', ep_get_index_name() . '-attachment' ), $args );
}

/**
 * Get allowed mime types for feature
 *
 * @since  2.3
 * @return array
 */
function ep_documents_get_allowed_ingest_mime_types() {
	return apply_filters( 'ep_allowed_documents_ingest_mime_types', array(
		'pdf'  => 'application/pdf',
		'ppt'  => 'application/vnd.ms-powerpoint',
		'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
		'xls'  => 'application/vnd.ms-excel',
		'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		'doc'  => 'application/msword',
		'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
	) );
}

/**
 * Register the feature
 *
 * @since  2.3
 */
ep_register_feature( 'documents', array(
	'title' => 'Documents',
	'requirements_status_cb' => 'ep_documents_requirements_status',
	'setup_cb' => 'ep_documents_setup',
	'post_activation_cb' => 'ep_documents_create_pipeline',
	'feature_box_summary_cb' => 'ep_documents_feature_box_summary',
	'feature_box_long_cb' => 'ep_documents_feature_box_long',
	'requires_install_reindex' => true,
) );


