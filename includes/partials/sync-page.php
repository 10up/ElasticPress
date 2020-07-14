<?php
require_once __DIR__ . '/header.php';
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Sync', 'elasticpress' ); ?></h1>

	<div class="card">
		<h2><?php esc_html_e( 'Sync New Data', 'elasticpress' ); ?></h2>

		<p><?php esc_html_e( 'If you are missing data in your search results or have recently added custom content types to your site, you should run a sync to reflect these changes. You may also be prompted to run a sync after updates to the ElasticPress plugin.', 'elasticpress' ); ?></p>
		<p><?php esc_html_e( 'Running this will sync any missing data to your Elasticsearch index. Your search results will be unaffected in the meantime.', 'elasticpress' ); ?></p>

		<p class="submit"><a href="<?php echo esc_url( admin_url( 'admin.php?page=elasticpress&do_sync' ) ); ?>" class="button button-primary button-large"><?php esc_html_e( 'Let&rsquo;s go!', 'elasticpress' ); ?></a></p>
	</div>

	<div class="card">
		<h2><?php esc_html_e( 'Delete All Data and Sync', 'elasticpress' ); ?></h2>

		<p><?php esc_html_e( 'If you are still having issues with your search results, you may need to do a completely fresh sync. This may take a few hours depending on the amount of content that needs to be synced and indexed. While this is happening, searches will use the default WordPress results.', 'elasticpress' ); ?></p>

		<?php submit_button( __('I understand, I want to do a fresh sync', 'elasticpress' ), 'secondary' ); ?>
	</div>
</div>