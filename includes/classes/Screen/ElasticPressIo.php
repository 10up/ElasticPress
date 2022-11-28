<?php
/**
 * ElasticPress.io Screen class
 *
 * @since x.x
 * @package elasticpress
 */

namespace ElasticPress\Screen;

defined( 'ABSPATH' ) || exit;

/**
 * ElasticPress.io Screen class
 *
 * @package ElasticPress
 */
class ElasticPressIo {
	/**
	 * Initialize class
	 */
	public function setup() {}

	/**
	 * Render all user messages
	 */
	public function render_messages() {
		$messages_request = \ElasticPress\Elasticsearch::factory()->remote_request( 'endpoint-messages' );

		$messages = (array) json_decode( wp_remote_retrieve_body( $messages_request ), true );

		foreach ( $messages as $notice ) {
			?>
			<p class="notice notice-<?php echo esc_attr( $notice['type'] ); ?>">
				<?php echo wp_kses( $notice['message'], 'ep-html' ); ?>
			</p>
			<?php
		}
	}
}
