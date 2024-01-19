<?php
/**
 * Template for ElasticPress Index health page
 *
 * @since  3.1
 * @package elasticpress
 */

use ElasticPress\IndexHelper;
use ElasticPress\Stats;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once __DIR__ . '/header.php';

$index_meta = IndexHelper::factory()->get_index_meta();

if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
	$sync_url = network_admin_url( 'admin.php?page=elasticpress-sync' );
} else {
	$sync_url = admin_url( 'admin.php?page=elasticpress-sync' );
}

Stats::factory()->build_stats();

$index_health   = Stats::factory()->get_health();
$totals         = Stats::factory()->get_totals();
$failed_queries = Stats::factory()->get_failed_queries();
?>

<div class="error-overlay <?php if ( ! empty( $index_meta ) ) : ?>syncing<?php endif; ?>"></div>
<div class="wrap metabox-holder">
	<h1><?php esc_html_e( 'Index Health', 'elasticpress' ); ?></h1>

	<?php if ( ! empty( $failed_queries ) ) : ?>
		<p>
			<?php esc_html_e( 'It seems some requests to Elasticsearch failed and it was not possible to build your stats properly:', 'elasticpress' ); ?>
		</p>
		<ul>
			<?php foreach ( $failed_queries as $failed_query ) : ?>
				<li>
					<?php
					printf(
						'<code>%1$s</code>: <code>%2$s</code>',
						esc_html( $failed_query['path'] ),
						esc_html( $failed_query['error'] )
					);
					?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php elseif ( ! empty( $index_health ) ) : ?>
		<div class="ep-flex-container">
			<div class="stats-list postbox">
				<h2 class="hndle stats-list-th"><span><?php esc_html_e( 'Index list', 'elasticpress' ); ?></span><span><?php esc_html_e( 'Health', 'elasticpress' ); ?></span></h2>
				<?php
				foreach ( $index_health as $index_stat ) :
					?>
					<p class="inside"><?php echo esc_html( $index_stat['name'] ); ?>
						<span class="status-circle <?php echo esc_attr( $index_stat['health'] ); ?>-status">
					<?php echo esc_html( $index_stat['health'] ); ?>
				</span>
					</p>
				<?php endforeach; ?>
			</div>
			<div class="stats-queries postbox">
				<h2 class="hndle"><?php esc_html_e( 'Queries & Indexing total', 'elasticpress' ); ?></h2>
				<div class="ep-qchart-container">
					<div class="inside">
						<canvas id="queriesTotalChart" width="400" height="400"></canvas>
					</div>
				</div>
			</div>
			<div class="postbox doc-chart">
				<h2 class="hndle"><?php esc_html_e( 'Documents', 'elasticpress' ); ?></h2>
				<div class="inside">
					<canvas id="documentChart" width="800" height="450"></canvas>
				</div>
			</div>
			<div class="postbox ep-totals">
				<h2 class="hndle">Totals</h2>
				<div class="ep-flex-container">
					<div class="ep-totals-column inside">
						<p class="ep-totals-title"><?php esc_html_e( 'Total Documents', 'elasticpress' ); ?></p>
						<p class="ep-totals-data"><?php echo esc_html( $totals['docs'] ); ?></p>
					</div>
					<div class="ep-totals-column inside">
						<p class="ep-totals-title"><?php esc_html_e( 'Total Size', 'elasticpress' ); ?></p>
						<p class="ep-totals-data"><?php echo esc_html( Stats::factory()->convert_to_readable_size( $totals['size'] ) ); ?></p>
					</div>
					<div class="ep-totals-column inside">
						<p class="ep-totals-title"><?php esc_html_e( 'Total Memory', 'elasticpress' ); ?></p>
						<p class="ep-totals-data"><?php echo esc_html( Stats::factory()->convert_to_readable_size( $totals['memory'] ) ); ?></p>
					</div>
				</div>
			</div>
		</div>
	<?php else : ?>
		<p>
			<?php
			printf(
				/* translators: %s: Sync page link. */
				esc_html__( 'We could not find any data for your Elasticsearch indices. Maybe you need to %s?', 'elasticpress' ),
				sprintf(
					'<a href="%1$s">%2$s</a>',
					esc_url( $sync_url ),
					esc_html__( 'sync your content', 'elasticpress' )
				)
			);
			?>
		</p>
	<?php endif; ?>
</div>
