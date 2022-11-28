<?php
/**
 * Template for the ElasticPress.io page
 *
 * @since x.x
 * @package elasticpress
 */

use ElasticPress\Screen\ElasticPressIo;

defined( 'ABSPATH' ) || exit;

$elasticpress_io = new ElasticPressIo();

require_once __DIR__ . '/header.php';
?>

<div class="wrap">
	<h1><?php esc_html_e( 'ElasticPress.io', 'elasticpress' ); ?></h1>
	<div class="ep-status-report">
		<?php $elasticpress_io->render_messages(); ?>
	</div>
</div>
