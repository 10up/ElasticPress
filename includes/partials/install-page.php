<?php
/**
 * Template for ElasticPress install page
 *
 * @since  2.1
 * @package elasticpress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
	$setup_url     = admin_url( 'network/admin.php?page=elasticpress-settings' );
	$sync_url      = admin_url( 'network/admin.php?page=elasticpress&do_sync' );
	$dashboard_url = admin_url( 'network/admin.php?page=elasticpress' );
} else {
	$setup_url     = admin_url( 'admin.php?page=elasticpress-settings' );
	$sync_url      = admin_url( 'admin.php?page=elasticpress&do_sync' );
	$dashboard_url = admin_url( 'admin.php?page=elasticpress' );
}

$skip_install_url = add_query_arg(
	[
		'ep-skip-install' => 1,
		'nonce'           => wp_create_nonce( 'ep-skip-install' ),
	]
);
?>

<?php require_once __DIR__ . '/header.php'; ?>

<div class="wrap intro">
	<h1><?php esc_html_e( 'A Fast and Flexible Search and Query Engine for WordPress.', 'elasticpress' ); ?></h1>

	<?php if ( isset( $_GET['install_complete'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
		<div class="intro-container-success">
			<h2 class="setup-complete">Setup Completed!</h2>
			<div class="ep-circle red-ep-circle ep-config-success">
				<span class="dashicons dashicons-yes"></span>
			</div>
			<p><?php esc_html_e( 'That’s it! You’re ready to experience faster search and gain the ability to create powerful queries on your site!', 'elasticpres' ); ?></p>
			<div class="setup-message">
				<a class="setup-button" href="<?php echo esc_url( $dashboard_url ); ?>"><?php esc_html_e( 'Go to dashboard', 'elasticpress' ); ?></a>
			</div>
		</div>
	<?php else : ?>
		<div class="intro-container">
			<div class="intro-box">
				<div class="ep-circle white-ep-circle">
					<?php esc_html_e( 'Step', 'elasticpress' ); ?><p>1</p>
				</div>
				<h2><?php esc_html_e( 'Plugin has been installed', 'elasticpress' ); ?></h2>
				<p class="ep-copy-text"><?php esc_html_e( 'You\'ve taken your first step into a faster and more flexible search and query engine for WordPress', 'elasticpress' ); ?></p>
			</div>
			<div class="intro-box">
				<div class="ep-circle <?php echo 2 === $install_status ? 'red-ep-circle' : 'white-ep-circle'; ?> ep-middle-circle">
					<?php esc_html_e( 'Step', 'elasticpress' ); ?><p>2</p>
				</div>
				<h2><?php esc_html_e( 'Set up Elasticsearch hosting', 'elasticpress' ); ?></h2>
				<p class="ep-copy-text">
					<?php echo wp_kses_post( __( 'The next step is to make sure you have a working Elasticsearch server. We recommend creating an <a href="https://elasticpress.io">ElasticPress.io</a> account or if you want you can set up your own hosting.', 'elasticpress' ) ); ?>
				</p>
			</div>
			<div class="intro-box">
				<div class="ep-circle <?php echo 3 === $install_status ? 'red-ep-circle' : 'white-ep-circle'; ?>">
					<?php esc_html_e( 'Step', 'elasticpress' ); ?><p>3</p>
				</div>
				<h2><?php esc_html_e( 'Index your content', 'elasticpress' ); ?></h2>
				<p class="ep-copy-text">
					<?php esc_html_e( 'Click below to index your content through ElasticPress. You can also activate optional Features such as Protected Content and Autosuggest in the Features page', 'elasticpress' ); ?>
				</p>
			</div>
		</div>
		<div class="setup-message">
			<?php if ( 3 === $install_status ) : ?>
				<a class="setup-button" href="<?php echo esc_url( $sync_url ); ?>"><?php esc_html_e( 'Index Your Content', 'elasticpress' ); ?></a>
				<p><a href="<?php echo esc_url( $skip_install_url ); ?>">Skip Install &#187;</a></p>
			<?php else : ?>
				<a class="setup-button" href="<?php echo esc_url( $setup_url ); ?>"><?php esc_html_e( 'Got hosting? Get Started', 'elasticpress' ); ?></a>
				<p><a href="<?php echo esc_url( $skip_install_url ); ?>">Skip Install &#187;</a></p>
			<?php endif ?>
		</div>
	<?php endif; ?>
</div>
