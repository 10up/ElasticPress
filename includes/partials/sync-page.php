<?php
/**
 * Template for ElasticPress sync page
 *
 * @since  3.6.0
 * @package elasticpress
 */

use ElasticPress\Elasticsearch as Elasticsearch;

require_once __DIR__ . '/header.php';
?>

<div class="error-overlay <?php if ( ! empty( $index_meta ) ) : ?>syncing<?php endif; ?> <?php if ( ! Elasticsearch::factory()->get_elasticsearch_version() ) : ?>cant-connect<?php endif; ?>"></div>
<div class="wrap">
	<h1><?php esc_html_e( 'Sync', 'elasticpress' ); ?></h1>

	<textarea id="ep-sync-output" cols="30" rows="10" class="widefat" readonly></textarea>

	<div class="card">
		<h2><?php esc_html_e( 'Sync New Data', 'elasticpress' ); ?></h2>

		<p><?php esc_html_e( 'If you are missing data in your search results or have recently added custom content types to your site, you should run a sync to reflect these changes. You may also be prompted to run a sync after updates to the ElasticPress plugin.', 'elasticpress' ); ?></p>
		<p><?php esc_html_e( 'Running this will sync any missing data to your Elasticsearch index. Your search results will be unaffected in the meantime.', 'elasticpress' ); ?></p>

		<p class="submit"><button class="button button-primary button-large start-sync"><?php esc_html_e( 'Let&rsquo;s go!', 'elasticpress' ); ?></button></p>
	</div>

	<div class="card">
		<h2><?php esc_html_e( 'Delete All Data and Sync', 'elasticpress' ); ?></h2>

		<p><?php esc_html_e( 'If you are still having issues with your search results, you may need to do a completely fresh sync. This may take a few hours depending on the amount of content that needs to be synced and indexed. While this is happening, searches will use the default WordPress results.', 'elasticpress' ); ?></p>

		<?php submit_button( esc_html__( 'I understand, I want to do a fresh sync', 'elasticpress' ), 'secondary start-sync start-sync-put-mapping' ); ?>
	</div>
</div>
