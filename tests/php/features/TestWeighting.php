<?php
/**
 * Test weighting sub-feature
 *
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;
use \ElasticPress\Utils;

/**
 * Weighting test class
 */
class TestWeighting extends BaseTestCase {
	/**
	 * Weighting settings
	 *
	 * @var array
	 */
	public $weighting_settings = [
		'weighting' => [
			'post' => [
				'post_title'   => [
					'weight'  => 1,
					'enabled' => 'on',
				],
				'post_content' => [
					'weight'  => 1,
					'enabled' => 'on',
				],
				'post_excerpt' => [
					'weight'  => 1,
					'enabled' => 'on',
				],

				'author_name'  => [
					'weight'  => 0,
					'enabled' => 'on',
				],
			],
			'page' => [
				'post_title'   => [
					'weight'  => 1,
					'enabled' => 'on',
				],
				'post_content' => [
					'weight'  => 1,
					'enabled' => 'on',
				],
				'post_excerpt' => [
					'weight'  => 1,
					'enabled' => 'on',
				],

				'author_name'  => [
					'weight'  => 0,
					'enabled' => false,
				],
			],
		],
	];

	/**
	 * Setup each test.
	 *
	 * @since 3.4.1
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
		ElasticPress\Features::factory()->activate_feature( 'search' );
	}

	/**
	 * Clean up after each test. Reset our mocks
	 *
	 * @since 2.1
	 */
	public function tear_down() {
		parent::tear_down();

		$this->fired_actions = array();
		update_option( 'elasticpress_weighting', [] );
	}

	/**
	 * Get the Weighting instance
	 *
	 * @return Weighting
	 */
	public function get_weighting_feature() {
		$search = ElasticPress\Features::factory()->get_registered_feature( 'search' );

		return $search->weighting;
	}

	/**
	 * Test searchable post_types exist after configuration change
	 *
	 * @expectedIncorrectUsage ElasticPress\Feature\Search\Weighting::save_weighting_configuration
	 */
	public function testWeightablePostType() {
		$search = ElasticPress\Features::factory()->get_registered_feature( 'search' );

		$searchable_post_types = $search->get_searchable_post_types();

		$weighting_settings = [
			'weighting' => [
				'post' => [
					'post_title' => [
						'enabled' => 'on',
						'weight'  => 1,
					],
				],
			],
		];

		$this->get_weighting_feature()->save_weighting_configuration( $weighting_settings );

		$weighting_configuration = $this->get_weighting_feature()->get_weighting_configuration();

		$this->assertEquals( count( $searchable_post_types ), count( array_keys( $weighting_configuration ) ) );

		$this->assertNotContains( 'ep_test_not_public', array_keys( $weighting_configuration ) );
	}

	/**
	 * Test settings toggle
	 *
	 * @expectedIncorrectUsage ElasticPress\Feature\Search\Weighting::save_weighting_configuration
	 */
	public function testWeightingConfiguration() {

		$weighting_ep_test = $this->get_weighting_feature()->get_post_type_default_settings( 'ep_test' );
		$this->assertEquals( true, $weighting_ep_test['post_title']['enabled'] );

		$weighting_configuration = $this->get_weighting_feature()->get_weighting_configuration();
		$this->assertEmpty( $weighting_configuration );

		$weighting_settings = [
			'weighting' => [
				'post' => [
					'post_title' => [
						'enabled' => 'on',
						'weight'  => 1,
					],
				],
			],
		];

		// enable post_title weighting
		$this->get_weighting_feature()->save_weighting_configuration( $weighting_settings );
		$weighting_configuration = $this->get_weighting_feature()->get_weighting_configuration();
		$this->assertEquals( true, $weighting_configuration['post']['post_title']['enabled'] );
		$this->assertEquals( 1, $weighting_configuration['post']['post_title']['weight'] );

		// disable post_title weighting
		$weighting_settings['weighting']['post']['post_title']['enabled'] = '';
		$this->get_weighting_feature()->save_weighting_configuration( $weighting_settings );
		$weighting_configuration = $this->get_weighting_feature()->get_weighting_configuration();
		$this->assertEquals( false, $weighting_configuration['post']['post_title']['enabled'] );

	}

	/**
	 * Test the `ep_weighting_default_enabled_taxonomies` filter.
	 *
	 * This filter should affect the weighting dashboard only if it was not saved yet.
	 *
	 * @since 3.6.5
	 * @group weighting
	 * @expectedIncorrectUsage ElasticPress\Feature\Search\Weighting::save_weighting_configuration
	 */
	public function testWeightingDefaultEnabledTaxonomies() {
		// By default, `post_format` should not be enabled, only `category` and `post_tag`.
		$post_default_config = $this->get_weighting_feature()->get_post_type_default_settings( 'post' );
		$this->assertArrayNotHasKey( 'terms.post_format.name', $post_default_config );
		$this->assertTrue( $post_default_config['terms.category.name']['enabled'] );
		$this->assertTrue( $post_default_config['terms.post_tag.name']['enabled'] );

		add_filter(
			'ep_weighting_default_enabled_taxonomies',
			function ( $taxs, $post_type ) {
				if ( 'post' === $post_type ) {
					$taxs[] = 'post_format';
				}
				return $taxs;
			},
			10,
			2
		);

		$post_default_config = $this->get_weighting_feature()->get_post_type_default_settings( 'post' );
		$this->assertTrue( $post_default_config['terms.post_format.name']['enabled'] );

		// `$this->weighting_settings` does not have post_format. So, once saved, the configuration should not have it enabled too.
		$this->get_weighting_feature()->save_weighting_configuration( $this->weighting_settings );
		$weighting_configuration = $this->get_weighting_feature()->get_weighting_configuration();
		$this->assertArrayNotHasKey( 'post_format', $weighting_configuration['post'] );
		$this->assertArrayNotHasKey( 'terms.post_format.name', $weighting_configuration['post'] );
	}

	/**
	 * Test the `get_weightable_fields_for_post_type` method
	 */
	public function testGetWeightableFieldsForPostType() {
		$fields = $this->get_weighting_feature()->get_weightable_fields_for_post_type( 'ep_test' );

		$this->assertEquals( 3, count( $fields ) ); // attributes, taxonomies, and ep_metadata
		$this->assertContains( 'post_title', array_keys( $fields['attributes']['children'] ) );
		$this->assertContains( 'terms.category.name', array_keys( $fields['taxonomies']['children'] ) );
		$this->assertContains( 'terms.post_tag.name', array_keys( $fields['taxonomies']['children'] ) );
	}

	/**
	 * Test the `add_weighting_submenu_page` method
	 */
	public function testAddWeightingSubmenuPage() {
		$site_url = trailingslashit( get_option( 'siteurl' ) );

		add_menu_page(
			'ElasticPress',
			'ElasticPress',
			Utils\get_capability(),
			'elasticpress'
		);

		$this->get_weighting_feature()->add_weighting_submenu_page();

		$this->assertEquals( $site_url . 'wp-admin/admin.php?page=elasticpress-weighting', menu_page_url( 'elasticpress-weighting', false ) );
	}

	/**
	 * Test the `render_settings_page` method
	 */
	public function testRenderSettingsPage() {
		ob_start();
		$this->get_weighting_feature()->render_settings_page();
		$content = ob_get_clean();

		$this->assertStringContainsString( 'id="ep-weighting-screen"', $content );
	}

	/**
	 * Test the `render_settings_page` method (success)
	 */
	public function testRenderSettingsPageSaveSuccess() {
		$_GET['settings-updated'] = true;
		ob_start();
		$this->get_weighting_feature()->render_settings_page();
		$content = ob_get_clean();

		$this->assertStringContainsString( 'Changes Saved', $content );
	}

	/**
	 * Test the `render_settings_page` method (failed)
	 */
	public function testRenderSettingsPageSaveFailed() {
		$_GET['settings-updated'] = false;
		ob_start();
		$this->get_weighting_feature()->render_settings_page();
		$content = ob_get_clean();

		$this->assertStringContainsString( 'An error occurred when saving', $content );
	}

	/**
	 * Test the `handle_save` method
	 *
	 * @expectedIncorrectUsage ElasticPress\Feature\Search\Weighting::deprecated_handle_save
	 * @expectedIncorrectUsage ElasticPress\Feature\Search\Weighting::save_weighting_configuration
	 */
	public function testHandleSave() {
		$weighting_class = $this->getMockBuilder( 'ElasticPress\Feature\Search\Weighting' )
			->setMethods( [ 'redirect' ] )
			->getMock();

		$_POST['ep-weighting-nonce'] = false;
		$this->assertEquals( null, $weighting_class->handle_save() );

		// Change to non admin user
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'author' ) ) );

		$_POST['ep-weighting-nonce'] = wp_create_nonce( 'save-weighting' );
		$this->assertEquals( null, $weighting_class->handle_save() );

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
		$_POST = [
			'ep-weighting-nonce' => wp_create_nonce( 'save-weighting' ),
			'weighting'          => [
				'post' => [
					'post_title' => [
						'enabled' => 'on',
						'weight'  => 1,
					],
				],
			],
		];

		$weighting_class->expects( $this->once() )->method( 'redirect' );
		$weighting_class->handle_save();
	}

	/**
	 * Test the `save_weighting_configuration` method (invalid post type)
	 *
	 * @expectedIncorrectUsage ElasticPress\Feature\Search\Weighting::save_weighting_configuration
	 */
	public function testSaveWeightingConfigurationInvalidPostType() {
		$weighting_settings = [
			'weighting' => [
				'post' => [
					'post_title' => [
						'enabled' => 'on',
						'weight'  => 1,
					],
				],
			],
		];

		add_filter(
			'ep_searchable_post_types',
			function( $config ) {
				return array_merge( $config, [ 'invalid_post_type' ] );
			}
		);

		add_filter(
			'ep_weighting_configuration',
			function( $config ) {
				return array_merge( $config, [ 'invalid_post_type' ] );
			}
		);

		$this->assertNotContains( 'invalid_post_type', $this->get_weighting_feature()->save_weighting_configuration( $weighting_settings ) );
	}

	/**
	 * Test the `recursively_inject_weights_to_fields` method
	 */
	public function testRecursivelyInjectWeightsToFieldsInvalidArgs() {
		$invalid_args = '';
		$this->assertEquals( null, $this->get_weighting_feature()->recursively_inject_weights_to_fields( $invalid_args, $this->weighting_settings['weighting']['post'] ) );
	}

	/**
	 * Test the `post_type_has_fields` method
	 *
	 * @expectedIncorrectUsage ElasticPress\Feature\Search\Weighting::save_weighting_configuration
	 */
	public function testPostTypeHasFieldsWithDefaultConfig() {
		$this->assertTrue( $this->get_weighting_feature()->post_type_has_fields( 'post' ) );
	}

	/**
	 * Test the `post_type_has_fields` method (with custom config)
	 */
	public function testPostTypeHasFieldsWithCustomConfig() {
		// Test with configuration saved for post only, page will return false.
		$weighting_settings = [
			'weighting' => [
				'post' => [
					'post_title' => [
						'enabled' => 'on',
						'weight'  => 1,
					],
				],
			],
		];
		$this->get_weighting_feature()->save_weighting_configuration( $weighting_settings );

		$this->assertTrue( $this->get_weighting_feature()->post_type_has_fields( 'post' ) );
		$this->assertFalse( $this->get_weighting_feature()->post_type_has_fields( 'page' ) );
	}

	/**
	 * Check if `post_type_has_fields()` behaves correctly when using the `ep_weighting_configuration_for_search` filter.
	 *
	 * @since 4.1.0
	 */
	public function testPostTypeHasFieldsWithCustomConfigViaFilter() {
		$function = function() {
			return [
				'page'   => [],
				'post'   => [
					'post_title' => [
						'enabled' => 'on',
						'weight'  => 1,
					],
				],
				'test'   => [
					'post_title' => [
						'enabled' => true,
						'weight'  => 1,
					],
				],
				'test-2' => [
					'post_title' => [
						'enabled' => 10, // This is not considered a "truthy" value
						'weight'  => 1,
					],
				],
			];
		};
		add_filter( 'ep_weighting_configuration_for_search', $function );

		$this->assertTrue( $this->get_weighting_feature()->post_type_has_fields( 'post' ) );
		$this->assertFalse( $this->get_weighting_feature()->post_type_has_fields( 'page' ) );
		$this->assertTrue( $this->get_weighting_feature()->post_type_has_fields( 'test' ) );
		$this->assertFalse( $this->get_weighting_feature()->post_type_has_fields( 'test-2' ) );
	}

	/**
	 * Test the `do_weighting` method (with `search_fields` parameter)
	 */
	public function testDoWeightingWithQueryContainsSearchFields() {
		// Test search fields are set on the query.
		$this->assertSame( [ 'do', 'nothing' ], $this->get_weighting_feature()->do_weighting( [ 'do', 'nothing' ], [ 'search_fields' => [ 'post_title' ] ] ) );
	}

	/**
	 * Test the `do_weighting` method in admin
	 */
	public function testDoWeightingInAdmin() {
		// Test if we're in admin area.
		set_current_screen( 'edit-post' );
		$this->assertSame( [ 'do', 'nothing' ], $this->get_weighting_feature()->do_weighting( [ 'do', 'nothing' ], [ 's' => 'blog' ] ) );
		set_current_screen( 'front' );
	}

	/**
	 * Test the `do_weighting` method (with an empty search query)
	 */
	public function testDoWeightingWithEmptySearchQuery() {
		// Test if search query is empty.
		$this->assertSame( [ 'do', 'nothing' ], $this->get_weighting_feature()->do_weighting( [ 'do', 'nothing' ], [ 's' => '' ] ) );
	}

	/**
	 * Test the `do_weighting` method (with the default config)
	 */
	public function testDoWeightingWithDefaultConfig() {
		$new_formatted_args = $this->get_weighting_feature()->do_weighting( ... $this->getArgs() );

		// We have 5 searchable post types.
		$this->assertEquals( 5, count( $new_formatted_args['query']['function_score']['query']['bool']['should'] ) );
	}

	/**
	 * Test the `do_weighting` method (with the custom config)
	 *
	 * @expectedIncorrectUsage ElasticPress\Feature\Search\Weighting::save_weighting_configuration
	 */
	public function testDoWeightingWithCustomConfig() {
		$this->get_weighting_feature()->save_weighting_configuration( $this->weighting_settings );

		$new_formatted_args = $this->get_weighting_feature()->do_weighting( ...$this->getArgs() );

		$this->assertEquals( 2, count( $new_formatted_args['query']['function_score']['query']['bool']['should'] ) );
	}

	/**
	 * Get formatted ES and query vars
	 */
	public function getArgs() {
		$post = new \ElasticPress\Indexable\Post\Post();

		$query      = new \WP_Query( [ 's' => 'blog' ] );
		$query_vars = $query->query_vars;

		$query_vars['post_type'] = apply_filters( 'ep_query_post_type', $query_vars['post_type'], $query );

		if ( 'any' === $query_vars['post_type'] ) {
			unset( $query_vars['post_type'] );
		}

		/**
		 * If not search and not set default to post. If not set and is search, use searchable post types
		 */
		if ( empty( $query_vars['post_type'] ) ) {
			if ( empty( $query_vars['s'] ) ) {
				$query_vars['post_type'] = 'post';
			} else {
				$query_vars['post_type'] = array_values( get_post_types( array( 'exclude_from_search' => false ) ) );
			}
		}

		$formatted_args = $post->format_args( $query_vars, $query );

		return [ $formatted_args, $query_vars ];
	}

	/**
	 * Test if ep_weighting_configuration_for_search is applied even when the config was not saved yet.
	 *
	 * @since 4.5.0
	 */
	public function testApplyFilterWhenWeightingConfigWasNotSaved() {
		delete_option( 'elasticpress_weighting' );

		$add_post_content_filter = function( $weight_config ) {
			$weight_config['new_cpt']['post_content_filtered'] = [
				'enabled' => true,
				'weight'  => 40,
			];
			return $weight_config;
		};
		$set_query_post_type     = function() {
			return 'new_cpt';
		};

		add_filter( 'ep_weighting_configuration_for_search', $add_post_content_filter );
		add_filter( 'ep_query_post_type', $set_query_post_type );

		$new_formatted_args = $this->get_weighting_feature()->do_weighting( ... $this->getArgs() );

		$query_multi_match = $new_formatted_args['query']['function_score']['query']
			['bool']['should'][0]['bool']['must'][0]
			['bool']['should'][0]['bool']['must'][0]
			['bool']['should'][0]['multi_match'];
		$this->assertEquals( [ 'post_content_filtered^40' ], $query_multi_match['fields'] );
	}
}
