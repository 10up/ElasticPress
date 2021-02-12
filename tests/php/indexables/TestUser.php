<?php
/**
 * Test user indexable functionality
 *
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;

/**
 * Test user indexable class
 */
class TestUser extends BaseTestCase {
	/**
	 * Checking if HTTP request returns 404 status code.
	 *
	 * @var boolean
	 */
	public $is_404 = false;

	/**
	 * Setup each test.
	 *
	 * @since 0.1.0
	 */
	public function setUp() {
		global $wpdb;
		parent::setUp();
		$wpdb->suppress_errors();

		ElasticPress\Features::factory()->activate_feature( 'users' );
		ElasticPress\Features::factory()->setup_features();

		ElasticPress\Indexables::factory()->get( 'user' )->delete_index();
		ElasticPress\Indexables::factory()->get( 'user' )->put_mapping();

		ElasticPress\Indexables::factory()->get( 'user' )->sync_manager->sync_queue = [];

		$admin_id = $this->factory->user->create(
			[
				'role'          => 'administrator',
				'user_login'    => 'test_admin',
				'first_name'    => 'Mike',
				'last_name'     => 'Mickey',
				'display_name'  => 'mikey',
				'user_email'    => 'mikey@gmail.com',
				'user_nicename' => 'mike',
			]
		);

		grant_super_admin( $admin_id );

		wp_set_current_user( $admin_id );

		// Need to call this since it's hooked to init
		ElasticPress\Features::factory()->get_registered_feature( 'users' )->search_setup();
	}

	/**
	 * Create and index users for testing
	 *
	 * @since 3.0
	 */
	public function createAndIndexUsers() {
		ElasticPress\Indexables::factory()->get( 'user' )->sync_manager->add_to_queue( 1 );

		ElasticPress\Indexables::factory()->get( 'user' )->bulk_index( array_keys( ElasticPress\Indexables::factory()->get( 'user' )->sync_manager->sync_queue ) );

		$user_1 = Functions\create_and_sync_user(
			[
				'user_login'   => 'user1-author',
				'role'         => 'author',
				'first_name'   => 'Dave',
				'last_name'    => 'Smith',
				'display_name' => 'dave',
				'user_email'   => 'dave@gmail.com',
			],
			[
				'user_1_key' => 'value1',
				'user_num'   => 5,
				'long_key'   => 'here is a text field',
			]
		);

		$user_2 = Functions\create_and_sync_user(
			[
				'user_login'   => 'user2-contributor',
				'role'         => 'contributor',
				'first_name'   => 'Zoey',
				'last_name'    => 'Johnson',
				'display_name' => 'Zoey',
				'user_email'   => 'zoey@gmail.com',
				'user_url'     => 'http://google.com',
			],
			[
				'user_2_key' => 'value2',
			]
		);

		$user_3 = Functions\create_and_sync_user(
			[
				'user_login'   => 'user3-editor',
				'role'         => 'editor',
				'first_name'   => 'Joe',
				'last_name'    => 'Doe',
				'display_name' => 'joe',
				'user_email'   => 'joe@gmail.com',
			],
			[
				'user_3_key' => 'value3',
				'user_num'   => 5,
			]
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();
	}

	/**
	 * Clean up after each test. Reset our mocks
	 *
	 * @since 0.1.0
	 */
	public function tearDown() {
		parent::tearDown();

		// make sure no one attached to this
		remove_filter( 'ep_sync_terms_allow_hierarchy', array( $this, 'ep_allow_multiple_level_terms_sync' ), 100 );
		$this->fired_actions = array();
	}

	/**
	 * Test the building of index mappings
	 * 
	 * @since 3.6
	 * @group user
	 */
	public function testUserBuildMapping() {
		$mapping_and_settings = ElasticPress\Indexables::factory()->get( 'user' )->build_mapping();

		// The mapping is currently expected to have both `mappings` and `settings` elements
		$this->assertArrayHasKey( 'settings', $mapping_and_settings, 'Built mapping is missing settings array' );
		$this->assertArrayHasKey( 'mappings', $mapping_and_settings, 'Built mapping is missing mapping array' );
	}

	/**
	 * Test the building of index settings
	 * 
	 * @since 3.6
	 * @group post
	 */
	public function testUserBuildSettings() {
		$settings = ElasticPress\Indexables::factory()->get( 'user' )->build_settings();

		$expected_keys = array(
			'index.number_of_shards',
			'index.number_of_replicas',
			'index.mapping.total_fields.limit',
			'index.max_shingle_diff',
			'index.max_result_window',
			'index.mapping.ignore_malformed',
			'analysis',
		);

		$actual_keys = array_keys( $settings );

		$diff = array_diff( $expected_keys, $actual_keys );

		$this->assertEquals( $diff, array() );
	}

	/**
	 * Test a simple user sync
	 *
	 * @since 3.0
	 * @group user
	 */
	public function testUserSync() {
		add_action(
			'ep_sync_user_on_transition',
			function() {
				$this->fired_actions['ep_sync_user_on_transition'] = true;
			}
		);

		ElasticPress\Indexables::factory()->get( 'user' )->sync_manager->sync_queue = [];

		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );

		$this->assertEquals( 1, count( ElasticPress\Indexables::factory()->get( 'user' )->sync_manager->sync_queue ) );

		ElasticPress\Indexables::factory()->get( 'user' )->index( $user_id );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$this->assertTrue( ! empty( $this->fired_actions['ep_sync_user_on_transition'] ) );

		$user = ElasticPress\Indexables::factory()->get( 'user' )->get( $user_id );
		$this->assertTrue( ! empty( $user ) );
	}

	/**
	 * Test a simple user sync with meta
	 *
	 * @since 3.0
	 * @group user
	 */
	public function testUserSyncMeta() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );

		update_user_meta( $user_id, 'new_meta', 'test' );

		ElasticPress\Indexables::factory()->get( 'user' )->index( $user_id );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$user = ElasticPress\Indexables::factory()->get( 'user' )->get( $user_id );

		$this->assertEquals( 'test', $user['meta']['new_meta'][0]['value'] );
	}

	/**
	 * Test a simple user sync on meta update
	 *
	 * @since 3.0
	 * @group user
	 */
	public function testUserSyncOnMetaUpdate() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );

		ElasticPress\Indexables::factory()->get( 'user' )->sync_manager->sync_queue = [];

		update_user_meta( $user_id, 'test_key', true );

		$this->assertEquals( 1, count( ElasticPress\Indexables::factory()->get( 'user' )->sync_manager->sync_queue ) );
		$this->assertTrue( ! empty( ElasticPress\Indexables::factory()->get( 'user' )->sync_manager->add_to_queue( $user_id ) ) );
	}

	/**
	 * Test user sync kill. Note we can't actually check Elasticsearch here due to how the
	 * code is structured.
	 *
	 * @since 3.0
	 * @group user
	 */
	public function testUserSyncKill() {
		$created_user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );

		add_action(
			'ep_sync_user_on_transition',
			function() {
				$this->fired_actions['ep_sync_user_on_transition'] = true;
			}
		);

		add_filter(
			'ep_user_sync_kill',
			function( $kill, $user_id ) use ( $created_user_id ) {
				if ( $created_user_id === $user_id ) {
					return true;
				}

				return $kill;
			},
			10,
			2
		);

		ElasticPress\Indexables::factory()->get( 'user' )->sync_manager->action_sync_on_update( $created_user_id );

		$this->assertTrue( empty( $this->fired_actions['ep_sync_user_on_transition'] ) );
	}

	/**
	 * Test a basic user query with and without ElasticPress
	 *
	 * @since 3.0
	 * @group user
	 */
	public function testBasicUserQuery() {
		$this->createAndIndexUsers();

		// First try without ES and make sure everything is right.
		$user_query = new \WP_User_Query(
			[
				'number' => 10,
			]
		);

		foreach ( $user_query->results as $user ) {
			$this->assertTrue( empty( $user->elasticsearch ) );
		}

		$this->assertEquals( 5, count( $user_query->results ) );
		$this->assertEquals( 5, $user_query->total_users );

		// Now try with Elasticsearch.
		$user_query = new \WP_User_Query(
			[
				'ep_integrate' => true,
				'number'       => 10,
			]
		);

		foreach ( $user_query->results as $user ) {
			$this->assertTrue( $user->elasticsearch );
		}

		$this->assertEquals( 5, count( $user_query->results ) );
		$this->assertEquals( 5, $user_query->total_users );
	}

	/**
	 * Test user query number parameter
	 *
	 * @since 3.0
	 * @group user
	 */
	public function testUserQueryNumber() {
		$this->createAndIndexUsers();

		$user_query = new \WP_User_Query(
			[
				'ep_integrate' => true,
				'number'       => 1,
			]
		);

		$this->assertEquals( 1, count( $user_query->results ) );
		$this->assertEquals( 5, $user_query->total_users );

		for ( $i = 1; $i <= 15; $i++ ) {
			Functions\create_and_sync_user(
				[
					'user_login' => 'user' . $i . '-editor',
					'role'       => 'administrator',
				]
			);
		}

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$user_query = new \WP_User_Query(
			[
				'ep_integrate' => true,
			]
		);

		$this->assertEquals( 19, count( $user_query->results ) );
		$this->assertEquals( 19, $user_query->total_users );
	}

	/**
	 * Test user query number parameter
	 *
	 * @since 3.0
	 * @group user
	 */
	public function testUserQueryOffset() {
		$this->createAndIndexUsers();

		$user_query = new \WP_User_Query(
			[
				'ep_integrate' => true,
				'number'       => 1,
			]
		);

		$first_user = $user_query->results[0];

		$user_query = new \WP_User_Query(
			[
				'ep_integrate' => true,
				'number'       => 1,
				'offset'       => 1,
			]
		);

		$this->assertNotEquals( $first_user->ID, $user_query->results[0]->ID );
	}

	/**
	 * Test user query paged parameter
	 *
	 * @since 3.0
	 * @group user
	 */
	public function testUserQueryPaged() {
		$this->createAndIndexUsers();

		$user_query = new \WP_User_Query(
			[
				'ep_integrate' => true,
				'number'       => 1,
			]
		);

		$first_user = $user_query->results[0];

		$user_query = new \WP_User_Query(
			[
				'ep_integrate' => true,
				'number'       => 1,
				'paged'        => 2,
			]
		);

		$this->assertNotEquals( $first_user->ID, $user_query->results[0]->ID );
	}

	/**
	 * Test user query role paramter
	 *
	 * @since 3.0
	 * @group user
	 */
	public function testUserQueryRole() {
		$this->createAndIndexUsers();

		$user_query = new \WP_User_Query(
			[
				'ep_integrate' => true,
				'role'         => 'editor',
			]
		);

		$this->assertEquals( 1, $user_query->total_users );
		$this->assertTrue( in_array( 'editor', $user_query->results[0]->roles, true ) );
	}

	/**
	 * Test user query include parameter
	 *
	 * @since 3.0
	 * @group user
	 */
	public function testUserInclude() {
		$this->createAndIndexUsers();

		$user_query = new \WP_User_Query(
			[
				'ep_integrate' => true,
				'include'      => [ 1 ],
			]
		);

		$this->assertEquals( 1, $user_query->total_users );
		$this->assertEquals( 1, $user_query->results[0]->ID );
	}

	/**
	 * Test user query exclude parameter
	 *
	 * @since 3.0
	 * @group user
	 */
	public function testUserExclude() {
		$this->createAndIndexUsers();

		$user_query = new \WP_User_Query(
			[
				'ep_integrate' => true,
				'exclude'      => [ 1 ],
			]
		);

		$this->assertEquals( 4, $user_query->total_users );
	}

	/**
	 * Test user query login parameter
	 *
	 * @since 3.0
	 * @group user
	 */
	public function testUserQueryLogin() {
		$this->createAndIndexUsers();

		$user_query = new \WP_User_Query(
			[
				'ep_integrate' => true,
				'login'        => 'test_admin',
			]
		);

		$this->assertEquals( 1, $user_query->total_users );
		$this->assertEquals( 'test_admin', $user_query->results[0]->user_login );
	}

	/**
	 * Test user query login__in paramter
	 *
	 * @since 3.0
	 * @group user
	 */
	public function testUserQueryLoginIn() {
		$this->createAndIndexUsers();

		$user_query = new \WP_User_Query(
			[
				'ep_integrate' => true,
				'login__in'    => [ 'test_admin' ],
			]
		);

		$this->assertEquals( 1, $user_query->total_users );
		$this->assertEquals( 'test_admin', $user_query->results[0]->user_login );
	}

	/**
	 * Test user query login__not_in paramter
	 *
	 * @since 3.0
	 * @group user
	 */
	public function testUserQueryLoginNotIn() {
		$this->createAndIndexUsers();

		$user_query = new \WP_User_Query(
			[
				'ep_integrate'  => true,
				'login__not_in' => [ 'test_admin' ],
			]
		);

		$this->assertEquals( 4, $user_query->total_users );
	}

	/**
	 * Test user query nicename parameter
	 *
	 * @since 3.0
	 * @group user
	 */
	public function testUserQueryNicename() {
		$this->createAndIndexUsers();

		$user_query = new \WP_User_Query(
			[
				'ep_integrate' => true,
				'nicename'     => 'mike',
			]
		);

		$this->assertEquals( 1, $user_query->total_users );
		$this->assertEquals( 'mike', $user_query->results[0]->user_nicename );
	}

	/**
	 * Test user query nicename__in parameter
	 *
	 * @since 3.0
	 * @group user
	 */
	public function testUserQueryNicenameIn() {
		$this->createAndIndexUsers();

		$user_query = new \WP_User_Query(
			[
				'ep_integrate' => true,
				'nicename__in' => [ 'mike' ],
			]
		);

		$this->assertEquals( 1, $user_query->total_users );
		$this->assertEquals( 'mike', $user_query->results[0]->user_nicename );
	}

	/**
	 * Test user query nicename__in parameter
	 *
	 * @since 3.0
	 * @group user
	 */
	public function testUserQueryNicenameNotIn() {
		$this->createAndIndexUsers();

		$user_query = new \WP_User_Query(
			[
				'ep_integrate'     => true,
				'nicename__not_in' => [ 'mike' ],
			]
		);

		$this->assertEquals( 4, $user_query->total_users );
	}

	/**
	 * Test user query role__not_in paramter
	 *
	 * @since 3.0
	 * @group user
	 */
	public function testUserQueryRoleNotIn() {
		$this->createAndIndexUsers();

		$user_query = new \WP_User_Query(
			[
				'ep_integrate' => true,
				'role__not_in' => [ 'editor' ],
			]
		);

		foreach ( $user_query->results as $user ) {
			$this->assertFalse( in_array( 'editor', $user_query->results[0]->roles, true ) );
		}
	}

	/**
	 * Test user query role__in paramter
	 *
	 * @since 3.0
	 * @group user
	 */
	public function testUserQueryRoleIn() {
		$this->createAndIndexUsers();

		$user_query = new \WP_User_Query(
			[
				'ep_integrate' => true,
				'role__in'     => [
					'editor',
					'author',
				],
			]
		);

		foreach ( $user_query->results as $user ) {
			$this->assertTrue( ( in_array( 'editor', $user_query->results[0]->roles, true ) || in_array( 'author', $user_query->results[0]->roles, true ) ) );
		}
	}

	/**
	 * Test user query orderby paramter where we are ordering by display name
	 *
	 * @since 3.0
	 * @group user
	 */
	public function testUserQueryOrderbyDisplayName() {
		$this->createAndIndexUsers();

		$user_query = new \WP_User_Query(
			[
				'ep_integrate' => true,
				'orderby'      => 'display_name',
			]
		);

		foreach ( $user_query->results as $key => $user ) {
			if ( ! empty( $user_query->results[ $key - 1 ] ) ) {
				$this->assertTrue( strcasecmp( $user_query->results[ $key - 1 ]->display_name, $user->display_name ) < 0 );
			}
		}
	}

	/**
	 * Test user query orderby paramter where we are ordering by ID
	 *
	 * @since 3.0
	 * @group user
	 */
	public function testUserQueryOrderbyID() {
		$this->createAndIndexUsers();

		$user_query = new \WP_User_Query(
			[
				'ep_integrate' => true,
				'orderby'      => 'ID',
			]
		);

		foreach ( $user_query->results as $key => $user ) {
			if ( ! empty( $user_query->results[ $key - 1 ] ) ) {
				$this->assertTrue( $user_query->results[ $key - 1 ]->ID < $user->ID );
			}
		}
	}

	/**
	 * Test user query orderby paramter where we are ordering by email
	 *
	 * @since 3.0
	 * @group user
	 */
	public function testUserQueryOrderbyEmail() {
		$this->createAndIndexUsers();

		$user_query = new \WP_User_Query(
			[
				'ep_integrate' => true,
				'orderby'      => 'email',
			]
		);

		foreach ( $user_query->results as $key => $user ) {
			if ( ! empty( $user_query->results[ $key - 1 ] ) ) {
				$this->assertTrue( strcasecmp( $user_query->results[ $key - 1 ]->user_email, $user->user_email ) < 0 );
			}
		}
	}

	/**
	 * Test user query order parameter where we are ordering by ID descending
	 *
	 * @since 3.0
	 * @group user
	 */
	public function testUserQueryOrderDesc() {
		$this->createAndIndexUsers();

		$user_query = new \WP_User_Query(
			[
				'ep_integrate' => true,
				'orderby'      => 'ID',
				'order'        => 'desc',
			]
		);

		foreach ( $user_query->results as $key => $user ) {
			if ( ! empty( $user_query->results[ $key - 1 ] ) ) {
				$this->assertTrue( $user_query->results[ $key - 1 ]->ID > $user->ID );
			}
		}
	}

	/**
	 * Test meta query with simple args
	 *
	 * @since 3.0
	 */
	public function testUserMetaQuerySimple() {
		$this->createAndIndexUsers();

		// Value does not exist so should return nothing
		$user_query = new \WP_User_Query(
			[
				'ep_integrate' => true,
				'meta_key'     => 'user_1_key',
				'meta_value'   => 'value5',
			]
		);

		$this->assertEquals( 0, $user_query->total_users );

		// This value exists
		$user_query = new \WP_User_Query(
			[
				'ep_integrate' => true,
				'meta_key'     => 'user_1_key',
				'meta_value'   => 'value1',
			]
		);

		$this->assertEquals( 1, $user_query->total_users );
		$this->assertEquals( 'value1', get_user_meta( $user_query->results[0]->ID, 'user_1_key', true ) );
	}

	/**
	 * Test meta query with simple args and meta_compare does not equal
	 *
	 * @since 3.0
	 */
	public function testUserMetaQuerySimpleCompare() {
		$this->createAndIndexUsers();

		$user_query = new \WP_User_Query(
			[
				'ep_integrate' => true,
				'meta_key'     => 'user_1_key',
				'meta_value'   => 'value1',
				'meta_compare' => '!=',
			]
		);

		$this->assertEquals( 4, $user_query->total_users );
	}

	/**
	 * Test meta query with no compare
	 *
	 * @since 3.0
	 */
	public function testUserMetaQueryNoCompare() {
		$this->createAndIndexUsers();

		$user_query = new \WP_User_Query(
			[
				'ep_integrate' => true,
				'meta_query'   => [
					[
						'key'   => 'user_1_key',
						'value' => 'value1',
					],
				],
			]
		);

		$this->assertEquals( 1, $user_query->total_users );
		$this->assertEquals( 'value1', get_user_meta( $user_query->results[0]->ID, 'user_1_key', true ) );
	}

	/**
	 * Test meta query compare equals
	 *
	 * @since 3.0
	 */
	public function testUserMetaQueryCompareEquals() {
		$this->createAndIndexUsers();

		$user_query = new \WP_User_Query(
			[
				'ep_integrate' => true,
				'meta_query'   => [
					[
						'key'     => 'user_2_key',
						'value'   => 'value2',
						'compare' => '=',
					],
				],
			]
		);

		$this->assertEquals( 1, $user_query->total_users );
		$this->assertEquals( 'value2', get_user_meta( $user_query->results[0]->ID, 'user_2_key', true ) );
	}

	/**
	 * Test meta query with multiple statements
	 *
	 * @since 3.0
	 */
	public function testUserMetaQueryMulti() {
		$this->createAndIndexUsers();

		$user_query = new \WP_User_Query(
			[
				'ep_integrate' => true,
				'meta_query'   => [
					[
						'key'   => 'user_num',
						'value' => 5,
					],
					[
						'key'   => 'user_1_key',
						'value' => 'value1',
					],
				],
			]
		);

		$this->assertEquals( 1, $user_query->total_users );
		$this->assertEquals( 'value1', get_user_meta( $user_query->results[0]->ID, 'user_1_key', true ) );
	}

	/**
	 * Test meta query with multiple statements and relation OR
	 *
	 * @since 3.0
	 */
	public function testUserMetaQueryMultiRelationOr() {
		$this->createAndIndexUsers();

		$user_query = new \WP_User_Query(
			[
				'ep_integrate' => true,
				'meta_query'   => [
					[
						'key'   => 'user_num',
						'value' => 5,
					],
					[
						'key'   => 'user_1_key',
						'value' => 'value1',
					],
					'relation' => 'or',
				],
			]
		);

		$this->assertEquals( 2, $user_query->total_users );
	}

	/**
	 * Test meta query with multiple statements and relation AND
	 *
	 * @since 3.0
	 */
	public function testUserMetaQueryMultiRelationAnd() {
		$this->createAndIndexUsers();

		$user_query = new \WP_User_Query(
			[
				'ep_integrate' => true,
				'meta_query'   => [
					[
						'key'   => 'user_num',
						'value' => 5,
					],
					[
						'key'   => 'user_1_key',
						'value' => 'value1',
					],
					'relation' => 'and',
				],
			]
		);

		$this->assertEquals( 1, $user_query->total_users );
	}

	/**
	 * Test basic user search
	 *
	 * @since 3.0
	 */
	public function testBasicUserSearch() {
		$this->createAndIndexUsers();

		$user_query = new \WP_User_Query(
			[
				'search' => 'joe',
			]
		);

		$this->assertEquals( 1, $user_query->total_users );
		$this->assertEquals( 'user3-editor', $user_query->results[0]->user_login );
	}

	/**
	 * Test basic user search via user login
	 *
	 * @since 3.0
	 */
	public function testBasicUserSearchUserLogin() {
		$this->createAndIndexUsers();

		$user_query = new \WP_User_Query(
			[
				'search' => 'joe',
			]
		);

		$this->assertEquals( 1, $user_query->total_users );
		$this->assertEquals( 'user3-editor', $user_query->results[0]->user_login );
	}

	/**
	 * Test basic user search via user url
	 *
	 * @since 3.0
	 */
	public function testBasicUserSearchUserUrl() {
		$this->createAndIndexUsers();

		$user_query = new \WP_User_Query(
			[
				'search'        => 'http://google.com',
				'search_fields' => [
					'user_url.raw',
				],
			]
		);

		$this->assertEquals( 1, $user_query->total_users );
		$this->assertEquals( 'user2-contributor', $user_query->results[0]->user_login );
	}

	/**
	 * Test basic user search via meta
	 *
	 * @since 3.0
	 */
	public function testBasicUserSearchMeta() {
		$this->createAndIndexUsers();

		$user_query = new \WP_User_Query(
			[
				'search'        => 'test field',
				'search_fields' => [
					'meta.long_key.value',
				],
			]
		);

		$this->assertEquals( 1, $user_query->total_users );
		$this->assertEquals( 'user1-author', $user_query->results[0]->user_login );
	}

	/**
	 * Tests a single field in the fields parameters for user queries.
	 */
	public function testSingleUserFieldQuery() {
		$this->createAndIndexUsers();

		// First, get the IDs of the users.
		$user_query = new \WP_User_Query(
			[
				'number' => 5,
				'fields' => 'ID',
			]
		);

		$this->assertEquals( 5, count( $user_query->results ) );

		// This returns an array of strings, while EP returns ints.
		$user_ids = array_map( 'absint', $user_query->results );

		// Run the same query against EP to verify we're only getting
		// user IDs.
		$user_query = new \WP_User_Query(
			[
				'ep_integrate' => true,
				'number'       => 5,
				'fields'       => 'ID',
			]
		);

		$ep_user_ids = array_map( 'absint', $user_query->results );

		$this->assertSame( $user_ids, $ep_user_ids );
	}

	/**
	 * Tests multiple fields in the fields parameters for user queries.
	 */
	public function testMultipleUserFieldsQuery() {
		$this->createAndIndexUsers();

		$count = 5;

		// First, get the IDs of the users.
		$user_query = new \WP_User_Query(
			[
				'number' => $count,
				'fields' => [ 'ID', 'display_name' ],
			]
		);

		$users = $user_query->results;

		$this->assertEquals( $count, count( $users ) );

		// Run the same query against EP to verify we're getting classes
		// with properties.
		$user_query = new \WP_User_Query(
			[
				'ep_integrate' => true,
				'number'       => $count,
				'fields' => [ 'ID', 'display_name' ],
			]
		);

		$ep_users = $user_query->results;

		$this->assertEquals( 5, count( $users ) );

		for ( $i = 0; $i < 5; $i++ ) {
			$this->assertSame( absint( $users[ $i ]->ID ), absint( $ep_users[ $i ]->ID ) );
			$this->assertSame( $users[ $i ]->display_name, $ep_users[ $i ]->display_name );
		}
	}
}
