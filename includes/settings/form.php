<?php
/**
 * Form for setting ElasticPress preferences
 *
 * @since   1.7
 *
 * @package elasticpress
 *
 * @author  Allan Collins <allan.collins@10up.com>
 */
?>

<?php
//Set form action
$action = 'options.php';

if ( is_multisite() ) {
	$action = '';
}
?>

<form method="POST" action="<?php echo esc_attr( $action ); ?>">
	<?php

	settings_fields( 'elasticpress' );
	do_settings_sections( 'elasticpress' );

	$stats = EP_Lib::ep_get_index_status();

	if ( ( $stats['status'] && ! is_wp_error( ep_check_host() ) ) || is_wp_error( ep_check_host() ) || get_site_option( 'ep_host' ) ) {
		submit_button();
	}

	?>
</form>
