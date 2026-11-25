<?php
/**
 * Test the ElasticPressIoTemplateManager trait.
 *
 * @since 5.3.6
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress\ElasticPressIoTemplateManager;

/**
 * ElasticPressIoTemplateManager test class.
 */
class TestElasticPressIoTemplateManager extends BaseTestCase {

	/**
	 * Helper to get a mock instance using the trait.
	 *
	 * @param string $slug Feature slug.
	 * @return object
	 */
	protected function get_mock_manager( $slug = 'test-feature-slug' ) {
		return new class( $slug ) {
			use ElasticPressIoTemplateManager;

			/**
			 * Template endpoint.
			 *
			 * @var string
			 */
			public $endpoint = '_scripts/test-template';

			/**
			 * Template source.
			 *
			 * @var string
			 */
			public $template = '{"script":{"lang":"mustache","source":"{}"}}';

			/**
			 * Feature slug.
			 *
			 * @var string
			 */
			public $slug;

			/**
			 * Constructor.
			 *
			 * @param string $slug Feature slug.
			 */
			public function __construct( $slug ) {
				$this->slug = $slug;
			}

			/**
			 * Get template endpoint.
			 *
			 * @return string
			 */
			public function get_template_endpoint(): string {
				return $this->endpoint;
			}

			/**
			 * Get search template.
			 *
			 * @return string
			 */
			public function get_search_template(): string {
				return $this->template;
			}

			/**
			 * Get feature slug.
			 *
			 * @return string
			 */
			public function get_feature_slug(): string {
				return $this->slug;
			}
		};
	}

	/**
	 * Test get_hook_prefix replaces hyphens with underscores.
	 */
	public function test_get_hook_prefix() {
		$manager = $this->get_mock_manager( 'test-feature-slug' );
		$this->assertSame( 'ep_test_feature_slug', $manager->get_hook_prefix() );

		$manager2 = $this->get_mock_manager( 'woocommerce-orders-autosuggest' );
		$this->assertSame( 'ep_woocommerce_orders_autosuggest', $manager2->get_hook_prefix() );
	}

	/**
	 * Test epio_save_search_template fires action with correct template argument.
	 */
	public function test_epio_save_search_template() {
		$manager = $this->get_mock_manager();

		$fired           = false;
		$action_template = '';

		add_action(
			'ep_test_feature_slug_template_saved',
			function ( $template ) use ( &$fired, &$action_template ) {
				$fired           = true;
				$action_template = $template;
			},
			10,
			1
		);

		$manager->epio_save_search_template();

		$this->assertTrue( $fired );
		$this->assertSame( '{"script":{"lang":"mustache","source":"{}"}}', $action_template );
	}

	/**
	 * Test epio_delete_search_template fires action.
	 */
	public function test_epio_delete_search_template() {
		$manager = $this->get_mock_manager();

		$fired = false;

		add_action(
			'ep_test_feature_slug_template_deleted',
			function () use ( &$fired ) {
				$fired = true;
			}
		);

		$manager->epio_delete_search_template();

		$this->assertTrue( $fired );
	}

	/**
	 * Test epio_get_search_template returns error or template body.
	 */
	public function test_epio_get_search_template() {
		$manager = $this->get_mock_manager();

		// Case 1: Remote error returns WP_Error.
		add_filter( 'ep_intercept_remote_request', '__return_true' );
		add_filter(
			'ep_do_intercept_request',
			function () {
				return new \WP_Error( 404, 'Template not found' );
			}
		);

		$result = $manager->epio_get_search_template();
		$this->assertWPError( $result );

		remove_all_filters( 'ep_do_intercept_request' );

		// Case 2: Success response.
		$template_body = '{"template":"body"}';
		add_filter(
			'ep_do_intercept_request',
			function () use ( $template_body ) {
				return [
					'response' => [ 'code' => 200 ],
					'body'     => $template_body,
				];
			}
		);

		$result_success = $manager->epio_get_search_template();
		$this->assertSame( $template_body, $result_success );

		remove_all_filters( 'ep_do_intercept_request' );
		remove_filter( 'ep_intercept_remote_request', '__return_true' );
	}

	/**
	 * Test after_update_feature triggers save on activation and delete on deactivation.
	 */
	public function test_after_update_feature() {
		$manager = $this->get_mock_manager();

		$saved_fired   = false;
		$deleted_fired = false;

		add_action(
			'ep_test_feature_slug_template_saved',
			function () use ( &$saved_fired ) {
				$saved_fired = true;
			}
		);

		add_action(
			'ep_test_feature_slug_template_deleted',
			function () use ( &$deleted_fired ) {
				$deleted_fired = true;
			}
		);

		// Different feature slug: should not trigger.
		$manager->after_update_feature( 'different-feature', [], [ 'active' => true ] );
		$this->assertFalse( $saved_fired );
		$this->assertFalse( $deleted_fired );

		// Matched slug, activated.
		$manager->after_update_feature( 'test-feature-slug', [], [ 'active' => true ] );
		$this->assertTrue( $saved_fired );
		$this->assertFalse( $deleted_fired );

		// Reset flags.
		$saved_fired = false;

		// Matched slug, deactivated.
		$manager->after_update_feature( 'test-feature-slug', [], [ 'active' => false ] );
		$this->assertFalse( $saved_fired );
		$this->assertTrue( $deleted_fired );
	}
}
