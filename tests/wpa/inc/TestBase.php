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
					'active'         => 0,
					'force_inactive' => 1,
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

		$actor->moveTo( 'wp-admin/post-new.php' );

		try {
			$actor->click( '.nux-dot-tip__disable' );
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
	protected function activatePlugin( $actor, $slug = 'elasticpress', $network = false ) {
		if ( $network ) {
			$actor->moveTo( '/wp-admin/network/plugins.php' );
		} else {
			$actor->moveTo( '/wp-admin/plugins.php' );
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
	protected function deactivatePlugin( $actor, $slug = 'elasticpress', $network = false ) {
		if ( $network ) {
			$actor->moveTo( '/wp-admin/network/plugins.php' );
		} else {
			$actor->moveTo( '/wp-admin/plugins.php' );
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
	 * @param \WPAcceptance\PHPUnit\Actor $actor The actor.
	 */
	protected function isElasticPressIo( $actor ) {
		$actor->moveTo( '/wp-admin/admin.php?page=elasticpress-settings' );
		$host = $actor->getElementAttribute( '#ep_host', 'value' );

		return strpos( $host, 'hosted-elasticpress.io' );
	}

	/**
	 * Create a user in the admin
	 *
	 * @param  array                       $data  User data
	 * @param  \WPAcceptance\PHPUnit\Actor $actor Current actor
	 */
	public function createUser( array $data, \WPAcceptance\PHPUnit\Actor $actor ) {
		$defaults = [
			'user_login' => 'testuser',
			'user_email' => 'testuser@example.com',
		];

		$data = array_merge( $defaults, $data );

		$actor->moveTo( 'wp-admin/user-new.php' );

		$actor->typeInField( '#user_login', $data['user_login'] );

		$actor->typeInField( '#email', $data['user_email'] );

		$actor->checkOptions( '#noconfirmation' );

		$actor->click( '#createusersub' );

		$actor->waitUntilElementVisible( '#message' );
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
		$actor->moveTo( 'wp-admin/admin.php?page=elasticpress-settings' );

		$per_page = $actor->getElementAttribute( '#ep_bulk_setting', 'value' );

		$actor->typeInField( '#ep_bulk_setting', (string) $number );

		$actor->click( '#submit' );

		return $per_page;
	}

	/**
	 * Activate a feature.
	 *
	 * @param string $feature_slug
	 * @param \WPAcceptance\PHPUnit\Actor $actor
	 */
	public function activateFeature( string $feature_slug, $actor ) {
		$actor->moveTo( '/wp-admin/admin.php?page=elasticpress' );

		$class = $actor->getElementAttribute( ".ep-feature-{$feature_slug}", 'class' );

		if ( strpos( $class, 'feature-active' ) === false ) {
			$actor->click( ".ep-feature-{$feature_slug} .settings-button" );

			$actor->click( "#feature_active_{$feature_slug}_enabled" );

			$actor->click( "a.save-settings[data-feature='{$feature_slug}']" );

			sleep( 2 );
		}
	}
}
