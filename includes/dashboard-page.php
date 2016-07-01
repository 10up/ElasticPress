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

$args = apply_filters( 'ep_index_posts_args', array(
	'posts_per_page'         => 1,
	'post_type'              => array( 'post', 'page' ),
	'post_status'            => array( 'publish' ),
	'offset'                 => 0,
	'ignore_sticky_posts'    => true,
	'orderby'                => 'ID',
	'order'                  => 'DESC',
) );

$query = new WP_Query( $args );
?>

<?php require_once( dirname( __FILE__ ) . '/header.php' ); ?>

<div class="error-overlay <?php if ( ! empty( $index_meta ) ) : ?>syncing<?php endif; ?> <?php if ( ! ep_elasticsearch_can_connect() ) : ?>cant-connect<?php endif; ?>"></div>
<div class="wrap">
	<h2><?php esc_html_e( 'ElasticPress', 'elasticpress' ); ?></h2>

	<p><?php esc_html_e( "ElasticPress let's you supercharge your WordPress website with various modules. Activate the ones you need below.", 'elasticpress' ); ?></p>

	<div class="ep-modules metabox-holder">
		<?php $modules = EP_Modules::factory()->registered_modules; ?>

		<?php 
		$left = '';
		$right = '';
		$i = 0;
		foreach ( $modules as $module ) :
			$i++;
			$module_classes = ( $module->is_active() ) ? 'module-active' : '';

			if ( ! empty( $index_meta ) && ! empty( $index_meta['module_sync'] ) && $module->slug === $index_meta['module_sync'] ) {
				$module_classes .= ' module-syncing';
			}

			$deps_met = $module->dependencies_met();
			if ( is_wp_error( $deps_met ) ) {
				$module_classes .= 'module-dependencies-unmet';
			}
			ob_start();
			?>
			<div class="ep-module ep-module-<?php echo esc_attr( $module->slug ); ?> <?php echo esc_attr( $module_classes ); ?>">
				<div class="postbox">
					<h2 class="hndle"><span><?php echo esc_html( $module->title ); ?></span></h2>

					<div class="inside activity-block">

						<?php $module->output_module_box(); ?>

					</div>

					<div class="action">
						<div class="module-message module-error">
							<?php if ( is_wp_error( $deps_met ) ) : ?>
								<?php echo esc_html( $deps_met->get_error_message() ); ?>
							<?php endif; ?>
						</div>
						
						<a data-module="<?php echo esc_attr( $module->slug ); ?>" class="js-toggle-module deactivate button"><?php esc_html_e( 'Deactivate', 'elasticpress' ); ?></a>
						<a data-module="<?php echo esc_attr( $module->slug ); ?>" class="js-toggle-module activate button button-primary"><?php esc_html_e( 'Activate', 'elasticpress' ); ?></a>
						<button disabled data-module="<?php echo esc_attr( $module->slug ); ?>" class="js-toggle-module syncing-placeholder button"><?php esc_html_e( 'Syncing...', 'elasticpress' ); ?></a>
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
