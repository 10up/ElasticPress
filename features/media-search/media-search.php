<?php
/**
 * ElasticPress Media Search
 *
 * @package elasticpress
 */


/**
 * Setup all feature filters
 *
 * @since  2.1
 */
function ep_ms_setup() {
    add_filter( 'ep_indexable_post_status', 'ep_ms_get_statuses' );

    if ( is_admin() ) {
        add_filter( 'ep_admin_wp_query_integration', '__return_true' );
        add_filter( 'ajax_query_attachments_args', 'ep_media_search_integrate', 10, 1 );
    }
}

/**
* Activate ElasticPress on ajax media search, and focus only on post_title for search
*/
function ep_media_search_integrate($query = array()) {
    $query['ep_integrate'] = 1;
    $query['search_fields'] = ['post_title'];
    return $query;
}

/**
 * Output feature box summary
 *
 * @since 2.1
 */
function ep_ms_feature_box_summary() {
    ?>
    <p><?php esc_html_e( 'Media search boost with Elasticsearch', 'elasticpress' ); ?></p>
    <?php
}

/**
 * Fetches all post with inherit status (attachment)
 *
 * @param  array $statuses
 * @return array
 */
function ep_ms_get_statuses( $statuses ) {
    $statuses[] = 'inherit';

    return array_unique($statuses);
}

/**
 * Register the feature
 */
ep_register_feature( 'media_search', array(
    'title' => esc_html__( 'Media Search', 'elasticpress' ),
    'setup_cb' => 'ep_ms_setup',
    'feature_box_summary_cb' => 'ep_ms_feature_box_summary',
    'requires_install_reindex' => true,
) );
