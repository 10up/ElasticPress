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
	public function setUp() {
		global $wpdb;
		parent::setUp();
		$wpdb->suppress_errors();

		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );

		wp_set_current_user( $admin_id );

		ElasticPress\Elasticsearch::factory()->delete_all_indices();
		ElasticPress\Indexables::factory()->get( 'post' )->put_mapping();

		ElasticPress\Indexables::factory()->get( 'post' )->sync_manager->sync_queue = [];

		$this->setup_test_post_type();

		// Backup the original
		$this->original_post = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : '';
		$this->original_pagenow = $GLOBALS['pagenow'];
		$this->original_screen = get_current_screen();
	}

	/**
	 * Clean up after each test. Reset our mocks
	 *
	 * @since 2.1
	 */
	public function tearDown() {
		parent::tearDown();

		$this->fired_actions = array();

		// Restore the original
		$GLOBALS['post'] = $this->original_post;
		$GLOBALS['pagenow'] = $this->original_pagenow;
		if ( $this->original_screen instanceof \WP_Screen ) {
			set_current_screen( $this->original_screen );
		}
	}

	/**
	 * @return weighting sub-feature
	 */
	public function get_feature() {
		return ElasticPress\Features::factory()->get_registered_feature( 'searchordering' );
	}

	public function testConstruct() {
		$instance = new \ElasticPress\Feature\SearchOrdering\SearchOrdering();
		$this->assertSame( 'searchordering', $instance->slug );
		$this->assertSame( 'Custom Search Results', $instance->title );
	}

	public function testSetupWithSearchDisabled() {
		ElasticPress\Features::factory()->deactivate_feature( 'search' );
		$this->assertFalse( $this->get_feature()->setup() );
		ElasticPress\Features::factory()->activate_feature( 'search' );
	}

	public function testFilterUpdatedMessages() {
		$post_id = Functions\create_and_sync_post( array( 'post_content' => 'findme test 1' ) );
		$GLOBALS['post'] = get_post( $post_id );
		$messages = $this->get_feature()->filter_updated_messages([]);

		$this->assertArrayHasKey( 'ep-pointer', $messages );
	}

	public function testOutputFeatureBoxSummary() {
		ob_start();
		$this->get_feature()->output_feature_box_summary();
		$output = ob_get_clean();

		$this->assertContains( 'Insert specific posts into search results for specific search queries.', $output );
	}

	public function testOutputFeatureBoxLong() {
		ob_start();
		$this->get_feature()->output_feature_box_long();
		$output = ob_get_clean();

		$this->assertContains( 'Selected posts will be inserted into search results in the specified position.', $output );
	}

	public function testAdminMenu() {
		$site_url = trailingslashit( get_option( 'siteurl' ) );

		add_menu_page(
			'ElasticPress',
			'ElasticPress',
			'manage_options',
			'elasticpress'
		);

		$this->get_feature()->admin_menu();

		$menu = $GLOBALS['submenu'];

		$this->assertEquals( 2, count( $menu['elasticpress'] ) );
		$this->assertEquals( 'Custom Results', $menu['elasticpress'][1][0] );
	}

	public function testParentFile() {
		set_current_screen( 'front' );

		$this->assertSame( 'test_parent_file', $this->get_feature()->parent_file( 'test_parent_file' ) );
	}

	public function testSubmenuFile() {
		set_current_screen( 'front' );

		$this->assertSame( 'test_submenu_file', $this->get_feature()->submenu_file( 'test_submenu_file' ) );
	}

	public function testRegisterPostType() {
		$this->get_feature()->register_post_type();
		$post_types = get_post_types();
		$this->assertContains( 'ep-pointer', $post_types );

		$taxonomies = get_taxonomies();
		$this->assertContains( 'ep_custom_result', $taxonomies );
	}

	public function testRegisterMetaBox() {
		global $wp_meta_boxes;
		$this->get_feature()->register_meta_box();
		$this->assertArrayHasKey( 'ep-ordering', $wp_meta_boxes['ep-pointer-network']['normal']['default'] );
		$this->assertEquals( 'Manage Results', $wp_meta_boxes['ep-pointer-network']['normal']['default']['ep-ordering']['title'] );
	}

	public function testRenderMetaBox() {
		$post_id = Functions\create_and_sync_post( array( 'post_content' => 'findme test 1' ) );

		ob_start();
		$this->get_feature()->render_meta_box( get_post( $post_id ) );
		$output = ob_get_clean();
		$this->assertContains( 'ordering-app', $output );
	}

	public function testGetPointerData() {
		$post_id_1  = Functions\create_and_sync_post( array( 'post_content' => 'findme test 1' ) );
		$post_id_2  = Functions\create_and_sync_post( array( 'post_content' => 'findme test 2' ) );
		$pointer_id = Functions\create_and_sync_post( array( 'post_title' => 'findme' ) );

		update_post_meta( $pointer_id, 'pointers', [
			[ 'ID' => $post_id_1, 'order' => 1 ],
			[ 'ID' => $post_id_2, 'order' => 2 ],
		] );

		$GLOBALS['post'] = get_post( $pointer_id );

		$localized_data = $this->get_feature()->get_pointer_data_for_localize();

		$this->assertEquals( 2, count( $localized_data ) );
		$this->assertArrayHasKey( 'pointers', $localized_data );
		$this->assertArrayHasKey( 'posts', $localized_data );
		$this->assertEquals( $post_id_1, $localized_data['pointers'][0]['ID'] );
		$this->assertEquals( $post_id_2, $localized_data['pointers'][1]['ID'] );
		$this->assertInstanceOf( '\WP_Post', $localized_data['posts'][$post_id_1] );
		$this->assertInstanceOf( '\WP_Post', $localized_data['posts'][$post_id_2] );
	}

	public function testEnqueueScripts() {
		$this->assertFalse( wp_script_is( 'ep_ordering_scripts' ) );
		$GLOBALS['pagenow'] = 'post-new.php';
		set_current_screen( 'ep-pointer' );
		$this->get_feature()->admin_enqueue_scripts();
		$this->assertTrue( wp_script_is( 'ep_ordering_scripts' ) );
	}

	public function testSavePostEarlyReturn() {
		$pointer_id = Functions\create_and_sync_post( array( 'post_title' => 'findme' ) );
		$return = $this->get_feature()->save_post( $pointer_id, get_post( $pointer_id ) );
		$this->assertNull( $return );

		wp_set_current_user($this->factory->user->create( array( 'role' => 'subscriber' ) ) );
		$_POST = [ 'search-ordering-nonce' => wp_create_nonce( 'save-search-ordering' ) ];

		$return = $this->get_feature()->save_post( $pointer_id, get_post( $pointer_id ) );
		$this->assertNull( $return );

	}

	public function testSavePost() {
		$post_id_1  = Functions\create_and_sync_post( array( 'post_content' => 'findme test 1' ) );
		$post_id_2  = Functions\create_and_sync_post( array( 'post_content' => 'findme test 2' ) );
		$pointer_id = wp_insert_post( [ 'post_title' => 'findme', 'post_status' => 'publish', 'post_type' => 'ep-pointer' ] );

		$_POST = [
			'search-ordering-nonce' => wp_create_nonce( 'save-search-ordering' ),
			'ordered_posts' => json_encode( [
				[ 'ID' => $post_id_1, 'order' => 1 ],
				[ 'ID' => $post_id_2, 'order' => 2 ],
			] ),
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
		$post_id_3  = Functions\create_and_sync_post( array( 'post_content' => '10up test 1' ) );
		$post_id_4  = Functions\create_and_sync_post( array( 'post_content' => '10up test 2' ) );
		$_POST = [
			'search-ordering-nonce' => wp_create_nonce( 'save-search-ordering' ),
			'ordered_posts' => json_encode( [
				[ 'ID' => $post_id_3, 'order' => 1 ],
				[ 'ID' => $post_id_4, 'order' => 2 ],
				[ 'ID' => $post_id_2, 'order' => 3 ],
			] ),
		];

		wp_update_post( [
			'ID'         => $pointer_id,
			'post_title' => '10up',
		] );

		$this->get_feature()->save_post( $pointer_id, get_post( $pointer_id ) );
		$this->assertEquals( '10up', get_post_meta( $pointer_id, 'search_term', true ) );

		$this->assertFalse( get_the_terms( $post_id_1, 'ep_custom_result' ) );
	}

	public function testSaveUnpublishedPost() {
		$post_id_1  = Functions\create_and_sync_post( [ 'post_content' => 'findme test 1' ] );
		$post_id_2  = Functions\create_and_sync_post( [ 'post_content' => 'findme test 2' ] );
		$pointer_id = wp_insert_post( [ 'post_title' => 'findme', 'post_status' => 'draft', 'post_type' => 'ep-pointer' ] );

		$_POST = [
			'search-ordering-nonce' => wp_create_nonce( 'save-search-ordering' ),
			'ordered_posts' => json_encode( [
				[ 'ID' => $post_id_1, 'order' => 1 ],
				[ 'ID' => $post_id_2, 'order' => 2 ],
			] ),
		];

		$this->get_feature()->save_post( $pointer_id, get_post( $pointer_id ) );

		$pointers_data = get_post_meta( $pointer_id, 'pointers', true );

		$this->assertFalse( get_the_terms( $post_id_1, 'ep_custom_result' ) );
	}

	public function testSavePostMaxCustomResults() {
		update_option( 'posts_per_page', 2 );
		$post_id_1  = Functions\create_and_sync_post( [ 'post_content' => 'findme test 1' ] );
		$post_id_2  = Functions\create_and_sync_post( [ 'post_content' => 'findme test 2' ] );
		$post_id_3  = Functions\create_and_sync_post( [ 'post_content' => 'findme test 3' ] );
		$pointer_id = wp_insert_post( [ 'post_title' => 'findme', 'post_status' => 'publish', 'post_type' => 'ep-pointer' ] );

		$_POST = [
			'search-ordering-nonce' => wp_create_nonce( 'save-search-ordering' ),
			'ordered_posts' => json_encode( [
				[ 'ID' => $post_id_1, 'order' => 1 ],
				[ 'ID' => $post_id_2, 'order' => 2 ],
				[ 'ID' => $post_id_3, 'order' => 3 ],
			] ),
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

	public function testCreateTermFailed() {
		$create_term_failed = function() {
			return new \WP_Error( 'test_error' );
		};

		add_filter( 'pre_insert_term', $create_term_failed );

		$this->assertFalse( $this->get_feature()->create_or_return_custom_result_term( 'test' ) );

		remove_filter( 'pre_insert_term', $create_term_failed );
	}

	public function testExcludeCustomResultsWeightingFields() {
		$fields = [
			'taxonomies' => [
				'children' => [
					'terms.category.name' => [],
					'terms.post_tag.name' => [],
					'terms.ep_custom_result.name' => [],
				],
			],
		];

		$result = $this->get_feature()->weighting_fields_for_post_type( $fields, 'post' );

		$this->assertNotContains(  'terms.ep_custom_result.name', $result['taxonomies']['children'] );
		$this->assertEquals( 2, count( $result['taxonomies']['children'] ) );
	}

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

	public function testFilterEnterTitleHere() {
		$this->assertEquals( 'Nothing changes', $this->get_feature()->filter_enter_title_here( 'Nothing changes' ) );

		$pointer_id = wp_insert_post( [ 'post_title' => 'findme', 'post_status' => 'publish', 'post_type' => 'ep-pointer' ] );

		$GLOBALS['post'] = get_post( $pointer_id );

		$this->assertEquals( 'Enter Search Query', $this->get_feature()->filter_enter_title_here( 'Nothing changes' ) );
	}

	public function testFilterColumnNames() {
		$columns = [ 'title' => 'Post title' ];
		$result = $this->get_feature()->filter_column_names( $columns );

		$this->assertArrayHasKey( 'title', $result );
		$this->assertEquals( 'Search Query', $result['title'] );
	}

	public function testPostsResults() {
		ElasticPress\Features::factory()->activate_feature( 'search' );
		ElasticPress\Features::factory()->setup_features();
		ElasticPress\Features::factory()->get_registered_feature( 'search' )->search_setup();

		$post_id_1  = Functions\create_and_sync_post( [ 'post_content' => 'findme test 1' ] );
		$post_id_2  = Functions\create_and_sync_post( [ 'post_content' => 'findme test 2' ] );
		$post_id_3  = Functions\create_and_sync_post( [ 'post_content' => 'findme test 3' ] );


		$pointer_id = wp_insert_post( [ 'post_title' => 'findme', 'post_status' => 'publish', 'post_type' => 'ep-pointer' ] );

		$_POST = [
			'search-ordering-nonce' => wp_create_nonce( 'save-search-ordering' ),
			'ordered_posts' => json_encode( [
				[ 'ID' => $post_id_2, 'order' => 1 ],
			] ),
		];

		$this->get_feature()->save_post( $pointer_id, get_post( $pointer_id ) );

		ElasticPress\Indexables::factory()->get( 'post' )->index( $post_id_2, true );
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$query = new \WP_Query( [ 's' => 'findme' ] );

		$new_posts = $this->get_feature()->posts_results( $query->posts, $query );

		$this->assertEquals( 3, count( $new_posts ) );
		$this->assertEquals( $post_id_2, $new_posts[0]->ID );
	}

	public function testRestApiInit() {
		global $wp_rest_server;
		add_filter( 'rest_url', [ $this, 'filter_rest_url_for_leading_slash' ], 10, 2 );
		/** @var WP_REST_Server $wp_rest_server */
		$wp_rest_server = new \WP_REST_Server;
		do_action( 'rest_api_init', $wp_rest_server );

		$routes = $wp_rest_server->get_routes();
		$this->assertArrayHasKey( '/elasticpress/v1', $routes );
		$this->assertArrayHasKey( '/elasticpress/v1/pointer_search', $routes );
		$this->assertArrayHasKey( '/elasticpress/v1/pointer_preview', $routes );

		$request = new \WP_REST_Request( 'GET', '/elasticpress/v1/pointer_search' );
		$response = $wp_rest_server->dispatch( $request );
		$this->assertEquals( 400, $response->get_status() );

		$request = new \WP_REST_Request( 'GET', '/elasticpress/v1/pointer_preview' );
		$response = $wp_rest_server->dispatch( $request );
		$this->assertEquals( 400, $response->get_status() );

		remove_filter( 'rest_url', [ $this, 'filter_rest_url_for_leading_slash' ], 10, 2 );
	}

	public function filter_rest_url_for_leading_slash( $url, $path ) {
		if ( is_multisite() || get_option( 'permalink_structure' ) ) {
			return $url;
		}

		// Make sure path for rest_url has a leading slash for proper resolution.
		$this->assertStringStartsWith( '/', $path, 'REST API URL should have a leading slash.' );

		return $url;
	}

	public function testHandlePointerSearch() {
		ElasticPress\Features::factory()->activate_feature( 'search' );
		ElasticPress\Features::factory()->setup_features();
		ElasticPress\Features::factory()->get_registered_feature( 'search' )->search_setup();

		$post_id_1  = Functions\create_and_sync_post( [ 'post_content' => 'findme test 1' ] );
		$post_id_2  = Functions\create_and_sync_post( [ 'post_content' => 'findme test 2' ] );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$request = new \WP_REST_Request( 'GET', '/elasticpress/v1/pointer_search' );
		$request->set_param( 's', 'findme' );

		$response = $this->get_feature()->handle_pointer_search( $request );

		$post_ids = wp_list_pluck( $response, 'ID' );

		$this->assertContains( $post_id_1, $post_ids );
		$this->assertContains( $post_id_2, $post_ids );
	}

	public function testHandlePostTrash() {
		$post_id_1  = Functions\create_and_sync_post( array( 'post_content' => 'findme test 1' ) );
		$post_id_2  = Functions\create_and_sync_post( array( 'post_content' => 'findme test 2' ) );
		$pointer_id = wp_insert_post( [ 'post_title' => 'findme', 'post_status' => 'publish', 'post_type' => 'ep-pointer' ] );

		// Test non ep-pointer post type.
		$this->assertNull( $this->get_feature()->handle_post_trash( $post_id_1) );

		// Test empty pointers
		$this->assertNull( $this->get_feature()->handle_post_trash( $pointer_id) );

		$_POST = [
			'search-ordering-nonce' => wp_create_nonce( 'save-search-ordering' ),
			'ordered_posts' => json_encode( [
				[ 'ID' => $post_id_1, 'order' => 1 ],
				[ 'ID' => $post_id_2, 'order' => 2 ],
			] ),
		];

		$this->get_feature()->save_post( $pointer_id, get_post( $pointer_id ) );
		$this->assertContains( 'findme', wp_list_pluck( get_the_terms( $post_id_1, 'ep_custom_result' ), 'name' ) );

		$this->get_feature()->handle_post_trash( $pointer_id );

		$this->assertFalse( get_the_terms( $post_id_1, 'ep_custom_result' ) );
	}

	public function testHandlePostUntrash() {
		$post_id_1  = Functions\create_and_sync_post( array( 'post_content' => 'findme test 1' ) );
		$post_id_2  = Functions\create_and_sync_post( array( 'post_content' => 'findme test 2' ) );
		$pointer_id = wp_insert_post( [ 'post_title' => 'findme', 'post_status' => 'publish', 'post_type' => 'ep-pointer' ] );

		$_POST = [
			'search-ordering-nonce' => wp_create_nonce( 'save-search-ordering' ),
			'ordered_posts' => json_encode( [
				[ 'ID' => $post_id_1, 'order' => 1 ],
				[ 'ID' => $post_id_2, 'order' => 2 ],
			] ),
		];

		$this->get_feature()->save_post( $pointer_id, get_post( $pointer_id ) );

		$this->get_feature()->handle_post_trash( $pointer_id );

		// Test non ep-pointer post type.
		$this->assertNull( $this->get_feature()->handle_post_untrash( $post_id_1) );

		$this->get_feature()->handle_post_untrash( $pointer_id );

		$this->assertContains( 'findme', wp_list_pluck( get_the_terms( $post_id_1, 'ep_custom_result' ), 'name' ) );
		$this->assertContains( 'findme', wp_list_pluck( get_the_terms( $post_id_2, 'ep_custom_result' ), 'name' ) );
	}
}
