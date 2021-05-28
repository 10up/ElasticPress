<?php
/**
 * Template for ElasticPress dashboard page
 *
 * @since  2.1
 * @package elasticpress
 */

use ElasticPress\Elasticsearch as Elasticsearch;
use ElasticPress\Features as Features;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
	$index_meta = get_site_option( 'ep_index_meta', false );
} else {
	$index_meta = get_option( 'ep_index_meta', false );
}
?>

<?php require_once __DIR__ . '/header.php'; ?>

<div class="error-overlay <?php if ( ! empty( $index_meta ) ) : ?>syncing<?php endif; ?> <?php if ( ! Elasticsearch::factory()->get_elasticsearch_version() ) : ?>cant-connect<?php endif; ?>"></div>
<div class="wrap">
	<h2 class="ep-list-features"><?php esc_html_e( 'List of features', 'elasticpress' ); // We use this since WP inserts warnings after the first h2. This will be hidden. ?></h2>
	<div class="ep-features metabox-holder">
		<?php
		$features = Features::factory()->registered_features;
		uasort(
			$features,
			function( $feature_a, $feature_b ) {
				$order_feature_a = (int) $feature_a->order;
				$order_feature_b = (int) $feature_b->order;

				if ( $order_feature_a === $order_feature_b ) {
					return 0;
				}

				return $order_feature_a < $order_feature_b ? -1 : 1;
			}
		);
		?>

		<?php
		$left  = '';
		$right = '';
		$i     = 0;
		foreach ( $features as $feature ) :
			$i++;
			$requirements_status = $feature->requirements_status();
			$active              = $feature->is_active();

			$feature_classes = 'feature-requirements-status-' . (int) $requirements_status->code;

			if ( ! empty( $active ) ) {
				$feature_classes .= ' feature-active';
			}

			if ( ! empty( $index_meta ) && ! empty( $index_meta['feature_sync'] ) && $feature->slug === $index_meta['feature_sync'] ) {
				$feature_classes .= ' feature-syncing';
			}

			ob_start();
			?>
			<div class="<?php if ( $feature->requires_install_reindex && defined( 'EP_DASHBOARD_SYNC' ) && ! EP_DASHBOARD_SYNC ) : ?>dash-sync-disabled<?php endif; ?> ep-feature ep-feature-<?php echo esc_attr( $feature->slug ); ?> <?php echo esc_attr( $feature_classes ); ?>">
				<div class="postbox">
					<h2 class="hndle">
						<span><?php echo esc_html( $feature->title ); ?></span>
						<a class="settings-button"><?php esc_html_e( 'settings', 'elasticpress' ); ?></a>
					</h2>

					<div class="description inside">

						<?php $feature->output_feature_box(); ?>

					</div>

					<div class="settings inside">
						<?php $feature->output_settings_box(); ?>
					</div>
				</div>
			</div>
			<?php
			if ( 'right' === $feature->group_order || 'left' === $feature->group_order ) {
				${$feature->group_order} .= ob_get_clean();
			} else {
				if ( 0 === $i % 2 ) {
					$right .= ob_get_clean();
				} else {
					$left .= ob_get_clean();
				}
			}
			?>
		<?php endforeach; ?>
		<div class="left">
			<?php echo wp_kses( $left, 'ep-html' ); ?>
		</div>
		<div class="right">
			<?php echo wp_kses( $right, 'ep-html' ); ?>
		</div>
	</div>
</div>
