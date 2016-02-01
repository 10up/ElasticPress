<?php
/**
 * Form for execution ElasticPress indexer
 *
 * @since   1.9
 *
 * @package elasticpress
 *
 * @author  Chris Wiegman <chris.wiegman@10up.com>
 */
?>
<?php
$host_alive = ep_check_host();
$class      = ( false === get_transient( 'ep_index_offset' ) ) ? ' button-primary ' : '';
$text       = ( false === get_transient( 'ep_index_offset' ) ) ? esc_html__( 'Run Index', 'elasticpress' ) : esc_html__( 'Running Index...', 'elasticpress' );
?>

<p>
	<?php if ( $host_alive && ! is_wp_error( $host_alive ) ) : ?>
		<?php if ( ep_is_activated() ) : ?>
			<input type="submit" name="ep_run_index" id="ep_run_index" class="button<?php echo esc_attr( $class ); ?> button-large" value="<?php echo esc_attr( $text ); ?>">
		<?php else : ?>
			<span class="error"><?php esc_html_e( 'ElasticPress needs to be enabled to run an index.', 'elasticpress' ); ?></span>
		<?php endif; ?>
	<?php else : ?>
		<span class="error"><?php esc_html_e( 'A proper host must be set before running an index.', 'elasticpress' ); ?></span>
	<?php endif; ?>
</p>
<p><div id="progressbar" style="display: none;"></div>
<p id="progressstats">
