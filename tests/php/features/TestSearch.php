<?php
/**
 * Test search feature
 *
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;

/**
 * Search test class
 */
class TestSearch extends BaseTestCase {

	/**
	 * Setup each test.
	 *
	 * @since 2.1
	 */
	public function set_up() {
		global $wpdb;
		parent::set_up();
		$wpdb->suppress_errors();

		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );

		wp_set_current_user( $admin_id );

		ElasticPress\Elasticsearch::factory()->delete_all_indices();
		ElasticPress\Indexables::factory()->get( 'post' )->put_mapping();

		ElasticPress\Indexables::factory()->get( 'post' )->sync_manager->reset_sync_queue();

		$this->setup_test_post_type();
	}

	/**
	 * Clean up after each test. Reset our mocks
	 *
	 * @since 2.1
	 */
	public function tear_down() {
		parent::tear_down();

		$this->fired_actions = array();

		ElasticPress\Features::factory()->update_feature(
			'search',
			array(
				'keyword_boosts' => '',
			)
		);
	}

	/**
	 * Test that search is on
	 *
	 * @since 2.1
	 * @group search
	 */
	public function testSearchOn() {
		ElasticPress\Features::factory()->activate_feature( 'search' );
		ElasticPress\Features::factory()->setup_features();

		// Need to call this since it's hooked to init
		ElasticPress\Features::factory()->get_registered_feature( 'search' )->search_setup();

		$this->ep_factory->post->create();
		$this->ep_factory->post->create();
		$this->ep_factory->post->create( array( 'post_content' => 'findme' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's' => 'findme',
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
	}

	/**
	 * Test case for when index is deleted, request for Elasticsearch should fall back to WP Query
	 *
	 * @group search
	 */
	public function testSearchIndexDeleted() {
		global $wpdb;

		ElasticPress\Features::factory()->activate_feature( 'search' );
		ElasticPress\Features::factory()->setup_features();

		// Need to call this since it's hooked to init
		ElasticPress\Features::factory()->get_registered_feature( 'search' )->search_setup();

		$post_ids = array();

		$this->ep_factory->post->create();
		$this->ep_factory->post->create();
		$this->ep_factory->post->create( array( 'post_content' => 'findme' ) );

		ElasticPress\Elasticsearch::factory()->delete_all_indices();

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's' => 'findme',
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( empty( $query->elasticsearch_success ) );
		$this->assertEquals( 1, count( $query->posts ) );
	}

	/**
	 * Test if decaying is enabled.
	 *
	 * @since 2.4
	 * @group search
	 */
	public function testDecayingEnabled() {
		ElasticPress\Features::factory()->activate_feature( 'search' );
		ElasticPress\Features::factory()->setup_features();

		// Need to call this since it's hooked to init
		ElasticPress\Features::factory()->get_registered_feature( 'search' )->search_setup();

		ElasticPress\Features::factory()->update_feature(
			'search',
			array(
				'active'           => true,
				'decaying_enabled' => true,
			)
		);

		$this->ep_factory->post->create(
			array(
				'post_content' => 'findme test 1',
				'tags_input'   => array(
					'one',
					'two',
				),
			)
		);
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$this->assertTrue( ElasticPress\Features::factory()->get_registered_feature( 'search' )->is_decaying_enabled() );

		add_filter( 'ep_formatted_args', array( $this, 'catch_ep_formatted_args' ), 20 );
		$query = new \WP_Query(
			array(
				's' => 'test',
			)
		);

		$this->assertTrue( isset( $this->fired_actions['ep_formatted_args'] ) );
		$this->assertDecayEnabled( $this->fired_actions['ep_formatted_args']['query'] );

		/**
		 * Test the `ep_is_decaying_enabled` filter
		 */
		add_filter( 'ep_is_decaying_enabled', '__return_true' );
		$this->assertTrue( ElasticPress\Features::factory()->get_registered_feature( 'search' )->is_decaying_enabled() );
		add_filter( 'ep_is_decaying_enabled', '__return_false' );
		$this->assertFalse( ElasticPress\Features::factory()->get_registered_feature( 'search' )->is_decaying_enabled() );
	}

	/**
	 * Test if decaying is disabled.
	 *
	 * @since 2.4
	 * @group search
	 */
	public function testDecayingDisabled() {
		ElasticPress\Features::factory()->activate_feature( 'search' );
		ElasticPress\Features::factory()->setup_features();

		// Need to call this since it's hooked to init
		ElasticPress\Features::factory()->get_registered_feature( 'search' )->search_setup();

		ElasticPress\Features::factory()->update_feature(
			'search',
			array(
				'active'           => true,
				'decaying_enabled' => false,
			)
		);

		$this->ep_factory->post->create(
			array(
				'post_content' => 'findme test 1',
				'tags_input'   => array(
					'one',
					'two',
				),
			)
		);
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		add_filter( 'ep_formatted_args', array( $this, 'catch_ep_formatted_args' ) );

		$query = new \WP_Query(
			array(
				's' => 'test',
			)
		);

		$this->assertTrue( isset( $this->fired_actions['ep_formatted_args'] ) );
		$this->assertDecayDisabled( $this->fired_actions['ep_formatted_args']['query'] );
		$this->assertTrue(
			isset(
				$this->fired_actions['ep_formatted_args']['query']['bool'],
				$this->fired_actions['ep_formatted_args']['query']['bool']['should']
			)
		);
	}

	/**
	 * Test allowed tags for highlighting sub-feature.
	 *
	 * @group search
	 */
	public function testAllowedTags() {
		ElasticPress\Features::factory()->activate_feature( 'search' );
		ElasticPress\Features::factory()->setup_features();

		// Need to call this since it's hooked to init
		ElasticPress\Features::factory()->get_registered_feature( 'search' )->search_setup();

		// a tag that is in the array of allowed tags
		$allowed_tag    = 'span';
		$search_feature = ElasticPress\Features::factory()->get_registered_feature( 'search' );

		$this->assertTrue( 'span' === $search_feature->get_highlighting_tag( $allowed_tag ) );
	}

	/**
	 * Test not-allowed tags for highlighting sub-feature.
	 *
	 * @group search
	 */
	public function testNotAllowedTags() {
		ElasticPress\Features::factory()->activate_feature( 'search' );
		ElasticPress\Features::factory()->setup_features();

		// Need to call this since it's hooked to init
		ElasticPress\Features::factory()->get_registered_feature( 'search' )->search_setup();

		// a tag that is not in the array of allowed tags
		$not_allowed_tag = 'div';
		$search_feature  = ElasticPress\Features::factory()->get_registered_feature( 'search' );

		$this->assertTrue( 'mark' === $search_feature->get_highlighting_tag( $not_allowed_tag ) );
	}

	/**
	 * Testing changing color and tag settings for highlighting sub-feature.
	 *
	 * @group search
	 */
	public function testHighlightSetting() {

		ElasticPress\Features::factory()->activate_feature( 'search' );
		ElasticPress\Features::factory()->setup_features();

		// Need to call this since it's hooked to init
		ElasticPress\Features::factory()->get_registered_feature( 'search' )->search_setup();

		ElasticPress\Features::factory()->update_feature(
			'search',
			array(
				'active'            => true,
				'highlight_enabled' => '1',
				'highlight_tag'     => 'span',
			)
		);

		$settings = ElasticPress\Features::factory()->get_registered_feature( 'search' )->get_settings();

		$this->assertTrue( 'span' === $settings['highlight_tag'] );
	}

	/**
	 * Testing setting a tag that's not allowed
	 *
	 * Leverages the ep_highlighting_tag filter used when updating settings.
	 * Should return 'mark' as the tag.
	 *
	 * @group search
	 */
	public function testBadTagSetting() {

		ElasticPress\Features::factory()->activate_feature( 'search' );
		ElasticPress\Features::factory()->setup_features();

		// Need to call this since it's hooked to init
		ElasticPress\Features::factory()->get_registered_feature( 'search' )->search_setup();

		ElasticPress\Features::factory()->update_feature(
			'search',
			array(
				'active'            => true,
				'highlight_enabled' => '1',
				'highlight_tag'     => 'div',
			)
		);

		$settings = ElasticPress\Features::factory()->get_registered_feature( 'search' )->get_settings();
		$tag      = apply_filters( 'ep_highlighting_tag', $settings['highlight_tag'] );

		$this->assertTrue( 'mark' === $tag );
	}

	/**
	 * Testing excerpt enabled on settings
	 *
	 * @group search
	 */
	public function testExcerptSetting() {

		ElasticPress\Features::factory()->activate_feature( 'search' );
		ElasticPress\Features::factory()->setup_features();

		// Need to call this since it's hooked to init
		ElasticPress\Features::factory()->get_registered_feature( 'search' )->search_setup();

		ElasticPress\Features::factory()->update_feature(
			'search',
			array(
				'active'            => true,
				'highlight_enabled' => '1',
				'highlight_excerpt' => '1',
			)
		);

		$settings = ElasticPress\Features::factory()->get_registered_feature( 'search' )->get_settings();

		$this->assertSame( $settings['highlight_excerpt'], '1' );
	}

	/**
	 * Test Search settings schema
	 *
	 * @since 5.0.0
	 * @group search
	 */
	public function test_get_settings_schema() {
		$settings_schema = \ElasticPress\Features::factory()->get_registered_feature( 'search' )->get_settings_schema();

		$settings_keys = wp_list_pluck( $settings_schema, 'key' );

		$expected = [ 'active', 'decaying_enabled', 'highlight_enabled', 'highlight_excerpt', 'highlight_tag', 'synonyms_editor_mode', 'keyword_boosts' ];
		if ( ! is_multisite() ) {
			$expected[] = 'additional_links';
		}

		$this->assertSame( $expected, $settings_keys );
	}

	/**
	 * Test keyword boosts are injected into the ES query when date decay is enabled.
	 *
	 * @since 5.3.4
	 * @group search
	 */
	public function testKeywordBoostsInjectedWithDecay() {
		ElasticPress\Features::factory()->activate_feature( 'search' );
		ElasticPress\Features::factory()->setup_features();
		ElasticPress\Features::factory()->get_registered_feature( 'search' )->search_setup();

		ElasticPress\Features::factory()->update_feature(
			'search',
			array(
				'active'         => true,
				'keyword_boosts' => "premium:5\nsale:3.5\n",
			)
		);

		add_filter( 'ep_formatted_args', array( $this, 'catch_ep_formatted_args' ), 25 );

		$query = new \WP_Query(
			array(
				's' => 'test',
			)
		);

		$this->assertTrue( isset( $this->fired_actions['ep_formatted_args'] ) );

		$es_query = $this->fired_actions['ep_formatted_args']['query'];
		$this->assertTrue( isset( $es_query['function_score']['query']['bool']['should'] ) );

		$shoulds = $es_query['function_score']['query']['bool']['should'];
		$found   = 0;

		foreach ( $shoulds as $should ) {
			if ( ! isset( $should['multi_match']['query'] ) ) {
				continue;
			}

			if ( 'premium' === $should['multi_match']['query'] && 5.0 === $should['multi_match']['boost'] ) {
				++$found;
			}

			if ( 'sale' === $should['multi_match']['query'] && 3.5 === $should['multi_match']['boost'] ) {
				++$found;
			}
		}

		$this->assertSame( 2, $found );
	}

	/**
	 * Test keyword boosts are injected into the ES query when date decay is disabled.
	 *
	 * @since 5.3.4
	 * @group search
	 */
	public function testKeywordBoostsInjectedWithoutDecay() {
		ElasticPress\Features::factory()->activate_feature( 'search' );
		ElasticPress\Features::factory()->setup_features();
		ElasticPress\Features::factory()->get_registered_feature( 'search' )->search_setup();

		ElasticPress\Features::factory()->update_feature(
			'search',
			array(
				'active'           => true,
				'decaying_enabled' => false,
				'keyword_boosts'   => "premium:5\n",
			)
		);

		add_filter( 'ep_formatted_args', array( $this, 'catch_ep_formatted_args' ), 25 );

		$query = new \WP_Query(
			array(
				's' => 'test',
			)
		);

		$this->assertTrue( isset( $this->fired_actions['ep_formatted_args'] ) );

		$es_query = $this->fired_actions['ep_formatted_args']['query'];
		$this->assertTrue( isset( $es_query['bool']['should'] ) );
		$this->assertFalse( isset( $es_query['function_score'] ) );

		$found = false;
		foreach ( $es_query['bool']['should'] as $should ) {
			if ( isset( $should['multi_match']['query'] ) && 'premium' === $should['multi_match']['query'] && 5.0 === $should['multi_match']['boost'] ) {
				$found = true;
				break;
			}
		}

		$this->assertTrue( $found );
	}

	/**
	 * Test keyword boosts are not injected when empty.
	 *
	 * @since 5.3.4
	 * @group search
	 */
	public function testKeywordBoostsNotInjectedWhenEmpty() {
		ElasticPress\Features::factory()->activate_feature( 'search' );
		ElasticPress\Features::factory()->setup_features();
		ElasticPress\Features::factory()->get_registered_feature( 'search' )->search_setup();

		ElasticPress\Features::factory()->update_feature(
			'search',
			array(
				'active'           => true,
				'decaying_enabled' => false,
				'keyword_boosts'   => '',
			)
		);

		add_filter( 'ep_formatted_args', array( $this, 'catch_ep_formatted_args' ), 25 );

		$query = new \WP_Query(
			array(
				's' => 'test',
			)
		);

		$this->assertTrue( isset( $this->fired_actions['ep_formatted_args'] ) );

		$es_query      = $this->fired_actions['ep_formatted_args']['query'];
		$boost_clauses = 0;
		$other_clauses = 0;

		foreach ( $es_query['bool']['should'] as $should ) {
			if ( isset( $should['multi_match']['query'] ) ) {
				++$boost_clauses;
			} else {
				++$other_clauses;
			}
		}

		$this->assertSame( 0, $boost_clauses );
		$this->assertGreaterThan( 0, $other_clauses );
	}

	/**
	 * Test keyword boost parsing and sanitization.
	 *
	 * @since 5.3.4
	 * @group search
	 */
	public function testKeywordBoostsSanitization() {
		ElasticPress\Features::factory()->activate_feature( 'search' );
		ElasticPress\Features::factory()->setup_features();
		$search = ElasticPress\Features::factory()->get_registered_feature( 'search' );

		$normalized = $search->sanitize_settings_callback(
			array(
				'keyword_boosts' => "premium:5\nno-colon\n  sale:0 \n  valid:2.5  \nbig:101\nnegative:-3\n",
			)
		);

		$this->assertSame( "premium:5\nvalid:2.5", $normalized['keyword_boosts'] );
	}

	/**
	 * Test keyword boosts filter allows programmatic override.
	 *
	 * @since 5.3.4
	 * @group search
	 */
	public function testKeywordBoostsFilter() {
		ElasticPress\Features::factory()->activate_feature( 'search' );
		ElasticPress\Features::factory()->setup_features();
		ElasticPress\Features::factory()->get_registered_feature( 'search' )->search_setup();

		ElasticPress\Features::factory()->update_feature(
			'search',
			array(
				'active'         => true,
				'keyword_boosts' => '',
			)
		);

		add_filter(
			'ep_search_keyword_boosts',
			function () {
				return array( 'filterterm' => 7 );
			}
		);

		add_filter( 'ep_formatted_args', array( $this, 'catch_ep_formatted_args' ), 25 );

		$query = new \WP_Query(
			array(
				's' => 'test',
			)
		);

		$es_query = $this->fired_actions['ep_formatted_args']['query'];
		$shoulds  = isset( $es_query['function_score']['query']['bool']['should'] ) ? $es_query['function_score']['query']['bool']['should'] : $es_query['bool']['should'];
		$found    = false;
		foreach ( $shoulds as $should ) {
			if ( isset( $should['multi_match']['query'] ) && 'filterterm' === $should['multi_match']['query'] && 7.0 === $should['multi_match']['boost'] ) {
				$found = true;
				break;
			}
		}

		$this->assertTrue( $found );
	}
}
