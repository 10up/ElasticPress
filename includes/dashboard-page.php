<?php
/**
 * Template for ElasticPress dashboard page
 *
 * @since  2.1
 * @package elasticpress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
	$index_meta = get_site_option( 'ep_index_meta', false );
} else {
	$index_meta = get_option( 'ep_index_meta', false );
}
?>

<?php require_once( dirname( __FILE__ ) . '/header.php' ); ?>

<div class="error-overlay <?php if ( ! empty( $index_meta ) ) : ?>syncing<?php endif; ?> <?php if ( ! ep_elasticsearch_can_connect() ) : ?>cant-connect<?php endif; ?>"></div>
<div class="wrap">
	<h2 class="ep-list-modules"><?php esc_html_e( 'List of modules', 'elasticpress' ); // We use this since WP inserts warnings after the first h2. This will be hidden. ?></h2>
	<div class="ep-modules metabox-holder">
		<?php $modules = EP_Modules::factory()->registered_modules; ?>

		<?php 
		$left = '';
		$right = '';
		$i = 0;
		foreach ( $modules as $module ) :
			$i++;
			$requirements_status = $module->requirements_status();
			$active = $module->is_active();

			$module_classes = 'module-requirements-status-' . (int) $requirements_status->code;

			if ( ! empty( $active ) ) {
				$module_classes .= ' module-active';
			}

			if ( ! empty( $index_meta ) && ! empty( $index_meta['module_sync'] ) && $module->slug === $index_meta['module_sync'] ) {
				$module_classes .= ' module-syncing';
			}

			ob_start();
			?>
			<div class="ep-module ep-module-<?php echo esc_attr( $module->slug ); ?> <?php echo esc_attr( $module_classes ); ?>">
				<div class="postbox">
					<h2 class="hndle">
						<span><?php echo esc_html( $module->title ); ?></span>
						<a class="settings-button"><?php esc_html_e( 'settings', 'elasticpress' ); ?></a>
					</h2>

					<div class="description inside">

						<?php $module->output_module_box(); ?>

					</div>

					<div class="settings inside">
						<?php $module->output_settings_box(); ?>
					</div>
				</div>
			</div>
			<?php
			if ( $i % 2 === 0 ) {
				$right .= ob_get_clean();
			} else {
				$left .= ob_get_clean();
			}
			?>
		<?php endforeach; ?>
		<div class="left">
			<?php echo $left; ?>
		</div>
		<div class="right">
			<?php echo $right; ?>
		</div>
	</div>
</div>
