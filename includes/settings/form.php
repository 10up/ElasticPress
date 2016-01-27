<?php
/**
 * Form for setting ElasticPress preferences
 *
 * @since   1.9
 *
 * @package elasticpress
 *
 * @author  Allan Collins <allan.collins@10up.com>
 */
?>

<?php
//Set form action
$action = 'options.php';

if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
	$action = '';
}
?>

<form method="POST" action="<?php echo esc_attr( $action ); ?>">
	<?php

	settings_fields( 'elasticpress' );
	do_settings_sections( 'elasticpress' );

	if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {

		$host = get_site_option( 'ep_host' );

	} else {

		$host = get_option( 'ep_host' );

	}

	if ( ( ! ep_host_by_option() && ! is_wp_error( ep_check_host() ) ) || is_wp_error( ep_check_host() ) || $host ) {
		submit_button();
	}

	?>
</form>
