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
		ElasticPress\Features::factory()->activate_feature( 'search' );
		ElasticPress\Features::factory()->activate_feature( 'searchordering' );
	}

	/**
	 * Clean up after each test. Reset our mocks
	 *
	 * @since 2.1
	 */
	public function tearDown() {
		parent::tearDown();

		// make sure no one attached to this
		remove_filter( 'ep_sync_terms_allow_hierarchy', array( $this, 'ep_allow_multiple_level_terms_sync' ), 100 );
		$this->fired_actions = array();
	}

	/**
	 * @return weighting sub-feature
	 */
	public function get_feature() {
		return ElasticPress\Features::factory()->get_registered_feature( 'searchordering' );
	}

	public function testConstruct() {
		$instance = new \ElasticPress\Feature\SearchOrdering\SearchOrdering();
		$this->assertEquals( 'searchordering', $instance->slug );
		$this->assertEquals( 'Custom Search Results', $instance->title );
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

		$this->assertNotFalse( strpos( $output, 'Insert specific posts into search results for specific search queries.') );
	}

	public function testOutputFeatureBoxLong() {
		ob_start();
		$this->get_feature()->output_feature_box_long();
		$output = ob_get_clean();

		$this->assertNotFalse( strpos( $output, 'Selected posts will be inserted into search results in the specified position.') );
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

		$this->assertEquals( 'test_parent_file', $this->get_feature()->parent_file( 'test_parent_file' ) );
	}

	public function testSubmenuFile() {
		set_current_screen( 'front' );

		$this->assertEquals( 'test_submenu_file', $this->get_feature()->submenu_file( 'test_submenu_file' ) );
	}

	public function testRegisterPostType() {
		$post_types = get_post_types();
		$this->assertTrue( in_array( 'ep-pointer', $post_types ) );

		$taxonomies = get_taxonomies();
		$this->assertTrue( in_array( 'ep_custom_result', $taxonomies ) );
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
		$this->assertNotFalse( strpos( $output, 'ordering-app' ) );
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
		$this->assertTrue( $localized_data['posts'][$post_id_1] instanceof \WP_Post );
		$this->assertTrue( $localized_data['posts'][$post_id_2] instanceof \WP_Post );
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
		$this->assertTrue( in_array( 'findme', $terms ) );

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

		$this->assertFalse( get_the_terms( $post_id_1, 'ep_custom_result' ), 'name' );
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
		$this->assertTrue( in_array( 'findme', wp_list_pluck( get_the_terms( $post_id_1, 'ep_custom_result' ), 'name' ) ) );
		$this->assertFalse( get_the_terms( $post_id_3, 'ep_custom_result' ), 'name' );
	}

	public function testCreateTermFailed() {
		add_filter( 'pre_insert_term', function() {
			return new \WP_Error( 'test_error' );
		} );

		$this->assertFalse( $this->get_feature()->create_or_return_custom_result_term( 'test' ) );
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

		$this->assertFalse( in_array( 'terms.ep_custom_result.name', $result['taxonomies']['children'] ) );
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
}
