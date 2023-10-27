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
	$sync_url      = admin_url( 'network/admin.php?page=elasticpress-sync&do_sync=install' );
	$dashboard_url = admin_url( 'network/admin.php?page=elasticpress' );
} else {
	$setup_url     = admin_url( 'admin.php?page=elasticpress-settings' );
	$sync_url      = admin_url( 'admin.php?page=elasticpress-sync&do_sync=install' );
	$dashboard_url = admin_url( 'admin.php?page=elasticpress' );
}

$skip_install_url = add_query_arg(
	[
		'ep-skip-install'  => 1,
		'ep-skip-features' => 1,
		'nonce'            => wp_create_nonce( 'ep-skip-install' ),
	]
);

$skip_index_url = remove_query_arg( 'ep-skip-features', $skip_install_url );
?>

<?php require_once __DIR__ . '/header.php'; ?>

<div class="wrap intro">
	<h1><?php esc_html_e( 'A Fast and Flexible Search and Query Engine for WordPress.', 'elasticpress' ); ?></h1>

	<?php if ( isset( $_GET['install_complete'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
		<div class="intro-container-success">
			<h2 class="setup-complete">Setup Completed!</h2>
			<div class="ep-circle ep-circle--active ep-config-success">
				<span class="dashicons dashicons-yes"></span>
			</div>
			<p><?php esc_html_e( 'That’s it! You’re ready to experience faster search and gain the ability to create powerful queries on your site!', 'elasticpress' ); ?></p>
			<div class="setup-message">
				<a class="setup-button" href="<?php echo esc_url( $dashboard_url ); ?>"><?php esc_html_e( 'Go to dashboard', 'elasticpress' ); ?></a>
			</div>
		</div>
	<?php else : ?>
		<form method="post" action="">
			<?php wp_nonce_field( 'ep_install_page', 'ep_install_page_nonce' ); ?>
			<div class="intro-container">
				<div class="intro-box">
					<div class="ep-circle ep-circle--first white-ep-circle">
						<?php esc_html_e( 'Step', 'elasticpress' ); ?><p>1</p>
					</div>
					<h2><?php esc_html_e( 'Plugin has been installed', 'elasticpress' ); ?></h2>
					<p class="ep-copy-text"><?php esc_html_e( 'You\'ve taken your first step into a faster and more flexible search and query engine for WordPress', 'elasticpress' ); ?></p>
				</div>
				<div class="intro-box">
					<div class="ep-circle <?php echo 2 === $install_status ? 'ep-circle--active' : ''; ?>">
						<?php esc_html_e( 'Step', 'elasticpress' ); ?><p>2</p>
					</div>
					<h2><?php esc_html_e( 'Set up Elasticsearch hosting', 'elasticpress' ); ?></h2>
					<p class="ep-copy-text">
						<?php echo wp_kses_post( __( 'The next step is to make sure you have a working Elasticsearch server. We recommend creating an <a href="https://elasticpress.io">ElasticPress.io</a> account or if you want you can set up your own hosting.', 'elasticpress' ) ); ?>
					</p>
					<?php if ( 2 === $install_status ) : ?>
						<div class="setup-message">
							<a class="setup-button" href="<?php echo esc_url( $setup_url ); ?>"><?php esc_html_e( 'Got hosting? Get Started', 'elasticpress' ); ?></a>
							<p><a href="<?php echo esc_url( $skip_install_url ); ?>"><?php esc_html_e( 'Skip Install »', 'elasticpress' ); ?></a></p>
						</div>
					<?php endif; ?>
				</div>
				<div class="intro-box">
					<div class="ep-circle <?php echo 3 === $install_status ? 'ep-circle--active' : ''; ?>">
						<?php esc_html_e( 'Step', 'elasticpress' ); ?><p>3</p>
					</div>
					<h2><?php esc_html_e( 'Select your features', 'elasticpress' ); ?></h2>
					<div class="ep-copy-text">
						<p><?php esc_html_e( 'ElasticPress will sync the data you select, then keep it up-to-date automatically.', 'elasticpress' ); ?></p>
						<?php if ( 3 === $install_status ) : ?>
							<ul class="ep-feature-list">
								<?php
								$features = \ElasticPress\Features::factory()->registered_features;
								foreach ( $features as $feature ) {
									$feature_status_code  = (int) $feature->requirements_status()->code;
									$activation_available = $feature->available_during_installation;

									if ( 2 === $feature_status_code ) {
										continue;
									}

									if ( ! $activation_available ) {
										continue;
									}

									$should_be_checked = 0 === $feature_status_code || $feature->is_active();
									?>
									<li>
										<label>
											<input
												type="checkbox"
												name="features[]"
												value="<?php echo esc_attr( $feature->slug ); ?>"
												<?php checked( $should_be_checked ); ?>>
											<?php echo esc_html( $feature->get_short_title() ); ?>
										</label>
										<?php if ( $feature->summary ) : ?>
											<span class="a11y-tip a11y-tip--no-delay">
												<a href="<?php echo esc_url( $feature->docs_url ); ?>" class="a11y-tip__trigger ep-feature-info" target="_blank" rel="noreferrer noopener">
													<span class="dashicons dashicons-info" role="presentation"></span>
													<span class="screen-reader-text">
														<?php
														printf(
															/* translators: %s: Feature name. */
															esc_html__( 'Learn more about %s.', 'elasticpress' ),
															esc_html( $feature->get_short_title() )
														);
														?>
													</span>
												</a>
												<span role="tooltip" class="a11y-tip__help a11y-tip__help--top">
													<?php echo wp_kses( $feature->summary, 'ep-html' ); ?>
													<?php esc_html_e( 'Click to learn more.', 'elasticpress' ); ?>
												</span>
											</span>
										<?php endif; ?>
									</li>
									<?php
								}
								?>
							</ul>
							<p><?php esc_html_e( 'Don\'t worry if you\'re not sure what features you need, you can always make changes to them later on.', 'elasticpress' ); ?></p>
						<?php endif; ?>
					</div>
					<?php if ( 3 === $install_status ) : ?>
						<div class="setup-message">
							<button type="submit" class="setup-button"><?php esc_html_e( 'Save Features', 'elasticpress' ); ?></button>
							<p><a href="<?php echo esc_url( $skip_install_url ); ?>"><?php esc_html_e( 'Skip Install »', 'elasticpress' ); ?></a></p>
						</div>
					<?php endif; ?>
				</div>
				<div class="intro-box">
					<div class="ep-circle <?php echo 4 === $install_status ? 'ep-circle--active' : ''; ?>">
						<?php esc_html_e( 'Step', 'elasticpress' ); ?><p>4</p>
					</div>
					<h2><?php esc_html_e( 'Index your content', 'elasticpress' ); ?></h2>
					<p class="ep-copy-text">
						<?php esc_html_e( 'Click below to index your content through ElasticPress. You can also activate optional Features such as Protected Content and Autosuggest in the Features page', 'elasticpress' ); ?>
					</p>
					<?php if ( 4 === $install_status ) : ?>
						<div class="setup-message">
							<a class="setup-button" href="<?php echo esc_url( $sync_url ); ?>"><?php esc_html_e( 'Index Your Content', 'elasticpress' ); ?></a>
							<p><a href="<?php echo esc_url( $skip_index_url ); ?>"><?php esc_html_e( 'Skip Install »', 'elasticpress' ); ?></a></p>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</form>
	<?php endif; ?>
</div>
