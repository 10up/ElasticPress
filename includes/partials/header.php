<?php
/**
 * Header template for ElasticPress settings page
 *
 * @since  2.1
 * @package elasticpress
 */

use ElasticPress\Elasticsearch;
use ElasticPress\Screen;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$base_url = admin_url( 'admin.php?page=' ); // VIP: The network menu is disabled, go to the site menu.
$is_sync_page = 'sync' === Screen::factory()->get_current_screen();
?>

<div class="ep-header-menu">
	<a href="<?php echo esc_url( $base_url . 'elasticpress' ); ?>"><img width="250" src="<?php echo esc_url( plugins_url( '/images/vip-logo.svg', dirname( __DIR__ ) ) ); ?>"></a> <!-- // VIP: Update header image -->

	<div class="icons">
		<span class="sync-status"></span>
		<?php if ( $is_sync_page ) : ?>

			<a class="dashicons resume-sync dashicons-controls-play" title ="<?php esc_attr_e( 'Resume Sync', 'elasticpress' ); ?>" aria-label="<?php esc_attr_e( 'Resume Sync', 'elasticpress' ); ?>"></a>
			<a class="dashicons cancel-sync dashicons-no" title="<?php esc_attr_e( 'Cancel Sync', 'elasticpress' ); ?>" aria-label="<?php esc_attr_e( 'Cancel Sync', 'elasticpress' ); ?>"></a>
		<?php endif; ?>
		<?php if ( Elasticsearch::factory()->get_elasticsearch_version() && defined( 'EP_DASHBOARD_SYNC' ) && EP_DASHBOARD_SYNC && ! $is_sync_page ) : ?>
			<a
				class="dashicons start-sync dashicons-update"
				title="<?php esc_attr_e( 'Sync Page', 'elasticpress' ); ?>"
				aria-label="<?php esc_attr_e( 'Sync Page', 'elasticpress' ); ?>"
				<?php echo ( $is_sync_page ) ? '' : 'href="' . esc_url( $base_url . 'elasticpress-sync' ) . '"'; ?>
			></a>
		<?php endif; ?>
		<!-- // VIP: Remove Settings button. -->
	</div>

	<div class="progress-bar"></div>
</div>
