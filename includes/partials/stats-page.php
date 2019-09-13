<?php
/**
 * Template for ElasticPress Index health page
 *
 * @since  3.1
 * @package elasticpress
 */

use ElasticPress\Stats as Stats;
use ElasticPress\Elasticsearch as Elasticsearch;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once __DIR__ . '/header.php';

Stats::factory()->build_stats();

$index_health = Stats::factory()->get_health();
$totals       = Stats::factory()->get_totals();
?>
<div class="wrap metabox-holder">
	<h1><?php esc_html_e( 'Index Health', 'elasticpress' ); ?></h1>

	<?php if ( ! empty( $index_health ) ) : ?>
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
					<canvas id="queriesTotalChart" width="400" height="400"></canvas>
				</div>
			</div>
			<div class="postbox doc-chart">
				<h2 class="hndle"><?php esc_html_e( 'Documents', 'elasticpress' ); ?></h2>
				<canvas id="documentChart" width="800" height="450"></canvas>
			</div>
			<div class="postbox ep-totals">
				<h2 class="hndle">Totals</h2>
				<div class="ep-flex-container">
					<div class="ep-totals-1st-row inside">
						<p class="ep-totals-title"><?php esc_html_e( 'Total Documents', 'elasticpress' ); ?></p>
						<p class="ep-totals-data"><?php echo esc_html( $totals['docs'] ); ?></p>
					</div>
					<div class="ep-totals-1st-row inside">
						<p class="ep-totals-title"><?php esc_html_e( 'Total Size', 'elasticpress' ); ?></p>
						<p class="ep-totals-data"><?php echo esc_html( Stats::factory()->convert_to_readable_size( $totals['size'] ) ); ?></p>
					</div>
					<div class="ep-totals-2nd-row inside">
						<p class="ep-totals-title"><?php esc_html_e( 'Total Memory', 'elasticpress' ); ?></p>
						<p class="ep-totals-data"><?php echo esc_html( Stats::factory()->convert_to_readable_size( $totals['memory'] ) ); ?></p>
					</div>
				</div>
			</div>
			<div class="stats-queries postbox">
				<h2 class="hndle"><?php esc_html_e( 'Queries & Indexing Time', 'elasticpress' ); ?></h2>
				<div class="ep-qchart-container">
					<canvas id="queriesTimeChart" width="400" height="400"></canvas>
				</div>
			</div>
		</div>
	<?php else : ?>
		<p><?php echo wp_kses( __( 'We could not find any data for your Elasticsearch indices. Maybe you need to <a href="admin.php?page=elasticpress">sync your content</a>?', 'elasticpress' ), 'ep-html' ); ?></p>
	<?php endif; ?>
</div>
