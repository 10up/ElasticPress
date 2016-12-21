<?php
/**
 * Header template for ElasticPress settings page
 *
 * @since  2.1
 * @package elasticpress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$base_url =  ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) ? admin_url( 'network/admin.php?page=' ) : admin_url( 'admin.php?page=' );
?>

<div class="ep-header-menu">
	<a href="<?php echo esc_url( $base_url . 'elasticpress' ); ?>"><img width="150" src="<?php echo esc_url( plugins_url( '/images/logo.svg', dirname( __FILE__ ) ) ); ?>"></a>

	<div class="icons">
		<span class="sync-status"></span>
		<?php if ( ! empty( $_GET['page'] ) && ( 'elasticpress' === $_GET['page'] || 'elasticpress-settings' === $_GET['page'] ) ) : ?>
			<a class="dashicons pause-sync dashicons-controls-pause"></a>
			<a class="dashicons resume-sync dashicons-controls-play"></a>
			<a class="dashicons cancel-sync dashicons-no"></a>
			<?php if ( ep_get_elasticsearch_version() ) : ?>
				<a class="dashicons start-sync dashicons-update"></a>
			<?php endif; ?>
		<?php endif; ?>
		<a href="<?php echo esc_url( $base_url . 'elasticpress-settings' ); ?>" class="dashicons dashicons-admin-generic"></a>
	</div>

	<div class="progress-bar"></div>
</div>
