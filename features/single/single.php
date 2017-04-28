<?php
/**
 * ElasticPress Single Post
 *
 * @package elasticpress
 */

/**
 * Setup all feature filters
 */
function ep_single_setup() {
    if ( !is_admin() ) {
        add_filter( 'pre_get_posts', 'get_post_from_elastic');
    }
}

/**
 * Activate ElasticPress on single post and single page
 * @param $query
 */
function get_post_from_elastic( $query ) {
    if( $query->is_main_query() && $query->is_singular() && !$query->get( 'post_type' ) && !$query->is_attachment() ) {
        $query->set('ep_integrate', true);
    }
}

/**
 * Output feature box summary
 */
function ep_single_feature_box_summary() {
    ?>
    <p><?php esc_html_e( 'Single and page query through Elasticsearch', 'elasticpress' ); ?></p>
    <?php
}

/**
 * Register the feature
 */
ep_register_feature( 'single', array(
    'title' => esc_html__( 'Single Posts', 'elasticpress' ),
    'setup_cb' => 'ep_single_setup',
    'feature_box_summary_cb' => 'ep_single_feature_box_summary',
    'requires_install_reindex' => false
) );