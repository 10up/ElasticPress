<?php
/**
 * Template for ElasticPress settings page
 *
 * @since  2.1
 * @package elasticpress
 */

use ElasticPress\Dashboard;
use ElasticPress\Elasticsearch;
use ElasticPress\IndexHelper;
use ElasticPress\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$index_meta = IndexHelper::factory()->get_index_meta();

$version = Elasticsearch::factory()->get_elasticsearch_version();

$host        = Utils\get_host();
$is_epio     = Utils\is_epio();
$credentials = Utils\get_epio_credentials();
$wpconfig    = defined( 'EP_HOST' ) && EP_HOST;

$bulk_setting = Utils\get_option( 'ep_bulk_setting', 350 );
?>

<?php require_once __DIR__ . '/header.php'; ?>

<div class="error-overlay <?php if ( ! empty( $index_meta ) ) : ?>syncing<?php endif; ?>"></div>
<div class="wrap">
	<h1><?php esc_html_e( 'Settings', 'elasticpress' ); ?></h1>

	<form action="" method="post" class="ep-settings">
		<?php wp_nonce_field( 'elasticpress_settings', 'ep_settings_nonce' ); ?>

		<div class="ep-credentials">
			<?php if ( ! $wpconfig ) : ?>
				<h2 class="nav-tab-wrapper ep-credentials-tabs">
					<button class="nav-tab ep-credentials-tab <?php if ( ! $host || $is_epio ) { ?>nav-tab-active initial<?php } ?>" data-epio type="button">
						<img src="<?php echo esc_url( plugins_url( '/images/logo-icon.svg', dirname( __DIR__ ) ) ); ?>" width="16" height="16" alt="ElasticPress.io" />
						<span>ElasticPress.io</span>
					</button>
					<button class="nav-tab ep-credentials-tab <?php if ( $host && ! $is_epio ) { ?>nav-tab-active initial<?php } ?>" type="button">
						<span>Third-Party/Self-Hosted</span>
					</button>
				</h2>
			<?php endif; ?>

			<fieldset class="<?php if ( $wpconfig ) { ?>predefined<?php } ?>">
				<?php if ( $is_epio || ! $wpconfig ) : ?>
					<p class="ep-legend ep-additional-fields <?php if ( $host && ! $is_epio ) { ?>hidden<?php } ?>" aria-hidden="<?php if ( $host && ! $is_epio ) { ?>true<?php } else { ?>false<?php } ?>">
						<a href="https://elasticpress.io/" target="_blank" rel="noreferrer noopener">ElasticPress.io</a> is a hosted Elasticsearch service built for ElasticPress, powered by <a href="https://10up.com/" target="_blank" rel="noreferrer noopener">10up</a>.
					</p>
				<?php endif; ?>
				<table class="form-table">
					<tbody>
						<tr class="ep-host-row">
							<th scope="row">
								<label for="ep_host">
									<?php if ( $is_epio ) : ?>
										<?php esc_html_e( 'ElasticPress.io Host URL', 'elasticpress' ); ?>
									<?php else : ?>
										<?php esc_html_e( 'Elasticsearch Host URL', 'elasticpress' ); ?>
									<?php endif; ?>
								</label>
							</th>
							<td>
								<?php
								/**
								 * Filter whether to show host field in admin UI or not
								 *
								 * @hook ep_admin_show_host
								 * @param  {boolean} $show True to show
								 * @return {boolean} New value
								 */
								$show_host = apply_filters( 'ep_admin_show_host', true );
								$disabled  = $wpconfig || ! $show_host;
								$value     = $show_host ? esc_url( $host ) : __( '••••••••••••••••', 'elasticpress' );
								?>
								<input <?php disabled( $disabled, true, true ); ?> placeholder="https://" type="text" value="<?php echo esc_attr( $value ); ?>" name="ep_host" id="ep_host">
								<?php if ( $show_host ) : ?>
									<?php if ( $wpconfig ) : ?>
										<p class="description ep-host-legend"><?php esc_html_e( 'Host already defined in wp-config.php.', 'elasticpress' ); ?></p>
									<?php elseif ( $is_epio ) : ?>
										<p class="description ep-host-legend"><?php esc_html_e( 'Plug in your ElasticPress.io server here.', 'elasticpress' ); ?></p>
									<?php else : ?>
										<p class="description ep-host-legend"><?php esc_html_e( 'Plug in your Elasticsearch server here.', 'elasticpress' ); ?></p>
									<?php endif; ?>
								<?php endif; ?>
							</td>
						</tr>
						<?php if ( $is_epio || ! $wpconfig ) : ?>

							<tr class="ep-additional-fields <?php if ( $host && ! $is_epio ) { ?>hidden<?php } ?>" aria-hidden="<?php if ( $host && ! $is_epio ) { ?>true<?php } else { ?>false<?php } ?>">
								<th scope="row">
									<label for="ep_username"><?php esc_html_e( 'Subscription ID', 'elasticpress' ); ?></label>
								</th>
								<td>
									<?php
									/**
									 * Filter whether to show epio credentials fields in admin UI or not
									 *
									 * @hook ep_admin_show_credentials
									 * @param  {boolean} $show True to show
									 * @return {boolean} New value
									 */
									if ( apply_filters( 'ep_admin_show_credentials', true ) ) :
										?>
										<input <?php if ( defined( 'EP_CREDENTIALS' ) && EP_CREDENTIALS ) : ?>disabled<?php endif; ?> type="text" value="<?php echo esc_attr( $credentials['username'] ); ?>" name="ep_credentials[username]" id="ep_username">
									<?php endif ?>
									<?php if ( defined( 'EP_CREDENTIALS' ) && EP_CREDENTIALS ) : ?>
										<p class="description"><?php esc_html_e( 'Your Subscription ID is set in wp-config.php', 'elasticpress' ); ?></p>
									<?php else : ?>
										<p class="description"><?php esc_html_e( 'Plug in your subscription ID (or subscription name) here.', 'elasticpress' ); ?></p>
									<?php endif; ?>
								</td>
							</tr>

							<tr class="ep-additional-fields <?php if ( $host && ! $is_epio ) { ?>hidden<?php } ?>" aria-hidden="<?php if ( $host && ! $is_epio ) { ?>true<?php } else { ?>false<?php } ?>">
								<th scope="row">
									<label for="ep_token"><?php esc_html_e( 'Subscription Token', 'elasticpress' ); ?></label>
								</th>
								<td>
									<?php
									/**
									 * Filter whether to show epio credentials fields in admin UI or not
									 *
									 * @hook ep_admin_show_credentials
									 * @param  {boolean} $show True to show
									 * @return {boolean} New value
									 */
									if ( apply_filters( 'ep_admin_show_credentials', true ) ) :
										?>
										<input <?php if ( defined( 'EP_CREDENTIALS' ) && EP_CREDENTIALS ) : ?>disabled<?php endif; ?> type="text" value="<?php echo esc_attr( $credentials['token'] ); ?>" name="ep_credentials[token]" id="ep_token">
									<?php endif ?>
									<?php if ( defined( 'EP_CREDENTIALS' ) && EP_CREDENTIALS ) : ?>
										<p class="description"><?php esc_html_e( 'Your Subscription Token is set in wp-config.php', 'elasticpress' ); ?></p>
									<?php else : ?>
										<p class="description"><?php esc_html_e( 'Plug in your subscription token here.', 'elasticpress' ); ?></p>
									<?php endif; ?>
								</td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>
			</fieldset>
		</div>

		<div class="ep-credentials-general">
			<table class="form-table">
				<tbody>
				<tr>
					<th scope="row">
						<label for="ep_language"><?php esc_html_e( 'Elasticsearch Language', 'elasticpress' ); ?></label>
					</th>
					<td>
						<?php
						$ep_language = Utils\get_language();

						wp_dropdown_languages(
							[
								'id'                       => 'ep_language',
								'name'                     => 'ep_language',
								'selected'                 => $ep_language,
								'languages'                => Dashboard\get_available_languages( 'locales' ),
								'show_option_site_default' => true,
								'explicit_option_en_us'    => true,
								'show_available_translations' => false,
							]
						);
						?>
						<p class="description"><?php esc_html_e( 'Default language for your Elasticsearch mapping.', 'elasticpress' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Elasticsearch Version', 'elasticpress' ); ?></label></th>
					<td>
						<?php if ( $is_epio ) : ?>
							<?php esc_html_e( 'ElasticPress.io Managed Platform' ); ?>
						<?php else : ?>
							<?php if ( ! empty( $version ) ) : ?>
								<?php echo esc_html( $version ); ?>
							<?php else : ?>
								&mdash;
							<?php endif; ?>
						<?php endif; ?>
					</td>
				</tr>
				<?php if ( ! empty( $host ) && ! has_filter( 'ep_index_posts_per_page' ) ) : ?>
					<tr>
						<th scope="row">
							<label for="ep_bulk_setting"><?php esc_html_e( 'Content Items per Index Cycle ', 'elasticpress' ); ?></label>
						</th>
						<td>
							<input type="text" name="ep_bulk_setting" id="ep_bulk_setting" value="<?php echo absint( $bulk_setting ); ?>">
						</td>
					</tr>
				<?php endif; ?>
				</tbody>
			</table>
		</div>

		<?php
		/**
		 * Fires after settings table is displayed for inserting custom settings.
		 *
		 * @hook ep_settings_custom
		 */
		do_action( 'ep_settings_custom' );
		?>

		<input type="submit" <?php if ( ! empty( $index_meta ) ) : ?>disabled<?php endif; ?> name="submit" id="submit" class="button button-primary" value="<?php esc_attr_e( 'Save Changes', 'elasticpress' ); ?>">
	</form>
</div>
