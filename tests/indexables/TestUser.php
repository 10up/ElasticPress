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
				'role'         => 'administrator',
				'user_login'   => 'test_admin',
				'first_name'   => 'Mike',
				'last_name'    => 'Mickey',
				'display_name' => 'mikey',
				'user_email'   => 'mikey@gmail.com',
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
		ElasticPress\Indexables::factory()->get( 'user' )->sync_manager->sync_queue[1] = true;

		ElasticPress\Indexables::factory()->get( 'user' )->bulk_index( array_keys( ElasticPress\Indexables::factory()->get( 'user' )->sync_manager->sync_queue ) );

		$user_1 = Functions\create_and_sync_user(
			[
				'user_login'   => 'user1 author',
				'role'         => 'author',
				'first_name'   => 'Dave',
				'last_name'    => 'Smith',
				'display_name' => 'dave',
				'user_email'   => 'dave@gmail.com',
			]
		);

		$user_2 = Functions\create_and_sync_user(
			[
				'user_login'   => 'user2 contributor',
				'role'         => 'contributor',
				'first_name'   => 'Zoey',
				'last_name'    => 'Johnson',
				'display_name' => 'Zoey',
				'user_email'   => 'zoey@gmail.com',
			]
		);

		$user_3 = Functions\create_and_sync_user(
			[
				'user_login'   => 'user3 editor',
				'role'         => 'editor',
				'first_name'   => 'Joe',
				'last_name'    => 'Doe',
				'display_name' => 'joe',
				'user_email'   => 'joe@gmail.com',
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
		$this->assertTrue( ! empty( ElasticPress\Indexables::factory()->get( 'user' )->sync_manager->sync_queue[ $user_id ] ) );
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

		// First try without ES and make sure everything is right
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

		// Now try with Elasticsearch

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
}
