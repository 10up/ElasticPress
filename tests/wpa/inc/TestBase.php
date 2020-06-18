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
			$this->updateFeatureSettings(
				[
					'search'            => [
						'active' => 1,
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
				]
			);

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
			'.editor-default-block-appender__content',
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

			$actor->waitUntilElementEnabled( '.editor-post-publish-button' );

			$actor->click( '.editor-post-publish-button' );

			$actor->waitUntilElementVisible( '.components-notice' );
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
}
