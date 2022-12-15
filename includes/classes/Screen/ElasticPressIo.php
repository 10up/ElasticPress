<?php
/**
 * ElasticPress.io Screen class
 *
 * @since 4.5.0
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
	public function setup() {
		add_action( 'admin_head', array( $this, 'admin_menu_notice' ), 11 );
	}

	/**
	 * Get messages from ElasticPress.io.
	 *
	 * @return array ElasticPress.io messages.
	 */
	protected function get_messages() {
		$transient = 'ep_elasticpress_io_messages';
		$messages  = get_transient( $transient );

		if ( false !== $messages ) {
			return $messages;
		}

		$response = \ElasticPress\Elasticsearch::factory()->remote_request( 'endpoint-messages' );
		$messages = (array) json_decode( wp_remote_retrieve_body( $response ), true );

		set_transient( $transient, $messages, DAY_IN_SECONDS );

		return $messages;
	}

	/**
	 * Render all user messages.
	 *
	 * @return void
	 */
	public function render_messages() {
		$messages = $this->get_messages();

		foreach ( $messages as $notice ) {
			?>
			<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> inline">
				<p><?php echo wp_kses( $notice['message'], 'ep-html' ); ?></p>
			</div>
			<?php
		}
	}

	/**
	 * Display a badge in the admin menu if there's admin notices from
	 * ElasticPress.io.
	 *
	 * @return void
	 */
	public function admin_menu_notice() {
		global $menu, $submenu;

		$messages = $this->get_messages();

		if ( empty( $messages ) ) {
			return;
		}

		$count = count( $messages );
		$title = sprintf(
			/* translators: %d: Number of messages. */
			_n( '%s message from ElasticPress.io', '%s messages from ElasticPress.io', $count, 'elasticpress' ),
			$count
		);

		foreach ( $menu as $key => $value ) {
			if ( 'elasticpress' === $value[2] ) {
				$menu[ $key ][0] .= sprintf(
					' <span class="update-plugins" title="%1$s">%2$s</span>',
					esc_attr( $title ),
					esc_html( $count )
				);
			}
		}

		foreach ( $submenu['elasticpress'] as $key => $value ) {
			if ( 'elasticpress-io' === $value[2] ) {
				$submenu['elasticpress'][ $key ][0] .= sprintf(
					' <span class="menu-counter" title="%1$s">%2$s</span>',
					esc_attr( $title ),
					esc_html( $count )
				);
			}
		}
	}
}
