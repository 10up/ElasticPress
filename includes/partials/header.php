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

$base_url = ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) ? admin_url( 'network/admin.php?page=' ) : admin_url( 'admin.php?page=' );
?>

<div class="ep-header-menu">
	<a href="<?php echo esc_url( $base_url . 'elasticpress' ); ?>"><img width="150" src="<?php echo esc_url( plugins_url( '/images/logo.svg', dirname( __DIR__ ) ) ); ?>"></a>

	<div class="icons">
		<span class="sync-status"></span>
		<?php if ( in_array( Screen::factory()->get_current_screen(), [ 'dashboard', 'settings', 'health' ], true ) ) : ?>
			<a class="dashicons pause-sync dashicons-controls-pause"></a>
			<a class="dashicons resume-sync dashicons-controls-play"></a>
			<a class="dashicons cancel-sync dashicons-no"></a>
			<?php if ( Elasticsearch::factory()->get_elasticsearch_version() && defined( 'EP_DASHBOARD_SYNC' ) && EP_DASHBOARD_SYNC ) : ?>
				<a class="dashicons start-sync dashicons-update"></a>
			<?php endif; ?>
		<?php endif; ?>
		<a href="<?php echo esc_url( $base_url . 'elasticpress-settings' ); ?>" class="dashicons dashicons-admin-generic"></a>
	</div>

	<div class="progress-bar"></div>
</div>
