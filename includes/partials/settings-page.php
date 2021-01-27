<?php
/**
 * Template for ElasticPress settings page
 *
 * @since  2.1
 * @package elasticpress
 */

use ElasticPress\Utils as Utils;
use ElasticPress\Elasticsearch as Elasticsearch;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$action = 'options.php';

if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
	$index_meta = get_site_option( 'ep_index_meta', false );
	$action     = '';
} else {
	$index_meta = get_option( 'ep_index_meta', false );
}

$version = Elasticsearch::factory()->get_elasticsearch_version();

$host        = Utils\get_host();
$is_epio     = Utils\is_epio();
$credentials = Utils\get_epio_credentials();
$wpconfig    = defined( 'EP_HOST' ) && EP_HOST;

if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
	$bulk_setting = get_site_option( 'ep_bulk_setting', 350 );
} else {
	$bulk_setting = get_option( 'ep_bulk_setting', 350 );
}
?>

<?php require_once __DIR__ . '/header.php'; ?>

<div class="error-overlay <?php if ( ! empty( $index_meta ) ) : ?>syncing<?php endif; ?>"></div>
<div class="wrap">
	<h1><?php esc_html_e( 'Settings', 'elasticpress' ); ?></h1>

	<form action="<?php echo esc_attr( $action ); ?>" method="post" class="ep-settings">
		<?php settings_fields( 'elasticpress' ); ?>
		<?php settings_errors(); ?>

		<div class="ep-credentials">
			<?php if ( ! $wpconfig ) : ?>
				<h2 class="nav-tab-wrapper ep-credentials-tabs">
					<button class="nav-tab ep-credentials-tab <?php if ( ! $host || $is_epio ) { ?>nav-tab-active initial<?php } ?>" data-epio>
						<img src="<?php echo esc_url( plugins_url( '/images/logo-icon.svg', dirname( __DIR__ ) ) ); ?>" width="16" height="16" alt="ElasticPress.io" />
						<span>ElasticPress.io</span>
					</button>
					<button class="nav-tab ep-credentials-tab <?php if ( $host && ! $is_epio ) { ?>nav-tab-active initial<?php } ?>">
						<span>Third-Party/Self-Hosted</span>
					</button>
				</h2>
			<?php endif; ?>

			<fieldset class="<?php if ( $wpconfig ) { ?>predefined<?php } ?>">
				<?php if ( $is_epio || ! $wpconfig ) : ?>
					<p class="ep-legend ep-additional-fields <?php if ( $host && ! $is_epio ) { ?>hidden<?php } ?>" aria-hidden="<?php if ( $host && ! $is_epio ) { ?>true<?php } else { ?>false<?php } ?>">
						<a href="http://elasticpress.io/" target="_blank" rel="noreferrer noopener">ElasticPress.io</a> is a hosted Elasticsearch service built for ElasticPress, powered by <a href="https://10up.com/" target="_blank" rel="noreferrer noopener">10up</a>.
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
								if ( apply_filters( 'ep_admin_show_host', true ) ) :
									?>
									<input <?php if ( $wpconfig ) { ?>disabled<?php } ?> placeholder="http://" type="text" value="<?php echo esc_url( $host ); ?>" name="ep_host" id="ep_host">
								<?php endif ?>
								<?php if ( $wpconfig ) : ?>
									<legend class="description ep-host-legend"><?php esc_html_e( 'Host already defined in wp-config.php.', 'elasticpress' ); ?></legend>
								<?php else : ?>
									<?php if ( $is_epio ) : ?>
										<legend class="description ep-host-legend"><?php esc_html_e( 'Plug in your ElasticPress.io server here!', 'elasticpress' ); ?></legend>
									<?php else : ?>
										<legend class="description ep-host-legend"><?php esc_html_e( 'Plug in your Elasticsearch server here!', 'elasticpress' ); ?></legend>
									<?php endif; ?>
								<?php endif; ?>
							</td>
						</tr>
						<?php if ( $is_epio || ! $wpconfig ) : ?>
							<tr class="ep-additional-fields <?php if ( $host && ! $is_epio ) { ?>hidden<?php } ?>" aria-hidden="<?php if ( $host && ! $is_epio ) { ?>true<?php } else { ?>false<?php } ?>">
								<th scope="row">
									<label for="ep_prefix"><?php esc_html_e( 'Subscription ID', 'elasticpress' ); ?></label>
								</th>
								<td>
									<?php
									/**
									 * Filter whether to show index prefix field in admin UI or not
									 *
									 * @hook ep_admin_index_prefix
									 * @param  {boolean} $show True to show
									 * @return {boolean} New value
									 */
									if ( apply_filters( 'ep_admin_show_index_prefix', true ) ) :
										?>
										<input <?php if ( defined( 'EP_INDEX_PREFIX' ) && EP_INDEX_PREFIX ) : ?>disabled<?php endif; ?> type="text" value="<?php echo esc_attr( rtrim( Utils\get_index_prefix(), '-' ) ); ?>" name="ep_prefix" id="ep_prefix">
									<?php endif ?>
									<?php if ( defined( 'EP_INDEX_PREFIX' ) && EP_INDEX_PREFIX ) : ?>
										<legend class="description"><?php esc_html_e( 'Your Subscription ID is set in wp-config.php', 'elasticpress' ); ?></legend>
									<?php else : ?>
										<legend class="description"><?php esc_html_e( 'Plug in your Subscription ID here.', 'elasticpress' ); ?></legend>
									<?php endif; ?>
								</td>
							</tr>

							<tr class="ep-additional-fields <?php if ( $host && ! $is_epio ) { ?>hidden<?php } ?>" aria-hidden="<?php if ( $host && ! $is_epio ) { ?>true<?php } else { ?>false<?php } ?>">
								<th scope="row">
									<label for="ep_username"><?php esc_html_e( 'Subscription Username', 'elasticpress' ); ?></label>
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
										<legend class="description"><?php esc_html_e( 'Your Subscription Username is set in wp-config.php', 'elasticpress' ); ?></legend>
									<?php else : ?>
										<legend class="description"><?php esc_html_e( 'Plug in your subscription username here.', 'elasticpress' ); ?></legend>
									<?php endif; ?>
								</td>
							</tr>

							<tr class="ep-additional-fields <?php if ( $host && ! $is_epio ) { ?>hidden<?php } ?>" aria-hidden="<?php if ( $host && ! $is_epio ) { ?>true<?php } else { ?>false<?php } ?>">
								<th scope="row">
									<label for="ep_token"><?php esc_html_e( 'Subscription Token', 'elasticpress' ); ?></label></th>
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
										<legend class="description"><?php esc_html_e( 'Your Subscription Token is set in wp-config.php', 'elasticpress' ); ?></legend>
									<?php else : ?>
										<legend class="description"><?php esc_html_e( 'Plug in your subscription token here.', 'elasticpress' ); ?></legend>
									<?php endif; ?>
								</td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>
			</fieldset>
		</div>

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
							'id'       => 'ep_language',
							'name'     => 'ep_language',
							'selected' => $ep_language,
						]
					);
					?>
					<legend class="description"><?php esc_html_e( 'Default language for your Elasticsearch mapping.', 'elasticpress' ); ?></legend>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="ep_host"><?php esc_html_e( 'Elasticsearch Version', 'elasticpress' ); ?></label></th>
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

		<?php
		/**
		 * Fires after settings table is displayed for inserting custom settings.
		 *
		 * @hook ep_settings_custom
		 */
		do_action( 'ep_settings_custom' );
		?>

		<input type="submit" <?php if ( ! empty( $index_meta ) ) : ?>disabled<?php endif; ?> name="submit" id="submit" class="button button-primary" value="<?php esc_html_e( 'Save Changes', 'elasticpress' ); ?>">
	</form>
</div>
