<?php
/**
 * Index Helper
 *
 * @since  3.6.0
 * @package elasticpress
 */

namespace ElasticPress;

use ElasticPress\Utils as Utils;

/**
 * Index Helper Class.
 *
 * @since 3.6.0
 */
class IndexHelper {
	/**
	 * Array to hold all the index sync information.
	 *
	 * @since 3.6.0
	 * @var array|bool
	 */
	protected $index_meta = false;

	/**
	 * Arguments to be used during the index process.
	 *
	 * @var array
	 */
	protected $args = [];

	/**
	 * Initialize class
	 *
	 * @since 3.6.0
	 */
	public function setup() {
		$this->index_meta = Utils\get_indexing_status();
	}

	/**
	 * Method to index everything.
	 *
	 * @param array $args Arguments.
	 */
	public function full_index( $args ) {
		$this->index_meta = Utils\get_indexing_status();
		$this->args       = $args;

		if ( false === $this->index_meta ) {
			$this->build_index_meta();
		}

		while ( $this->has_items_to_be_processed() ) {
			$this->process_sync_item();
		}

		$this->output(
			[
				'message' => esc_html__( 'Done.', 'elasticpress' ),
				'status'  => 'success',
			]
		);
	}

	/**
	 * Method to stack everything that needs to be indexed.
	 */
	protected function build_index_meta() {
		Utils\update_option( 'ep_last_sync', time() );
		Utils\delete_option( 'ep_need_upgrade_sync' );
		Utils\delete_option( 'ep_feature_auto_activated_sync' );

		$this->index_meta = [
			'offset'     => 0,
			'start'      => true,
			'sync_stack' => [],
		];

		$global_indexables     = Indexables::factory()->get_all( true, true );
		$non_global_indexables = Indexables::factory()->get_all( false, true );

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$sites = Utils\get_sites();

			foreach ( $sites as $site ) {
				if ( ! Utils\is_site_indexable( $site['blog_id'] ) ) {
					continue;
				}

				foreach ( $non_global_indexables as $indexable ) {
					$this->index_meta['sync_stack'][] = [
						'url'         => untrailingslashit( $site['domain'] . $site['path'] ),
						'blog_id'     => (int) $site['blog_id'],
						'indexable'   => $indexable,
						'put_mapping' => true,
					];
				}
			}
		} else {
			foreach ( $non_global_indexables as $indexable ) {
				$this->index_meta['sync_stack'][] = [
					'url'         => untrailingslashit( home_url() ),
					'blog_id'     => (int) get_current_blog_id(),
					'indexable'   => $indexable,
					'put_mapping' => true,
				];
			}
		}

		foreach ( $global_indexables as $indexable ) {
			$this->index_meta['sync_stack'][] = [
				'indexable'   => $indexable,
				'put_mapping' => true,
			];
		}
	}

	/**
	 * Check if there are still items to be processed in the stack.
	 *
	 * @return boolean
	 */
	protected function has_items_to_be_processed() {
		return ! empty( $this->index_meta['current_sync_item'] ) || count( $this->index_meta['sync_stack'] ) > 0;
	}

	/**
	 * Method to process the next item in the stack.
	 */
	protected function process_sync_item() {
		if ( ! $this->index_meta['current_sync_item'] ) {
			$this->index_meta['current_sync_item'] = array_shift( $this->index_meta['sync_stack'] );

			$indexable = Indexables::factory()->get( $this->index_meta['current_sync_item']['indexable'] );

			if ( ! empty( $this->index_meta['current_sync_item']['blog_id'] ) ) {
				$this->output(
					[
						'message' => sprintf(
							/* translators: 1: Indexable name, 2: Site ID */
							esc_html__( 'Indexing %1$s on site %2$d...', 'elasticpress' ),
							esc_html( strtolower( $indexable->labels['plural'] ) ),
							$this->index_meta['current_sync_item']['blog_id']
						),
						'status'  => 'success',
					]
				);
			} else {
				$this->output(
					[
						'message' => sprintf(
							/* translators: 1: Indexable name */
							esc_html__( 'Indexing %1$s (globally)...', 'elasticpress' ),
							esc_html( strtolower( $indexable->labels['plural'] ) )
						),
						'status'  => 'success',
					]
				);
			}
		}

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK && ! empty( $this->index_meta['current_sync_item']['blog_id'] ) ) {
			switch_to_blog( $this->index_meta['current_sync_item']['blog_id'] );
		}

		if ( $this->index_meta['current_sync_item']['put_mapping'] ) {
			$this->put_mapping();
		}

		$this->index_objects();

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK && ! empty( $this->index_meta['current_sync_item']['blog_id'] ) ) {
			restore_current_blog();
		}
	}

	/**
	 * Delete an index and recreate it sending the mapping.
	 */
	protected function put_mapping() {
		$indexable = Indexables::factory()->get( $this->index_meta['current_sync_item']['indexable'] );

		$indexable->delete_index();
		$result = $indexable->put_mapping();

		$this->index_meta['current_sync_item']['put_mapping'] = false;

		if ( $result ) {
			$this->output(
				[
					'message' => esc_html__( 'Mapping sent', 'elasticpress' ),
					'status'  => 'success',
				]
			);
		} else {
			$this->output(
				[
					'message' => esc_html__( 'Mapping failed', 'elasticpress' ),
					'status'  => 'error',
				]
			);
		}
	}

	/**
	 * Index documents of an index.
	 */
	protected function index_objects() {
		$this->index_meta['current_sync_item'] = null;

		$this->output(
			[
				'message' => esc_html__( 'Objects indexed', 'elasticpress' ),
				'status'  => 'success',
			]
		);
	}

	/**
	 * Output a message
	 *
	 * @param array $message Message to be outputted with its status and additional info, if needed.
	 * @return void
	 */
	protected function output( $message ) {
		Utils\update_option( 'ep_index_meta', $this->index_meta );

		if ( is_callable( $this->args['output_method'] ) ) {
			call_user_func( $this->args['output_method'], $message );
		}
	}

	/**
	 * Return singleton instance of class
	 *
	 * @return self
	 * @since 3.6.0
	 */
	public static function factory() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
			$instance->setup();
		}

		return $instance;
	}
}
