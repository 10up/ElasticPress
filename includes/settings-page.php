<?php
/**
 * Template for ElasticPress settings page
 *
 * @since  1.7
 *
 * @package elasticpress
 *
 * @author  Allan Collins <allan.collins@10up.com>
 */
?>
<div class="wrap">
	<?php printf( '<h2>%s</h2>', esc_html__( 'ElasticPress', 'elasticpress' ) ); ?>

	<div id="dashboard-widgets" class="metabox-holder columns-2 has-right-sidebar">
		<div id='postbox-container-1' class='postbox-container'>
			<?php $meta_boxes = do_meta_boxes( $this->options_page, 'normal', null ); ?>
		</div>

		<div id='postbox-container-2' class='postbox-container'>
			<?php do_meta_boxes( $this->options_page, 'side', null ); ?>
		</div>

	</div>
</div>
