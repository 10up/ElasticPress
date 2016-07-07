<?php
/**
 * Template for ElasticPress intro page
 *
 * @since  2.1
 * @package elasticpress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
	$setup_url = admin_url( 'network/admin.php?page=elasticpress-settings' );
} else {
	$setup_url = admin_url( 'admin.php?page=elasticpress-settings' );
}
?>

<?php require_once( dirname( __FILE__ ) . '/header.php' ); ?>

<div class="wrap intro">
	<div class="left">
		<h2><?php esc_html_e( 'Supercharge WordPress Performance with ElasticPress', 'elasticpress' ); ?></h2>
		<h3><?php _e( "You're almost there! The plugin is free to use but requires an Elasticsearch server behind-the-scenes. There are tons of services that let you easily get one like <a href='https://qbox.io'>Qbox</a>. If you have a bigger website, 10up provides Elasticsearch hosting via <a href='http://www.elasticpress.io'>ElasticPress.io</a>.", 'elasticpress' ); ?></h3>
	</div>
	<img class="modules-screenshot" src="<?php echo EP_URL . 'images/modules-screenshot.png'; ?>">
</div>

<div class="setup-message">
	<a class="setup-button setup-button-primary" href="<?php echo esc_url( $setup_url ); ?>">Set Up</a>
	<a class="setup-button" href="https://wordpress.org/plugins/elasticpress">Learn More</a>
</div>