<?php
/**
 * Basic test class
 *
 * @package elasticpress
 */

use \WPAcceptance\PHPUnit\Database;

/**
 * PHPUnit test class
 */
class TestBase extends \WPAcceptance\PHPUnit\TestCase {

	/**
	 * ElasticPress indexes
	 *
	 * @var array
	 */
	protected $indexes = [];

	/**
	 * Setup functionality
	 */
	public function setUp() {
		static $initialized = false;

		parent::setUp();

		if ( ! $initialized ) {
			$initialized = true;

			$this->indexes = json_decode( $this->runCommand( 'wp elasticpress get-indexes' )['stdout'], true );

			/**
			 * Set default feature settings
			 */
			$feature_settings = [
				'search'            => [
					'active'            => 1,
					'highlight_enabled' => true,
					'highlight_excerpt' => true,
					'highlight_tag'     => 'mark',
					'highlight_color'   => '#157d84',
				],
				'related_posts'     => [
					'active' => 1,
				],
				'facets'            => [
					'active' => 1,
				],
				'searchordering'    => [
					'active' => 1,
				],
				'autosuggest'       => [
					'active' => 1,
				],
				'woocommerce'       => [
					'active' => 0,
				],
				'protected_content' => [
					'active' => 0,
				],
				'users'             => [
					'active' => 1,
				],
			];

			// If not using EP.io, set the autosuggest endpoint.
			$ep_host = $this->runCommand( "wp eval 'echo \ElasticPress\Utils\get_host();'" )['stdout'];
			if ( ! preg_match( '#elasticpress\.io#i', $ep_host ) ) {
				$ep_host = rtrim( $ep_host, '/\\' );
				$ep_host = str_replace( 'host.docker.internal', '127.0.0.1', $ep_host );

				$post_index = '';
				foreach ( $this->indexes as $index ) {
					if ( false !== strpos( $index, '-post' ) ) {
						$post_index = $index;
					}
				}

				$feature_settings['autosuggest']['endpoint_url'] = "{$ep_host}/{$post_index}/_search";
			}

			$this->updateFeatureSettings( $feature_settings );

			/**
			 * Set default weighting
			 */
			$weighting = [
				'post' => [
					'post_title'   => [
						'weight'  => 1,
						'enabled' => true,
					],
					'post_content' => [
						'weight'  => 1,
						'enabled' => true,
					],
					'post_excerpt' => [
						'weight'  => 1,
						'enabled' => true,
					],

					'author_name'  => [
						'weight'  => 0,
						'enabled' => false,
					],
				],
				'page' => [
					'post_title'   => [
						'weight'  => 1,
						'enabled' => true,
					],
					'post_content' => [
						'weight'  => 1,
						'enabled' => true,
					],
					'post_excerpt' => [
						'weight'  => 1,
						'enabled' => true,
					],

					'author_name'  => [
						'weight'  => 0,
						'enabled' => false,
					],
				],
			];

			$this->updateWeighting( $weighting );
		}
	}

	/**
	 * Update EP weighting
	 *
	 * @param  array $weighting Weighting to set
	 */
	public function updateWeighting( $weighting ) {
		$this->updateRowsWhere(
			[
				'option_value' => $weighting,
			],
			[
				'option_name' => 'elasticpress_weighting',
			],
			'options'
		);
	}

	/**
	 * Update feature settings
	 *
	 * @param  array $feature_settings Feature settings
	 */
	public function updateFeatureSettings( $feature_settings ) {
		$current_settings_row = $this->selectRowsWhere( [ 'option_name' => 'ep_feature_settings' ], 'options' );

		if ( empty( $current_settings_row ) ) {
			$current_settings = [];
		} else {
			$current_settings = unserialize( $current_settings_row['option_value'] );
		}

		foreach ( $feature_settings as $feature => $settings ) {
			if ( ! empty( $current_settings[ $feature ] ) ) {
				$feature_settings[ $feature ] = array_merge( $current_settings[ $feature ], $settings );
			}
		}

		$this->updateRowsWhere(
			[
				'option_value' => $feature_settings,
			],
			[
				'option_id' => $current_settings_row['option_id'],
			],
			'options'
		);
	}

	/**
	 * Publish a post in the admin
	 *
	 * @param  array                       $data  Post data
	 * @param  \WPAcceptance\PHPUnit\Actor $actor Current actor
	 */
	public function publishPost( array $data, \WPAcceptance\PHPUnit\Actor $actor ) {
		$defaults = [
			'title'   => 'Test Post',
			'content' => 'Test content.',
		];

		$data = array_merge( $defaults, $data );

		$this->moveTo( $actor, 'wp-admin/post-new.php' );

		try {
			$actor->click( '.edit-post-welcome-guide .components-modal__header button' );
		} catch ( \Exception $e ) {
			// Do nothing
		}

		$actor->typeInField( '#post-title-0', $data['title'] );

		$actor->getPage()->type(
			'.block-editor-default-block-appender__content',
			$data['content'],
			[ 'delay' => 10 ]
		);

		usleep( 100 );

		// Post Status.
		if ( isset( $data['status'] ) && 'draft' === $data['status'] ) {

			$actor->click( '.editor-post-save-draft' );

			$actor->waitUntilElementContainsText( 'Saved', '.editor-post-saved-state' );

		} else {

			$actor->waitUntilElementVisible( '.editor-post-publish-panel__toggle' );

			$actor->waitUntilElementEnabled( '.editor-post-publish-panel__toggle' );

			$actor->click( '.editor-post-publish-panel__toggle' );

			// Some time we can't click the publish button using this method $actor->click( '.editor-post-publish-button' );
			$actor->executeJavaScript( 'document.querySelector( ".editor-post-publish-button" ).click();' );

			// $actor->waitUntilElementEnabled( '.editor-post-publish-button' );

			// $actor->click( '.editor-post-publish-button' );

			$actor->waitUntilElementVisible( '.components-snackbar' );
		}
	}

	/**
	 * Activate the plugin.
	 *
	 * @param \WPAcceptance\PHPUnit\Actor $actor   The actor.
	 * @param string                      $slug    Plugin slug.
	 * @param bool                        $network Multisite?
	 */
	protected function activatePlugin( $actor = null, $slug = 'elasticpress', $network = false ) {
		if ( ! $actor ) {
			$command = "wp plugin activate {$slug}";
			if ( $network ) {
				$command .= ' --network';
			}
			$this->runCommand( $command );
			return;
		}

		if ( $network ) {
			$this->moveTo( $actor, '/wp-admin/network/plugins.php' );
		} else {
			$this->moveTo( $actor, '/wp-admin/plugins.php' );
		}

		try {
			$element = $actor->getElement( '[data-slug="' . $slug . '"] .activate a' );
			if ( $element ) {
				$actor->click( $element );
				$actor->waitUntilElementVisible( '#message' );
			}
		} catch ( \Exception $e ) {}
	}

	/**
	 * Deactivate the plugin.
	 *
	 * @param \WPAcceptance\PHPUnit\Actor $actor The actor.
	 * @param string                      $slug  Plugin slug.
	 * @param bool                        $network Multisite?
	 */
	protected function deactivatePlugin( $actor = null, $slug = 'elasticpress', $network = false ) {
		if ( ! $actor ) {
			$command = "wp plugin deactivate {$slug}";
			if ( $network ) {
				$command .= ' --network';
			}
			$this->runCommand( $command );
			return;
		}

		if ( $network ) {
			$this->moveTo( $actor, '/wp-admin/network/plugins.php' );
		} else {
			$this->moveTo( $actor, '/wp-admin/plugins.php' );
		}

		try {
			$element = $actor->getElement( '[data-slug="' . $slug . '"] .deactivate a' );
			if ( $element ) {
				$actor->click( $element );
				$actor->waitUntilElementVisible( '#message' );
			}
		} catch ( \Exception $e ) {}
	}

	/**
	 * Check if we're using ElasticPress.io.
	 *
	 * @return boolean
	 */
	protected function isElasticPressIo() {
		$ep_host = $this->runCommand( "wp eval 'echo \ElasticPress\Utils\get_host();'" )['stdout'];
		return preg_match( '#elasticpress\.io#i', $ep_host );
	}

	/**
	 * Helper function to check for total entries found in Debug Bar.
	 *
	 * @param integer $total
	 * @param \WPAcceptance\PHPUnit\Actor $actor
	 */
	public function checkTotal( int $total, \WPAcceptance\PHPUnit\Actor $actor ) {
		// Different ES versions will return it in different ways.
		try {
			$actor->seeText( '"total": ' . $total, '.query-results' );
		} catch ( \Exception $e ) {
			$actor->seeText( '"value": ' . $total, '.query-results' );
		}
	}

	/**
	 * Set the number of entries per cycle.
	 *
	 * @param integer $number
	 * @param \WPAcceptance\PHPUnit\Actor $actor
	 * @return string
	 */
	public function setPerIndexCycle( int $number, \WPAcceptance\PHPUnit\Actor $actor ) {
		$this->moveTo( $actor, 'wp-admin/admin.php?page=elasticpress-settings' );

		$per_page = $actor->getElementAttribute( '#ep_bulk_setting', 'value' );

		$actor->typeInField( '#ep_bulk_setting', (string) $number );

		$actor->click( '#submit' );

		return $per_page;
	}

	/**
	 * Make sure a feature is enable before running tests that rely on it.
	 *
	 * @param string $feature Feature slug.
	 */
	public function maybeEnableFeature( $feature ) {
		$cli_result = $this->runCommand( 'wp elasticpress list-features' )['stdout'];
		if ( false === strpos( $cli_result, $feature ) ) {
			$this->runCommand( "wp elasticpress activate-feature {$feature}" );
		}
	}

	/**
	 * Open the Widgets Page and try to open the modal introduced in WP 5.8.
	 *
	 * @param \WPAcceptance\PHPUnit\Actor $actor
	 */
	public function openWidgetsPage( $actor ) {
		$this->moveTo( $actor, '/wp-admin/widgets.php' );

		try {
			$actor->click( '.edit-widgets-welcome-guide .components-modal__header button' );
		} catch ( \Exception $e ) {
			// Do nothing
		}
	}

	/**
	 * Open a page in the browser.
	 *
	 * This method will keep trying to move to the new page until a success
	 * or any error other than "Page crashed"
	 *
	 * @param Actor $actor The actor
	 * @param array ...$args  Arguments to be passed to Actor::moveTo()
	 */
	public function moveTo( $actor, ...$args ) {
		$attempts = 0;
		do {
			try {
				$attempts++;
				$continue_trying = false;
				$actor->moveTo( ...$args );
			} catch ( \Throwable $th ) {
				// If failed due to Page crashed, let's try again. Otherwise, stop.
				if ( false !== strpos( $th->getMessage(), 'Page crashed' ) ) {
					\WPAcceptance\Log::instance()->write( 'Page crashed error. Retrying (' . $attempts . ')', 0 );
					$continue_trying = true;
					sleep( 5 );
				}
			}
		} while ( $continue_trying && $attempts < 10 );
	}
}
