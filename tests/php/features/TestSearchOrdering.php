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
class TestSearchOrdering extends BaseTestCase {

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

		// Backup the original
		$this->original_post    = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : '';
		$this->original_pagenow = $GLOBALS['pagenow'];
		$this->original_screen  = get_current_screen();
	}

	/**
	 * Clean up after each test. Reset our mocks
	 *
	 * @since 2.1
	 */
	public function tear_down() {
		parent::tear_down();

		$this->fired_actions = array();

		// Restore the original
		$GLOBALS['post']    = $this->original_post;
		$GLOBALS['pagenow'] = $this->original_pagenow;
		if ( $this->original_screen instanceof \WP_Screen ) {
			set_current_screen( $this->original_screen );
		}
	}

	/**
	 * Get the feature instance
	 *
	 * @return ElasticPress\Feature\SearchOrdering\SearchOrdering
	 */
	public function get_feature() {
		return ElasticPress\Features::factory()->get_registered_feature( 'searchordering' );
	}

	/**
	 * Test the class constructor
	 */
	public function testConstruct() {
		$instance = new \ElasticPress\Feature\SearchOrdering\SearchOrdering();
		$this->assertSame( 'searchordering', $instance->slug );
		$this->assertSame( 'Custom Search Results', $instance->title );
	}

	/**
	 * Test the `setup` method when search is disabled
	 */
	public function testSetupWithSearchDisabled() {
		ElasticPress\Features::factory()->deactivate_feature( 'search' );
		$this->assertFalse( $this->get_feature()->setup() );
		ElasticPress\Features::factory()->activate_feature( 'search' );
	}

	/**
	 * Test the `filter_updated_messages` method
	 */
	public function testFilterUpdatedMessages() {
		$post            = $this->ep_factory->post->create_and_get();
		$GLOBALS['post'] = $post;
		$messages        = $this->get_feature()->filter_updated_messages( [] );

		$this->assertArrayHasKey( 'ep-pointer', $messages );
	}

	/**
	 * Test the `output_feature_box_summary` method
	 */
	public function testOutputFeatureBoxSummary() {
		ob_start();
		$this->get_feature()->output_feature_box_summary();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Insert specific posts into search results for specific search queries.', $output );
	}

	/**
	 * Test the `output_feature_box_long` method
	 */
	public function testOutputFeatureBoxLong() {
		ob_start();
		$this->get_feature()->output_feature_box_long();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Selected posts will be inserted into search results in the specified position.', $output );
	}

	/**
	 * Test the `admin_menu` method
	 */
	public function testAdminMenu() {
		add_menu_page(
			'ElasticPress',
			'ElasticPress',
			\ElasticPress\Utils\get_capability(),
			'elasticpress'
		);

		$this->get_feature()->admin_menu();

		$menu = $GLOBALS['submenu'];

		$this->assertEquals( 2, count( $menu['elasticpress'] ) );
		$this->assertEquals( 'Custom Results', $menu['elasticpress'][1][0] );
	}

	/**
	 * Test the `admin_menu` method
	 */
	public function parent_file() {
		set_current_screen( 'front' );

		$this->assertSame( 'test_parent_file', $this->get_feature()->parent_file( 'test_parent_file' ) );

		if ( ! $this->is_network_activate() ) {
			set_current_screen( 'ep-pointer' );
			$this->assertSame( 'elasticpress', $this->get_feature()->parent_file( 'test_parent_file' ) );
		}
	}

	/**
	 * Test the `submenu_file` method
	 */
	public function testSubmenuFile() {
		set_current_screen( 'front' );

		$this->assertSame( 'test_submenu_file', $this->get_feature()->submenu_file( 'test_submenu_file' ) );

		if ( ! $this->is_network_activate() ) {
			set_current_screen( 'ep-pointer' );
			$this->assertSame( 'edit.php?post_type=ep-pointer', $this->get_feature()->submenu_file( 'test_submenu_file' ) );
		}
	}

	/**
	 * Test the `register_post_type` method
	 */
	public function testRegisterPostType() {
		$this->get_feature()->register_post_type();
		$post_types = get_post_types();
		$this->assertContains( 'ep-pointer', $post_types );

		$taxonomies = get_taxonomies();
		$this->assertContains( 'ep_custom_result', $taxonomies );
	}

	/**
	 * Test the `register_meta_box` method
	 */
	public function testRegisterMetaBox() {
		global $wp_meta_boxes;
		$this->get_feature()->register_meta_box();
		if ( $this->is_network_activate() ) {
			$this->assertArrayHasKey( 'ep-ordering', $wp_meta_boxes['ep-pointer-network']['normal']['default'] );
			$this->assertEquals( 'Manage Results', $wp_meta_boxes['ep-pointer-network']['normal']['default']['ep-ordering']['title'] );
		} else {
			$this->assertArrayHasKey( 'ep-ordering', $wp_meta_boxes['ep-pointer']['normal']['default'] );
			$this->assertEquals( 'Manage Results', $wp_meta_boxes['ep-pointer']['normal']['default']['ep-ordering']['title'] );
		}
	}

	/**
	 * Test the `render_meta_box` method
	 */
	public function testRenderMetaBox() {
		$post = $this->ep_factory->post->create_and_get();

		ob_start();
		$this->get_feature()->render_meta_box( $post );
		$output = ob_get_clean();
		$this->assertStringContainsString( 'ordering-app', $output );
	}

	/**
	 * Test the `get_pointer_data_for_localize` method
	 */
	public function testGetPointerData() {
		$post_id_1  = $this->ep_factory->post->create();
		$post_id_2  = $this->ep_factory->post->create();
		$pointer_id = $this->ep_factory->post->create();

		update_post_meta(
			$pointer_id,
			'pointers',
			[
				[
					'ID'    => $post_id_1,
					'order' => 1,
				],
				[
					'ID'    => $post_id_2,
					'order' => 2,
				],
			]
		);

		$GLOBALS['post'] = get_post( $pointer_id );

		$localized_data = $this->get_feature()->get_pointer_data_for_localize();

		$this->assertEquals( 2, count( $localized_data ) );
		$this->assertArrayHasKey( 'pointers', $localized_data );
		$this->assertArrayHasKey( 'posts', $localized_data );
		$this->assertEquals( $post_id_1, $localized_data['pointers'][0]['ID'] );
		$this->assertEquals( $post_id_2, $localized_data['pointers'][1]['ID'] );
		$this->assertInstanceOf( '\WP_Post', $localized_data['posts'][ $post_id_1 ] );
		$this->assertInstanceOf( '\WP_Post', $localized_data['posts'][ $post_id_2 ] );
	}

	/**
	 * Test the `admin_enqueue_scripts` method
	 */
	public function testEnqueueScripts() {
		$this->assertFalse( wp_script_is( 'ep_ordering_scripts' ) );
		$GLOBALS['pagenow'] = 'post-new.php';
		set_current_screen( 'ep-pointer' );
		$this->get_feature()->admin_enqueue_scripts();
		$this->assertTrue( wp_script_is( 'ep_ordering_scripts' ) );
	}

	/**
	 * Test the early return in the `save_post` method
	 */
	public function testSavePostEarlyReturn() {
		$pointer_id = $this->ep_factory->post->create( array( 'post_title' => 'findme' ) );
		$return     = $this->get_feature()->save_post( $pointer_id, get_post( $pointer_id ) );
		$this->assertNull( $return );

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'subscriber' ) ) );
		$_POST = [ 'search-ordering-nonce' => wp_create_nonce( 'save-search-ordering' ) ];

		$return = $this->get_feature()->save_post( $pointer_id, get_post( $pointer_id ) );
		$this->assertNull( $return );

	}

	/**
	 * Test the `save_post` method
	 */
	public function testSavePost() {
		$post_id_1  = $this->ep_factory->post->create( array( 'post_content' => 'findme test 1' ) );
		$post_id_2  = $this->ep_factory->post->create( array( 'post_content' => 'findme test 2' ) );
		$pointer_id = wp_insert_post(
			[
				'post_title'  => 'findme',
				'post_status' => 'publish',
				'post_type'   => 'ep-pointer',
			]
		);

		$_POST = [
			'search-ordering-nonce' => wp_create_nonce( 'save-search-ordering' ),
			'ordered_posts'         => wp_json_encode(
				[
					[
						'ID'    => $post_id_1,
						'order' => 1,
					],
					[
						'ID'    => $post_id_2,
						'order' => 2,
					],
				]
			),
		];

		$this->get_feature()->save_post( $pointer_id, get_post( $pointer_id ) );

		$pointers_data = get_post_meta( $pointer_id, 'pointers', true );

		$this->assertEquals( 2, count( $pointers_data ) );
		$this->assertEquals( $post_id_1, $pointers_data[0]['ID'] );
		$this->assertEquals( $post_id_2, $pointers_data[1]['ID'] );
		$this->assertEquals( 'findme', get_post_meta( $pointer_id, 'search_term', true ) );
		$terms = wp_list_pluck( get_the_terms( $post_id_1, 'ep_custom_result' ), 'name' );
		$this->assertContains( 'findme', $terms );

		/**
		 * Test change search term.
		 */
		$post_id_3 = $this->ep_factory->post->create( array( 'post_content' => '10up test 1' ) );
		$post_id_4 = $this->ep_factory->post->create( array( 'post_content' => '10up test 2' ) );
		$_POST     = [
			'search-ordering-nonce' => wp_create_nonce( 'save-search-ordering' ),
			'ordered_posts'         => wp_json_encode(
				[
					[
						'ID'    => $post_id_3,
						'order' => 1,
					],
					[
						'ID'    => $post_id_4,
						'order' => 2,
					],
					[
						'ID'    => $post_id_2,
						'order' => 3,
					],
				]
			),
		];

		wp_update_post(
			[
				'ID'         => $pointer_id,
				'post_title' => '10up',
			]
		);

		$this->get_feature()->save_post( $pointer_id, get_post( $pointer_id ) );
		$this->assertEquals( '10up', get_post_meta( $pointer_id, 'search_term', true ) );

		$this->assertFalse( get_the_terms( $post_id_1, 'ep_custom_result' ) );
	}

	/**
	 * Test the `save_post` method on drafts
	 */
	public function testSaveUnpublishedPost() {
		$post_id_1  = $this->ep_factory->post->create( [ 'post_content' => 'findme test 1' ] );
		$post_id_2  = $this->ep_factory->post->create( [ 'post_content' => 'findme test 2' ] );
		$pointer_id = wp_insert_post(
			[
				'post_title'  => 'findme',
				'post_status' => 'draft',
				'post_type'   => 'ep-pointer',
			]
		);

		$_POST = [
			'search-ordering-nonce' => wp_create_nonce( 'save-search-ordering' ),
			'ordered_posts'         => wp_json_encode(
				[
					[
						'ID'    => $post_id_1,
						'order' => 1,
					],
					[
						'ID'    => $post_id_2,
						'order' => 2,
					],
				]
			),
		];

		$this->get_feature()->save_post( $pointer_id, get_post( $pointer_id ) );

		$pointers_data = get_post_meta( $pointer_id, 'pointers', true );

		$this->assertFalse( get_the_terms( $post_id_1, 'ep_custom_result' ) );
	}

	/**
	 * Test the `save_post` method
	 */
	public function testSavePostMaxCustomResults() {
		update_option( 'posts_per_page', 2 );
		$post_id_1  = $this->ep_factory->post->create( [ 'post_content' => 'findme test 1' ] );
		$post_id_2  = $this->ep_factory->post->create( [ 'post_content' => 'findme test 2' ] );
		$post_id_3  = $this->ep_factory->post->create( [ 'post_content' => 'findme test 3' ] );
		$pointer_id = wp_insert_post(
			[
				'post_title'  => 'findme',
				'post_status' => 'publish',
				'post_type'   => 'ep-pointer',
			]
		);

		$_POST = [
			'search-ordering-nonce' => wp_create_nonce( 'save-search-ordering' ),
			'ordered_posts'         => wp_json_encode(
				[
					[
						'ID'    => $post_id_1,
						'order' => 1,
					],
					[
						'ID'    => $post_id_2,
						'order' => 2,
					],
					[
						'ID'    => $post_id_3,
						'order' => 3,
					],
				]
			),
		];

		$this->get_feature()->save_post( $pointer_id, get_post( $pointer_id ) );

		$pointers_data = get_post_meta( $pointer_id, 'pointers', true );

		$this->assertEquals( 2, count( $pointers_data ) );
		$this->assertEquals( $post_id_1, $pointers_data[0]['ID'] );
		$this->assertEquals( $post_id_2, $pointers_data[1]['ID'] );
		$this->assertEquals( 'findme', get_post_meta( $pointer_id, 'search_term', true ) );
		$this->assertContains( 'findme', wp_list_pluck( get_the_terms( $post_id_1, 'ep_custom_result' ), 'name' ) );
		$this->assertFalse( get_the_terms( $post_id_3, 'ep_custom_result' ) );
	}

	/**
	 * Test the `create_or_return_custom_result_term` method
	 */
	public function testCreateTermFailed() {
		$create_term_failed = function() {
			return new \WP_Error( 'test_error' );
		};

		add_filter( 'pre_insert_term', $create_term_failed );

		$this->assertFalse( $this->get_feature()->create_or_return_custom_result_term( 'test' ) );
	}

	/**
	 * Test the `weighting_fields_for_post_type` method
	 */
	public function testExcludeCustomResultsWeightingFields() {
		$fields = [
			'taxonomies' => [
				'children' => [
					'terms.category.name'         => [],
					'terms.post_tag.name'         => [],
					'terms.ep_custom_result.name' => [],
				],
			],
		];

		$result = $this->get_feature()->weighting_fields_for_post_type( $fields, 'post' );

		$this->assertNotContains( 'terms.ep_custom_result.name', $result['taxonomies']['children'] );
		$this->assertEquals( 2, count( $result['taxonomies']['children'] ) );
	}

	/**
	 * Test the `weighting_fields_for_post_type` method
	 */
	public function testFilterWeightingConfig() {
		$config = [
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
		];

		$updated_config = $this->get_feature()->filter_weighting_configuration( $config, [] );

		$this->assertEquals( 5, count( $updated_config['post'] ) );
		$this->assertArrayHasKey( 'terms.ep_custom_result.name', $updated_config['post'] );
	}

	/**
	 * Test the `filter_enter_title_here` method
	 */
	public function testFilterEnterTitleHere() {
		$this->assertEquals( 'Nothing changes', $this->get_feature()->filter_enter_title_here( 'Nothing changes' ) );

		$pointer_id = wp_insert_post(
			[
				'post_title'  => 'findme',
				'post_status' => 'publish',
				'post_type'   => 'ep-pointer',
			]
		);

		$GLOBALS['post'] = get_post( $pointer_id );

		$this->assertEquals( 'Enter Search Query', $this->get_feature()->filter_enter_title_here( 'Nothing changes' ) );
	}

	/**
	 * Test the `filter_column_names` method
	 */
	public function testFilterColumnNames() {
		$columns = [ 'title' => 'Post title' ];
		$result  = $this->get_feature()->filter_column_names( $columns );

		$this->assertArrayHasKey( 'title', $result );
		$this->assertEquals( 'Search Query', $result['title'] );
	}

	/**
	 * Test the `posts_results` method
	 */
	public function testPostsResults() {
		ElasticPress\Features::factory()->activate_feature( 'search' );
		ElasticPress\Features::factory()->setup_features();
		ElasticPress\Features::factory()->get_registered_feature( 'search' )->search_setup();

		$post_id_1 = $this->ep_factory->post->create( [ 'post_content' => 'findme test 1' ] );
		$post_id_2 = $this->ep_factory->post->create( [ 'post_content' => 'findme test 2' ] );
		$post_id_3 = $this->ep_factory->post->create( [ 'post_content' => 'findme test 3' ] );

		$pointer_id = wp_insert_post(
			[
				'post_title'  => 'findme',
				'post_status' => 'publish',
				'post_type'   => 'ep-pointer',
			]
		);

		$_POST = [
			'search-ordering-nonce' => wp_create_nonce( 'save-search-ordering' ),
			'ordered_posts'         => wp_json_encode(
				[
					[
						'ID'    => $post_id_2,
						'order' => 1,
					],
				]
			),
		];

		$this->get_feature()->save_post( $pointer_id, get_post( $pointer_id ) );

		ElasticPress\Indexables::factory()->get( 'post' )->index( $post_id_2, true );
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$query = new \WP_Query( [ 's' => 'findme' ] );

		$new_posts = $this->get_feature()->posts_results( $query->posts, $query );

		$this->assertEquals( 3, count( $new_posts ) );
		$this->assertEquals( $post_id_2, $new_posts[0]->ID );
	}

	/**
	 * Test REST API endpoints
	 */
	public function testRestApiInit() {
		global $wp_rest_server;
		add_filter( 'rest_url', [ $this, 'filter_rest_url_for_leading_slash' ], 10, 2 );

		$wp_rest_server = new \WP_REST_Server();
		do_action( 'rest_api_init', $wp_rest_server );

		$routes = $wp_rest_server->get_routes();
		$this->assertArrayHasKey( '/elasticpress/v1', $routes );
		$this->assertArrayHasKey( '/elasticpress/v1/pointer_search', $routes );
		$this->assertArrayHasKey( '/elasticpress/v1/pointer_preview', $routes );

		$request  = new \WP_REST_Request( 'GET', '/elasticpress/v1/pointer_search' );
		$response = $wp_rest_server->dispatch( $request );
		$this->assertEquals( 400, $response->get_status() );

		$request  = new \WP_REST_Request( 'GET', '/elasticpress/v1/pointer_preview' );
		$response = $wp_rest_server->dispatch( $request );
		$this->assertEquals( 400, $response->get_status() );
	}

	/**
	 * Test API endpoints are accessible for users with `manage_elasticpress` capability.
	 *
	 * @since 4.4.0
	 */
	public function testUserWithManageElasticPressCapabilityCanAccessAPI() {
		global $wp_rest_server;

		$wp_rest_server = new \WP_REST_Server();
		do_action( 'rest_api_init', $wp_rest_server );

		$request = new \WP_REST_Request( 'GET', '/elasticpress/v1/pointer_search' );
		$request->set_query_params(
			array(
				's' => 'hello-world',
			)
		);
		$response = $wp_rest_server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$request = new \WP_REST_Request( 'GET', '/elasticpress/v1/pointer_preview' );
		$request->set_query_params(
			array(
				's' => 'hello-world',
			)
		);
		$response = $wp_rest_server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Test API endpoints are not accessible for users without `manage_elasticpress` capability.
	 *
	 * @since 4.4.0
	 */
	public function testUserWithOutManageElasticPressCapabilityCanNotAccessAPI() {
		global $wp_rest_server;

		// Set current user without `manage_elasticpress` capability.
		wp_set_current_user( $this->factory()->user->create( array( 'role' => 'editor' ) ) );

		$wp_rest_server = new \WP_REST_Server();
		do_action( 'rest_api_init', $wp_rest_server );

		$request = new \WP_REST_Request( 'GET', '/elasticpress/v1/pointer_search' );
		$request->set_query_params(
			array(
				's' => 'hello-world',
			)
		);
		$response = $wp_rest_server->dispatch( $request );
		$this->assertEquals( 403, $response->get_status() );

		$request = new \WP_REST_Request( 'GET', '/elasticpress/v1/pointer_preview' );
		$request->set_query_params(
			array(
				's' => 'hello-world',
			)
		);
		$response = $wp_rest_server->dispatch( $request );
		$this->assertEquals( 403, $response->get_status() );
	}

	/**
	 * Make sure path for rest_url has a leading slash for proper resolution.
	 *
	 * @param string $url  REST URL.
	 * @param string $path REST route.
	 * @return string
	 */
	public function filter_rest_url_for_leading_slash( $url, $path ) {
		if ( is_multisite() || get_option( 'permalink_structure' ) ) {
			return $url;
		}

		// Make sure path for rest_url has a leading slash for proper resolution.
		$this->assertStringStartsWith( '/', $path, 'REST API URL should have a leading slash.' );

		return $url;
	}

	/**
	 * Test the `handle_post_trash` method
	 */
	public function testHandlePostTrash() {
		$post_id_1  = $this->ep_factory->post->create( array( 'post_content' => 'findme test 1' ) );
		$post_id_2  = $this->ep_factory->post->create( array( 'post_content' => 'findme test 2' ) );
		$pointer_id = wp_insert_post(
			[
				'post_title'  => 'findme',
				'post_status' => 'publish',
				'post_type'   => 'ep-pointer',
			]
		);

		// Test non ep-pointer post type.
		$this->assertNull( $this->get_feature()->handle_post_trash( $post_id_1 ) );

		// Test empty pointers
		$this->assertNull( $this->get_feature()->handle_post_trash( $pointer_id ) );

		$_POST = [
			'search-ordering-nonce' => wp_create_nonce( 'save-search-ordering' ),
			'ordered_posts'         => wp_json_encode(
				[
					[
						'ID'    => $post_id_1,
						'order' => 1,
					],
					[
						'ID'    => $post_id_2,
						'order' => 2,
					],
				]
			),
		];

		$this->get_feature()->save_post( $pointer_id, get_post( $pointer_id ) );
		$this->assertContains( 'findme', wp_list_pluck( get_the_terms( $post_id_1, 'ep_custom_result' ), 'name' ) );

		$this->get_feature()->handle_post_trash( $pointer_id );

		$this->assertFalse( get_the_terms( $post_id_1, 'ep_custom_result' ) );
	}

	/**
	 * Test the `handle_post_untrash` method
	 */
	public function testHandlePostUntrash() {
		$post_id_1  = $this->ep_factory->post->create( array( 'post_content' => 'findme test 1' ) );
		$post_id_2  = $this->ep_factory->post->create( array( 'post_content' => 'findme test 2' ) );
		$pointer_id = wp_insert_post(
			[
				'post_title'  => 'findme',
				'post_status' => 'publish',
				'post_type'   => 'ep-pointer',
			]
		);

		$_POST = [
			'search-ordering-nonce' => wp_create_nonce( 'save-search-ordering' ),
			'ordered_posts'         => wp_json_encode(
				[
					[
						'ID'    => $post_id_1,
						'order' => 1,
					],
					[
						'ID'    => $post_id_2,
						'order' => 2,
					],
				]
			),
		];

		$this->get_feature()->save_post( $pointer_id, get_post( $pointer_id ) );

		$this->get_feature()->handle_post_trash( $pointer_id );

		// Test non ep-pointer post type.
		$this->assertNull( $this->get_feature()->handle_post_untrash( $post_id_1 ) );

		$this->get_feature()->handle_post_untrash( $pointer_id );

		$this->assertContains( 'findme', wp_list_pluck( get_the_terms( $post_id_1, 'ep_custom_result' ), 'name' ) );
		$this->assertContains( 'findme', wp_list_pluck( get_the_terms( $post_id_2, 'ep_custom_result' ), 'name' ) );
	}
}
