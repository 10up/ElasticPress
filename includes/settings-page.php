<?php
/**
 * Template for ElasticPress settings page
 *
 * @since  2.1
 * @package elasticpress
 */

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

$ep_host = ep_get_host();
?>

<?php require_once( dirname( __FILE__ ) . '/header.php' ); ?>

<div class="error-overlay <?php if ( ! empty( $index_meta ) ) : ?>syncing<?php endif; ?>"></div>
<div class="wrap">
	<h1><?php esc_html_e( 'Settings', 'elasticpress' ); ?></h1>

	<form action="<?php echo esc_attr( $action ); ?>" method="post">
		<?php settings_fields( 'elasticpress' ); ?>
		<?php settings_errors(); ?>

		<table class="form-table">
			<tbody>
			<tr>
				<th scope="row">
					<label for="ep_host"><?php esc_html_e( 'Elasticsearch Host', 'elasticpress' ); ?></label>
				</th>
				<td>
					<?php if ( apply_filters( 'ep_admin_show_host', true ) ) : ?>
						<input <?php if ( defined( 'EP_HOST' ) && EP_HOST ) : ?>disabled<?php endif; ?> placeholder="http://" type="text" value="<?php echo esc_url( $ep_host ); ?>" name="ep_host" id="ep_host">
					<?php endif ?>
					<?php if ( defined( 'EP_HOST' ) && EP_HOST ) : ?>
						<span class="description"><?php esc_html_e( 'Your Elasticsearch host is set in wp-config.php', 'elasticpress' ); ?></span>
					<?php else : ?>
						<span class="description"><?php esc_html_e( 'Plug in your Elasticsearch server here!', 'elasticpress' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="ep_host"><?php esc_html_e( 'Elasticsearch Version', 'elasticpress' ); ?></label></th>
				<td>
					<?php if ( $version = ep_get_elasticsearch_version() ) : ?>
						<span class="description"><?php echo esc_html( $version ); ?></span>
					<?php else : ?>
						<span class="description">&mdash;</span>
					<?php endif; ?>
				</td>
			</tr>
			<?php
			if ( ep_is_epio() && current_user_can( 'manage_options' ) ) {
				$credentials = ep_get_epio_credentials();
				?>
				<tr>
					<th scope="row">
						<label for="ep_prefix"><?php esc_html_e( 'Subscription ID', 'elasticpress' ); ?></label>
					</th>
					<td>
						<?php if ( apply_filters( 'ep_admin_show_index_prefix', true ) ) : ?>
							<input <?php if ( EP_Config::$option_prefix ) : ?>disabled<?php endif; ?> type="text" value="<?php echo esc_attr( rtrim( ep_get_index_prefix(), '-' ) ); ?>" name="ep_prefix" id="ep_prefix">
						<?php endif ?>
						<?php if ( EP_Config::$option_prefix ) : ?>
							<span class="description"><?php esc_html_e( 'Your Subscription ID is set in wp-config.php', 'elasticpress' ); ?></span>
						<?php else : ?>
							<span class="description"><?php esc_html_e( 'Plug in your Subscription ID here.', 'elasticpress' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="ep_username"><?php esc_html_e( 'Subscription Username', 'elasticpress' ); ?></label>
					</th>
					<td>
						<input type="text" value="<?php echo esc_attr( $credentials['username'] ); ?>" name="ep_credentials[username]" id="ep_username">
						<span class="description"><?php esc_html_e( 'Plug in your subscription username here.', 'elasticpress' ); ?></span>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="ep_token"><?php esc_html_e( 'Subscription Token', 'elasticpress' ); ?></label></th>
					<td>
						<input type="text" value="<?php echo esc_attr( $credentials['token'] ); ?>" name="ep_credentials[token]" id="ep_token">
						<span class="description"><?php esc_html_e( 'Plug in your subscription token here.', 'elasticpress' ); ?></span>
					</td>
				</tr>
			<?php }
			if ( ! empty( $ep_host ) && ! has_filter( 'ep_index_posts_per_page' ) ) {
				?>
			<th scope="row">
				<label for="ep_bulk_setting"><?php esc_html_e( 'Post index per cycle ', 'elasticpress' ); ?></label>
			</th>
				<td>
					<input type="text" name="ep_bulk_setting" id="ep_bulk_setting" value="<?php echo esc_html( ep_get_bulk_settings() ); ?>">
				</td>
				<?php
			}
			?>
			</tbody>
		</table>

		<input type="submit" <?php if ( ! empty( $index_meta ) ) : ?>disabled<?php endif; ?> name="submit" id="submit" class="button button-primary" value="<?php esc_html_e( 'Save Changes', 'elasticpress' ); ?>">
	</form>
</div>
