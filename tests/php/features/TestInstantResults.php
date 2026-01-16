<?php
/**
 * Test the Instants Results feature.
 *
 * @since   5.0.0
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress\Indexables;
/**
 * Instants Results test class.
 */
class TestInstantResults extends BaseTestCase {

	/**
	 * Test the constructor.
	 *
	 * @group instant-results
	 * @since 5.3.0
	 */
	public function test_construct() {
		$feature = new \ElasticPress\Feature\InstantResults\InstantResults();
		$feature->set_i18n_strings();

		$this->assertSame( 'instant-results', $feature->slug );
		$this->assertSame( 'live-search', $feature->group );
		$this->assertSame( 'Instant Results', $feature->title );
	}

	/**
	 * Test the setup method
	 *
	 * @since 5.3.0
	 * @group instant-results
	 */
	public function test_setup() {
		$instant_results_feature = \ElasticPress\Features::factory()->get_registered_feature( 'instant-results' );
		$instant_results_feature->setup();

		$this->assertSame( 10, has_action( 'wp_enqueue_scripts', [ $instant_results_feature, 'enqueue_frontend_assets' ] ) );
	}

	/**
	 * Test Instants Results settings schema
	 *
	 * @group instant-results
	 */
	public function test_get_settings_schema() {
		$settings_schema = \ElasticPress\Features::factory()->get_registered_feature( 'instant-results' )->get_settings_schema();

		$settings_keys = wp_list_pluck( $settings_schema, 'key' );

		$this->assertSame(
			[ 'active', 'highlight_tag', 'facets', 'match_type', 'term_count', 'numbered_pagination', 'per_page', 'search_behavior' ],
			$settings_keys
		);
	}

	/**
	 * Test the requirements status.
	 *
	 * @group instant-results
	 * @since 5.3.0
	 */
	public function test_requirements_status() {
		$host_url = function () {
			return 'https://elasticpress.io';
		};
		add_filter( 'ep_host', $host_url );

		$status = \ElasticPress\Features::factory()->get_registered_feature( 'instant-results' )->requirements_status();
		// Check if status is one for the elasticpress.io
		$this->assertSame( 1, $status->get_code() );

		remove_filter( 'ep_host', $host_url );

		// check status for proxy.
		$proxy_status = function () {
			return true;
		};
		add_filter( 'ep_instant_results_available', $proxy_status );

		$status = \ElasticPress\Features::factory()->get_registered_feature( 'instant-results' )->requirements_status();

		$this->assertSame( 1, $status->get_code() );
		$this->assertSame( 'You are using a custom proxy. Make sure you implement all security measures needed.', $status->get_message()[0] );

		remove_filter( 'ep_instant_results_available', $proxy_status );

		// Check if Instant Results is not available.
		$status = \ElasticPress\Features::factory()->get_registered_feature( 'instant-results' )->requirements_status();
		$this->assertSame( 2, $status->get_code() );
		$this->assertSame( "To use this feature you need to be an <a href='https://elasticpress.io'>ElasticPress.io</a> customer or implement a <a href='https://github.com/10up/elasticpress-proxy'>custom proxy</a>.", $status->get_message()[0] );
	}

	/**
	 * Test the feature slug.
	 *
	 * @group instant-results
	 * @since 5.3.0
	 */
	public function test_feature_slug() {
		$feature = \ElasticPress\Features::factory()->get_registered_feature( 'instant-results' );
		$this->assertSame( 'instant-results', $feature->get_feature_slug() );
	}

	/**
	 * Test the hook prefix
	 *
	 * @group instant-results
	 * @since 5.3.0
	 */
	public function test_get_hook_prefix() {
		$feature = \ElasticPress\Features::factory()->get_registered_feature( 'instant-results' );
		$this->assertSame( 'ep_instant_results', $feature->get_hook_prefix() );
	}

	/**
	 * Test the template endpoint.
	 *
	 * @group instant-results
	 * @since 5.3.0
	 */
	public function test_template_endpoint() {
		$feature = \ElasticPress\Features::factory()->get_registered_feature( 'instant-results' );
		$index   = Indexables::factory()->get( 'post' )->get_index_name();
		$this->assertSame( "api/v1/search/posts/{$index}/template/", $feature->get_template_endpoint() );
	}

	/**
	 * Test the search template.
	 *
	 * @group instant-results
	 * @since 5.3.0
	 */
	public function test_search_template() {
		$feature  = \ElasticPress\Features::factory()->get_registered_feature( 'instant-results' );
		$template = $feature->get_search_template();

		$this->assertIsString( $template, );
		$this->assertNotFalse( json_decode( $template ) );
	}

	/**
	 * Test the epio_save_search_template method.
	 *
	 * @group instant-results
	 * @since 5.3.0
	 */
	public function test_epio_save_search_template() {
		$feature = \ElasticPress\Features::factory()->get_registered_feature( 'instant-results' );

		add_action(
			'ep_instant_results_template_saved',
			function () {
				$this->assertTrue( true );
			}
		);

		$feature->epio_save_search_template();

		$this->assertTrue( true );
	}

	/**
	 * Test the epio_delete_search_template method.
	 *
	 * @group instant-results
	 * @since 5.3.0
	 */
	public function test_epio_delete_search_template() {
		$feature = \ElasticPress\Features::factory()->get_registered_feature( 'instant-results' );

		$feature->epio_save_search_template();

		$template = $feature->epio_get_search_template();

		$this->assertIsString( $template );
		$this->assertNotFalse( json_decode( $template ) );

		// Assert that the template is deleted
		add_action(
			'ep_instant_results_template_deleted',
			function () {
				$this->assertTrue( true );
			}
		);

		$feature->epio_delete_search_template();

		$template = $feature->epio_get_search_template();

		$this->assertStringContainsString( 'no handler found for uri', $template );
	}

	/**
	 * Test the after_update_feature method.
	 *
	 * @group instant-results
	 * @since 5.3.0
	 */
	public function test_after_update_feature() {
		$feature = \ElasticPress\Features::factory()->get_registered_feature( 'instant-results' );

		add_action(
			'ep_instant_results_template_saved',
			function () {
				$this->assertTrue( true );
			}
		);
		$feature->after_update_feature( 'instant-results', [], [ 'active' => true ] );

		add_action(
			'ep_instant_results_template_deleted',
			function () {
				$this->assertTrue( true );
			}
		);
		$feature->after_update_feature( 'instant-results', [], [ 'active' => false ] );
	}
		/**
	 * Ensure invalid taxonomy values returned from ep_facet_include_taxonomies
	 * are skipped and logged via _doing_it_wrong().
	 *
	 * @group instant-results
	 * @since 5.3.2
	 */
	public function test_invalid_taxonomy_from_facet_filter_triggers_doing_it_wrong_and_is_skipped() {
		$this->setExpectedIncorrectUsage(
			\ElasticPress\Feature\InstantResults\InstantResults::class . '::get_facets'
		);

		add_filter( 'doing_it_wrong_trigger_error', '__return_false' );

		$calls = [];

		$listener = function ( $function_name, $message, $version ) use ( &$calls ) {
			$calls[] = [
				'function' => $function_name,
				'message'  => $message,
				'version'  => $version,
			];
		};

		add_action( 'doing_it_wrong_run', $listener, 10, 3 );

		$taxonomy_filter = function ( $taxonomies ) {
			$taxonomies['not-a-real-taxonomy'] = 'not-a-real-taxonomy';
			return $taxonomies;
		};

		add_filter( 'ep_facet_include_taxonomies', $taxonomy_filter, 1, 1 );

		$feature = \ElasticPress\Features::factory()->get_registered_feature( 'instant-results' );
		$facets  = $feature->get_facets();

		$this->assertArrayNotHasKey( 'tax-not-a-real-taxonomy', $facets );

		$this->assertNotEmpty( $calls );
		$this->assertSame( 'ElasticPress 5.3.2', $calls[0]['version'] );
		$this->assertSame(
			\ElasticPress\Feature\InstantResults\InstantResults::class . '::get_facets',
			$calls[0]['function']
		);

		$message = html_entity_decode( $calls[0]['message'] );

		$this->assertStringContainsString( 'not-a-real-taxonomy', $message );
		$this->assertStringContainsString( 'Invalid taxonomy', $message );
		$this->assertStringContainsString( 'returned via ep_facet_include_taxonomies', $message );

		remove_filter( 'doing_it_wrong_trigger_error', '__return_false' );
		remove_action( 'doing_it_wrong_run', $listener, 10 );
		remove_filter( 'ep_facet_include_taxonomies', $taxonomy_filter, 1 );
	}

}
