<?php
/**
 * Form for execution ElasticPress indexer
 *
 * @since   1.7
 *
 * @package elasticpress
 *
 * @author  Chris Wiegman <chris.wiegman@10up.com>
 */
?>
<?php
$class = ( false === get_transient( 'ep_index_offset' ) ) ? ' button-primary ' : '';
$text  = ( false === get_transient( 'ep_index_offset' ) ) ? esc_html__( 'Run Index', 'elasticpress' ) : esc_html__( 'Running Index...', 'elasticpress' );
?>

<p>
	<input type="submit" name="ep_run_index" id="ep_run_index" class="button<?php echo esc_attr( $class ); ?> button-large" value="<?php echo esc_attr( $text ); ?>">
</p>
<p><div id="progressbar" style="display: none;"></div>
<p id="progressstats">
