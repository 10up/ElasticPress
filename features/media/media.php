<?php

/**
 * ElasticPress media indexing feature
 *
 * @since 2.3
 * @package elasticpress
 */

/**
 * Setup feature filters
 */
function ep_media_setup() {
	add_filter( 'ep_search_fields', 'ep_filter_ep_search_fields' );
	add_filter( 'ep_index_post_request_path', 'ep_media_index_post_request_path', 999, 2 );
	add_filter( 'ep_post_sync_args', 'ep_media_post_sync_args', 999, 2 );
	add_filter( 'ep_admin_supported_post_types', 'ep_media_admin_supported_post_types', 999 , 1 );
	add_filter( 'ep_indexable_post_status', 'ep_media_indexable_post_status', 999, 1 );
	add_filter( 'ep_formatted_args_query', 'ep_media_formatted_args_query', 999, 2 );
}

/**
 * Change Elasticsearch request path if processing attachment
 *
 * @param $path
 * @param $post
 *
 * @return string
 */
function ep_media_index_post_request_path( $path, $post ) {
	// Allowed mimes only
	if( isset( $post['post_mime_type'] ) && in_array( $post['post_mime_type'], ep_media_get_allowed_mime_types() ) ) {
		$index = ep_get_index_name();
		$path = trailingslashit( $index ) . 'post/' . $post['ID'] . '?pipeline=attachment';
	}
	
	return $path;
}

/**
 * Add attachment data in post sync args
 *
 * @param $post_args
 * @param $post_id
 *
 * @return mixed
 */
function ep_media_post_sync_args( $post_args, $post_id ) {
	global $wp_filesystem;
	
	// Add "data" field if it's supported mime types attachment and have direct filesystem access to read file data
	// Following block is basically for attachments
	if( 'attachment' == get_post_type( $post_id ) && in_array( get_post_mime_type( $post_id ), ep_media_get_allowed_mime_types() ) && WP_Filesystem() ) {
		$file_name = get_attached_file( $post_id );
		$exist = $wp_filesystem->exists( $file_name, false, 'f' );
		if( $exist ) {
			$file_content = $wp_filesystem->get_contents( $file_name );
			
			$post_args['data'] = base64_encode( $file_content );
		}
	}
	
	// Fetch child posts to put nested attachments in post object.
	// Doing so will allow us to search into post's attachments using Elasticsearch nested objects
	// This is basically for posts who has attachments attached.
	$child_args = array(
		'ep_integrate' => true,
		'post_parent' => intval( $post_id ),
		'post_type'   => 'attachment',
		'post_mime_type' => array_values( ep_media_get_allowed_mime_types() ),
		'post_status' => 'inherit',
	);
	
	// Filter to add attachment data in post object when search data is returned
	add_filter( 'ep_search_post_return_args', 'ep_media_search_post_args_add_attachments', 10, 1 );
	
	$child_attachment_query = new WP_Query( $child_args );
	
	remove_filter( 'ep_search_post_return_args', 'ep_media_search_post_args_add_attachments', 10 );
	
	$child_attachments = $child_attachment_query->posts;
	
	// Put attachment data into post
	if( ! empty( $child_attachment_query->found_posts ) ) {
		$post_args['attachments'] = array();
		foreach( $child_attachments as $single_child ) {
			if( ! empty( $single_child->attachment['content'] ) ) {
				$post_args['attachments'][] = array(
					'ID' => $single_child->ID,
					'content' => $single_child->attachment['content'],
				);
			}
		}
	}
	
	return $post_args;
}

/**
 * Add post's attachment data in search return post
 *
 * By default returned post will have post data which can filtered using ep_search_post_return_args filter.
 *
 * @param $search_return_args
 *
 * @return array
 */
function ep_media_search_post_args_add_attachments( $search_return_args ) {
	$search_return_args[] = 'attachment';
	
	return $search_return_args;
}

/**
 * Add attachment field for search
 *
 * @param $search_fields
 *
 * @return array
 */
function ep_filter_ep_search_fields( $search_fields ) {
	if ( ! is_array( $search_fields ) ) {
		return $search_fields;
	}
	
	$search_fields[] = 'attachment.content';
	
	return $search_fields;
}

/**
 * Add attachment as supported post type for admin feature
 *
 * @param $post_types
 *
 * @return array
 */
function ep_media_admin_supported_post_types( $post_types ) {
	if( is_array( $post_types ) ){
		$post_types['attachment'] = 'inherit';
	}
	
	return $post_types;
}

/**
 * Add "inherit" post status for indexable post status
 *
 * @param $statuses
 *
 * @return array
 */
function ep_media_indexable_post_status( $statuses ) {
	if( ! array_search( 'inherit', $statuses ) ) {
		$statuses[] = 'inherit';
	}
	
	return $statuses;
}

/**
 * Add nested query to search in attachment content in Elasticsearch formatted args query
 *
 * @param $formatted_args
 * @param $args
 *
 * @return mixed
 */
function ep_media_formatted_args_query( $formatted_args, $args ) {
	
	if( ! empty( $formatted_args['bool']['should'] ) && ! empty( $args['s'] ) ) {
		$formatted_args['bool']['should'][] = array(
			'nested' => array(
				'path' => 'attachments',
				'query' => array(
					'bool' => array(
						'must' => [
							array(
								'match' => array(
									'attachments.content' => $args['s'],
								),
							),
						],
					),
				),
			),
		);
	}
	
	return $formatted_args;
}

/**
 * Determine Media feature requirement status
 *
 * @param $status
 *
 * @return mixed
 */
function ep_media_requirements_status( $status ) {
	$plugins = ep_get_plugins();
	
	// Ingest attachment plugin should be exist and Elaticsearch version should be 5.x
	if ( ( ! array_key_exists( 'ingest-attachment', $plugins ) ) && version_compare( ep_get_elasticsearch_version(),'5.0', '>=' ) ) {
		$status->code = 2;
		$status->message = esc_html__( 'Elasticsearch Ingest Attachment plugin is not installed.', 'elasticpress' );
	}
	
	return $status;
}

/**
 * Output feature box summary
 */
function ep_media_feature_box_summary() {
	?>
	<p><?php esc_html_e( 'Index pdf, xdoc, xls, and ppt files which can influence search relevancy of the post.', 'elasticpress' ) ?></p>
	<?php
}

/**
 * Output feature box long
 */
function ep_media_feature_box_long() {
	?>
	<p><?php esc_html_e( 'When searching for content in WordPress, posts that have associated media files will be searched and used to influence the search relevancy of that piece of content.', 'elasticpress' ) ?></p>
	<p><?php esc_html_e( 'Also, if media items, are being searched the ElasticPress will be able to factor in the relevancy of the actual media content.', 'elasticpress' ) ?></p>
	<?php
}

/**
 * Put attachment pipeline once feature is activate
 *
 * @param $feature_obj
 */
function ep_media_post_activation( $feature_obj ) {
	//put attachment pipeline once
	$args = array(
		'description' => 'Extract attachment information',
		'processors' => [
			array(
				'attachment' => array(
					'field' => 'data',
					'indexed_chars' => -1,
				),
			),
		],
	);
	
	$path = '_ingest/pipeline/attachment';
	
	$request_args = array(
		'body'    => json_encode( $args ),
		'method'  => 'PUT',
	);
	
	$request = ep_remote_request( $path, apply_filters( 'ep_put_attachment_pipeline_args', $request_args ) );
}

/**
 * Get allowed mime types for feature
 *
 * @return mixed|void
 */
function ep_media_get_allowed_mime_types() {
	return apply_filters( 'ep_allowed_media_mime_types', array(
		'pdf' => 'application/pdf',
		'ppt' => 'application/vnd.ms-powerpoint',
		'xls' => 'application/vnd.ms-excel',
		'doc' => 'application/msword',
	) );
}

/**
 * Register the feature
 */
ep_register_feature( 'media', array(
	'title' => 'Media',
	'requirements_status_cb' => 'ep_media_requirements_status',
	'setup_cb' => 'ep_media_setup',
	'post_activation_cb' => 'ep_media_post_activation',
	'feature_box_summary_cb' => 'ep_media_feature_box_summary',
	'feature_box_long_cb' => 'ep_media_feature_box_long',
	'requires_install_reindex' => true,
) );