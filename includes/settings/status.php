<?php
/**
 * Template for displaying Elasticsearch statistics
 *
 * @since   1.7
 *
 * @package elasticpress
 *
 * @author  Allan Collins <allan.collins@10up.com>
 */

$stats        = Jovo_Lib::ep_get_index_status();
$search_stats = Jovo_lib::ep_get_search_status();

echo '<div id="jovo_stats">';

?>

	<form>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'ElasticSearch Host', 'elasticpress' ) ?>:</th>
				<?php if ( ! is_wp_error( Jovo_Lib::check_host() ) ) { ?>

					<?php $current_host = ep_get_host( true ); ?>

					<td><?php echo( ! is_wp_error( $current_host ) ? $current_host : esc_html__( 'Current host is set but cannot be contacted. Please contact the server administrator.', 'elasticpress' ) ); ?></td>

				<?php } else { ?>

					<td><?php esc_html_e( 'A host has not been set. You must set a host to continue.', 'elasticpress' ); ?></td>

				<?php } ?>
			</tr>
		</table>
	</form>

<?php

if ( $stats['status'] ) {

	printf( '<h2>%s</h2>', esc_html__( 'Plugin Status', 'elasticpress' ) );
	?>
	<span class="dashicons dashicons-yes"
	      style="color:green;"></span> <?php esc_html_e( 'Connected to ElasticSearch.', 'elasticpress' ); ?><br/><br/>

	<?php if ( ep_is_activated() ) { ?>

		<span class="dashicons dashicons-yes"
		      style="color:green;"></span> <?php esc_html_e( 'ElasticPress can override WP search.', 'elasticpress' ); ?><br/>
		<br/>

	<?php } else { ?>

		<span class="dashicons dashicons-no"
		      style="color:red;"></span> <?php esc_html_e( 'ElasticPress is not activated and cannot override WP search. You can activate it on the form to the left.', 'elasticpress' ); ?>
		<br/>

	<?php } ?>

	<?php printf( '<h2>%s</h2>', esc_html__( 'System Stats', 'elasticpress' ) ); ?>

	<div class="search_stats">

		<?php printf( '<h3>%s</h3>', esc_html__( 'Search Stats', 'elasticpress' ) ); ?>

		<ul>
			<li>
				<strong><?php esc_html_e( 'Total Queries:', 'elasticpress' ); ?> </strong> <?php echo esc_html( $search_stats->query_total ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Query Time:', 'elasticpress' ); ?> </strong> <?php echo esc_html( $search_stats->query_time_in_millis ); ?>
				ms
			</li>
			<li>
				<strong><?php esc_html_e( 'Total Fetches:', 'elasticpress' ); ?> </strong> <?php echo esc_html( $search_stats->fetch_total ); ?>
				<br/>
			<li>
				<strong><?php esc_html_e( 'Fetch Time:', 'elasticpress' ); ?> </strong> <?php echo esc_html( $search_stats->fetch_time_in_millis ); ?>
				ms
			</li>
		</ul>

	</div>

	<div class="index_stats">

		<?php printf( '<h3>%s</h3>', esc_html__( 'Index Stats', 'elasticpress' ) ); ?>

		<ul>
			<li>
				<strong><?php esc_html_e( 'Index Total:', 'elasticpress' ); ?> </strong> <?php echo esc_html( $stats['data']->index_total ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Index Time:', 'elasticpress' ); ?> </strong> <?php echo esc_html( $stats['data']->index_time_in_millis ); ?>ms
			</li>
		</ul>

	</div>

	<?php
	if ( is_multisite() ) {

		echo '<div id="jovo_ind_stats" class="jovo_stats_section">';

		printf( '<h2>%s</h2>', esc_html__( 'Site Stats', 'elasticpress' ) );

		$sites = ep_get_sites();

		echo '<div id="jovo_site_sel">';
		echo '<strong>' . esc_html__( 'Select a site:', 'elasticpress' ) . '</strong> <select name="jovo_site_select" id="jovo_site_select">';
		echo '<option value="0">' . esc_html__( 'Select', 'elasticpress' ) . '</option>';

		foreach ( $sites as $site ) {

			$details = get_blog_details( $site['blog_id'] );

			printf( '<option value="%d">%s</option>', $site['blog_id'], $details->blogname );

		}

		echo '</select>';
		echo '</div>';

		foreach ( $sites as $site ) {

			$stats        = Jovo_Lib::ep_get_index_status( $site['blog_id'] );
			$search_stats = Jovo_Lib::ep_get_search_status( $site['blog_id'] );
			$details      = get_blog_details( $site['blog_id'] );
			?>
			<div id="jovo_<?php echo $site['blog_id']; ?>" class="jovo_site">
				<?php if ( $stats['status'] ) : ?>
					<div class="search_stats">
						<?php printf( '<h3>%s</h3>', esc_html__( 'Search Stats', 'elasticpress' ) ); ?>
						<ul>
							<li>
								<strong><?php esc_html_e( 'Total Queries:', 'elasticpress' ); ?> </strong> <?php echo esc_html( $search_stats->query_total ); ?>
							</li>
							<li>
								<strong><?php esc_html_e( 'Query Time:', 'elasticpress' ); ?> </strong> <?php echo esc_html( $search_stats->query_time_in_millis ); ?>ms
							</li>
							<li>
								<strong><?php esc_html_e( 'Total Fetches:', 'elasticpress' ); ?> </strong> <?php echo esc_html( $search_stats->fetch_total ); ?>
							</li>
							<li>
								<strong><?php esc_html_e( 'Fetch Time:', 'elasticpress' ); ?> </strong> <?php echo esc_html( $search_stats->fetch_time_in_millis ); ?>ms
							</li>
						</ul>

					</div>
					<div class="index_stats">
						<?php printf( '<h3>%s</h3>', esc_html__( 'Index Stats', 'elasticpress' ) ); ?>
						<ul>
							<li>
								<strong><?php esc_html_e( 'Index Total:', 'elasticpress' ); ?> </strong> <?php echo esc_html( $stats['data']->index_total ); ?>
							</li>
							<li>
								<strong><?php esc_html_e( 'Index Time:', 'elasticpress' ); ?> </strong> <?php echo esc_html( $stats['data']->index_time_in_millis ); ?>ms
							</li>
						</ul>
					</div>
				<?php endif; ?>
			</div>
			<?php
		}

		echo '</div>';

	}
	?>

	<div id="jovo_cluster_stats" class="jovo_stats_section">

		<?php printf( '<h2>%s</h2>', esc_html__( 'Cluster Stats', 'elasticpress' ) ); ?>

		<?php
		$stats      = Jovo_Lib::ep_get_cluster_status();
		$fs         = $stats->nodes->fs;
		$disk_usage = $fs->total_in_bytes - $fs->available_in_bytes;
		?>

		<ul>
			<li>
				<strong><?php esc_html_e( 'Disk Usage:', 'elasticpress' ); ?></strong> <?php echo esc_html( number_format( ( $disk_usage / $fs->total_in_bytes ) * 100, 0 ) ); ?>%
			</li>
			<li>
				<strong><?php esc_html_e( 'Disk Space Available:', 'elasticpress' ); ?></strong> <?php echo esc_html( Jovo_Lib::ep_byte_size( $fs->available_in_bytes ) ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Total Disk Space:', 'elasticpress' ); ?></strong> <?php echo esc_html( Jovo_Lib::ep_byte_size( $fs->total_in_bytes ) ); ?>
			</li>
		</ul>
	</div>

	<?php
} else {

	$allowed_tags = array(
		'p'    => array(),
		'code' => array(),
	);

	echo '<span class="dashicons dashicons-no" style="color:red;"></span> <strong>' . esc_html__( 'ERROR:', 'elasticpress' ) . '</strong> ' . wp_kses( $stats['msg'], $allowed_tags );

}

echo '</div>';
