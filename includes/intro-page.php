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
	<h1><?php esc_html_e( 'A Fast and Flexible Search and Query Engine for WordPress.', 'elasticpress' ); ?></h1>
	<div class="intro-container">
		<div class="intro-box">
			<div class="circle red-circle">
				<?php esc_html_e( 'Step', 'elasticpress' ); ?><p>1</p>
			</div>
			<h2><?php esc_html_e( 'Plugin has been installed', 'elasticpress' ); ?></h2>
			<p class="ep-copy-text"><?php esc_html_e( 'You\'ve taken your first step into a faster and more flexible search and query engine for WordPress', 'elasticpress' ); ?></p>
		</div>
		<div class="intro-box">
			<div class="circle white-circle">
				<?php esc_html_e( 'Step', 'elasticpress' ); ?><p>2</p>
			</div>
			<h2><?php esc_html_e( 'Set up hosting', 'elasticpress' ); ?></h2>
			<p class="ep-copy-text">
				<?php echo sprintf( __( 'The next step is to make sure you have a working Elasticsearch server. We recommend creating an %s account or if you want you can set up your own hosting', 'elasticpress' ),
					'<a href="https://www.elasticpress.io/">ElasticPress.io</a>'
				); ?>
			</p>
		</div>
		<div class="intro-box">
			<div class="circle white-circle">
				<?php esc_html_e( 'Step', 'elasticpress' ); ?><p>3</p>
			</div>
			<h2><?php esc_html_e( 'Index your content', 'elasticpress' ); ?></h2>
			<p class="ep-copy-text">
				<?php echo sprintf( __( '%sClick here%s to index your content through ElasticPress. You can also activate optional Features such as Protected Content and Autosuggest in the %sSettings%s page', 'elasticpress' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=elasticpress&do_sync' ) ) . '">',
				'</a>',
					'<a href="' . esc_url( admin_url( 'admin.php?page=elasticpress' ) ) . '">',
					'</a>'
				); ?>
		</div>
	</div>
	<div class="setup-message">
		<a class="setup-button" href="<?php echo esc_url( $setup_url ); ?>"><?php esc_html_e( 'Got hosting? Get Started', 'elasticpress' ); ?></a>
	</div>
</div>
