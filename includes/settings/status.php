<?php
/**
 * Template for displaying Elasticsearch statistics
 *
 * @since   1.9
 *
 * @package ElasticPress
 *
 * @author  Allan Collins <allan.collins@10up.com>
 */

$site_stats_id = null;

if ( is_multisite() && ( ! defined( 'EP_IS_NETWORK' ) || ! EP_IS_NETWORK ) ) {
	$site_stats_id = get_current_blog_id();
}

$stats = ep_get_index_status( $site_stats_id );

echo '<div id="ep_stats">';

?>

	<form>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Elasticsearch Host', 'elasticpress' ) ?>:</th>
				<?php if ( ! is_wp_error( ep_check_host() ) ) { ?>

					<?php $current_host = ep_get_host( true ); ?>

					<td><?php echo( ! is_wp_error( $current_host ) ? $current_host : esc_html__( 'Current host is set but cannot be contacted. Please contact the server administrator.', 'elasticpress' ) ); ?></td>

				<?php } else { ?>

					<td><?php esc_html_e( 'A host has not been set or is set but cannot be contacted. You must set a proper host to continue.', 'elasticpress' ); ?></td>

				<?php } ?>
			</tr>
		</table>
	</form>

<?php

if ( $stats['status'] ) {

	printf( '<h2>%s</h2>', esc_html__( 'Plugin Status', 'elasticpress' ) );
	?>
	<span class="dashicons dashicons-yes"
	      style="color:green;"></span> <?php esc_html_e( 'Connected to Elasticsearch.', 'elasticpress' ); ?><br/><br/>

	<?php if ( ep_is_activated() ) { ?>

		<span class="dashicons dashicons-yes"
		      style="color:green;"></span> <?php esc_html_e( 'ElasticPress can override WP search.', 'elasticpress' ); ?><br/><br/>

	<?php } else { ?>

		<span class="dashicons dashicons-no"
		      style="color:red;"></span> <?php esc_html_e( 'WordPress is currently using default search. To activate ElasticPress, check “Use Elasticsearch” in the form on the left and click “Save Changes".', 'elasticpress' ); ?>
		<br/>

	<?php } ?>

	<?php
	if ( ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) ) {

		echo '<div id="ep_ind_stats" class="ep_stats_section">';

		printf( '<h2>%s</h2>', esc_html__( 'Site Stats', 'elasticpress' ) );

		echo '<div id="ep_site_sel">';
		echo '<strong>' . esc_html__( 'Select a site:', 'elasticpress' ) . '</strong> <select name="ep_site_select" id="ep_site_select">';
		echo '<option value="0">' . esc_html__( 'Select', 'elasticpress' ) . '</option>';

		$site_list = get_site_transient( 'ep_site_list_for_stats' );

		if ( false === $site_list ) {

			$site_list = '';
			$sites     = ep_get_sites();

			foreach ( $sites as $site ) {

				$details = get_blog_details( $site['blog_id'] );

				$site_list .= sprintf( '<option value="%d">%s</option>', $site['blog_id'], $details->blogname );

			}

			set_site_transient( 'ep_site_list_for_stats', $site_list, 600 );

		}

		echo wp_kses( $site_list, array( 'option' => array( 'value' => array() ) ) );

		echo '</select>';
		echo '</div>';

		echo '<div id="ep_site_stats"></div>';

		echo '</div>';

	}
	?>

	<div id="ep_cluster_stats" class="ep_stats_section">

		<?php printf( '<h2>%s</h2>', esc_html__( 'Cluster Stats', 'elasticpress' ) ); ?>

		<?php
		$stats      = ep_get_cluster_status();
		$fs         = $stats->nodes->fs;
		$disk_usage = $fs->total_in_bytes - $fs->available_in_bytes;
		?>

		<ul>
			<li>
				<strong><?php esc_html_e( 'Disk Usage:', 'elasticpress' ); ?></strong> <?php echo esc_html( number_format( ( $disk_usage / $fs->total_in_bytes ) * 100, 0 ) ); ?>
				%
			</li>
			<li>
				<strong><?php esc_html_e( 'Disk Space Available:', 'elasticpress' ); ?></strong> <?php echo esc_html( $this->ep_byte_size( $fs->available_in_bytes ) ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Total Disk Space:', 'elasticpress' ); ?></strong> <?php echo esc_html( $this->ep_byte_size( $fs->total_in_bytes ) ); ?>
			</li>
		</ul>
	</div>

	<?php
} elseif ( ! is_wp_error( ep_check_host() ) ) {

	$allowed_tags = array(
		'p'    => array(),
		'code' => array(),
	);

	echo '<span class="dashicons dashicons-no" style="color:red;"></span> <strong>' . esc_html__( 'ERROR:', 'elasticpress' ) . '</strong> ' . wp_kses( $stats['msg'], $allowed_tags );

}

echo '</div>';
