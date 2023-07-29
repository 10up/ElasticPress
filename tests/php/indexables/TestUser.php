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
	public function set_up() {
		global $wpdb;
		parent::set_up();
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
				'user_url'      => 'http://abc.com',
			]
		);

		grant_super_admin( $admin_id );

		wp_set_current_user( $admin_id );

		// Need to call this since it's hooked to init
		ElasticPress\Features::factory()->get_registered_feature( 'users' )->search_setup();
	}

	/**
	 * Get User feature
	 *
	 * @return ElasticPress\Feature\Users
	 */
	protected function get_feature() {
		return ElasticPress\Features::factory()->get_registered_feature( 'users' );
	}

	/**
	 * Create and index users for testing
	 *
	 * @since 3.0
	 */
	public function createAndIndexUsers() {
		ElasticPress\Indexables::factory()->get( 'user' )->sync_manager->add_to_queue( 1 );

		ElasticPress\Indexables::factory()->get( 'user' )->bulk_index( array_keys( ElasticPress\Indexables::factory()->get( 'user' )->sync_manager->sync_queue ) );

		$user_1 = $this->ep_factory->user->create(
			[
				'user_login'   => 'user1-author',
				'role'         => 'author',
				'first_name'   => 'Dave',
				'last_name'    => 'Smith',
				'display_name' => 'dave',
				'user_email'   => 'dave@gmail.com',
				'user_url'     => 'http://bac.com',
				'meta_input'   => [
					'user_1_key' => 'value1',
					'user_num'   => 5,
					'long_key'   => 'here is a text field',
				],
			]
		);

		$user_2 = $this->ep_factory->user->create(
			[
				'user_login'   => 'user2-contributor',
				'role'         => 'contributor',
				'first_name'   => 'Zoey',
				'last_name'    => 'Johnson',
				'display_name' => 'Zoey',
				'user_email'   => 'zoey@gmail.com',
				'user_url'     => 'http://google.com',
				'meta_input'   => [
					'user_2_key' => 'value2',
				],
			]
		);

		$user_3 = $this->ep_factory->user->create(
			[
				'user_login'   => 'user3-editor',
				'role'         => 'editor',
				'first_name'   => 'Joe',
				'last_name'    => 'Doe',
				'display_name' => 'joe',
				'user_email'   => 'joe@gmail.com',
				'user_url'     => 'http://cab.com',
				'meta_input'   => [
					'user_3_key' => 'value3',
					'user_num'   => 5,
				],
			]
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		return [ $user_1, $user_2, $user_3 ];
	}

	/**
	 * Clean up after each test. Reset our mocks
	 *
	 * @since 0.1.0
	 */
	public function tear_down() {
		parent::tear_down();

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

		$this->assertArrayNotHasKey( 'elasticsearch_success', $user_query->query_vars );
		$this->assertEquals( 5, count( $user_query->results ) );
		$this->assertEquals( 5, $user_query->total_users );

		// Now try with Elasticsearch.
		$user_query = new \WP_User_Query(
			[
				'ep_integrate' => true,
				'number'       => 10,
			]
		);

		$this->assertTrue( $user_query->query_vars['elasticsearch_success'] );
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

		$this->ep_factory->user->create_many( 15 );
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$user_query = new \WP_User_Query(
			[
				'ep_integrate' => true,
			]
		);

		$this->assertTrue( $user_query->query_vars['elasticsearch_success'] );
		$this->assertEquals( 20, count( $user_query->results ) );
		$this->assertEquals( 20, $user_query->total_users );
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

		$this->assertTrue( $user_query->query_vars['elasticsearch_success'] );
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

		$this->assertTrue( $user_query->query_vars['elasticsearch_success'] );
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

		$this->assertTrue( $user_query->query_vars['elasticsearch_success'] );
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

		$this->assertTrue( $user_query->query_vars['elasticsearch_success'] );
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

		$this->assertTrue( $user_query->query_vars['elasticsearch_success'] );
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

		$this->assertTrue( $user_query->query_vars['elasticsearch_success'] );
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

		$this->assertTrue( $user_query->query_vars['elasticsearch_success'] );
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

		$this->assertTrue( $user_query->query_vars['elasticsearch_success'] );
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

		$this->assertTrue( $user_query->query_vars['elasticsearch_success'] );
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

		$this->assertTrue( $user_query->query_vars['elasticsearch_success'] );
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

		$this->assertTrue( $user_query->query_vars['elasticsearch_success'] );
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

		$this->assertTrue( $user_query->query_vars['elasticsearch_success'] );

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

		$this->assertTrue( $user_query->query_vars['elasticsearch_success'] );

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
		$users_id = $this->createAndIndexUsers();

		$user_query = new \WP_User_Query(
			[
				'ep_integrate' => true,
				'orderby'      => 'display_name',
			]
		);

		$this->assertTrue( $user_query->query_vars['elasticsearch_success'] );

		$users_id_fetched = wp_list_pluck( $user_query->results, 'ID' );

		$this->assertCount( 5, $user_query->results );

		foreach ( $users_id as $user_id ) {
			$this->assertContains( $user_id, $users_id_fetched );
		}

		$users_display_name_fetched = wp_list_pluck( $user_query->results, 'display_name' );

		$this->assertEquals( 'admin', $users_display_name_fetched[0] );
		$this->assertEquals( 'Zoey', $users_display_name_fetched[4] );

	}

	/**
	 * Test order by display_name in format_args().
	 *
	 * We should not use a text/string field to sort
	 * in Elasticsearch.
	 *
	 * @return void
	 * @since 3.6.0
	 * @group user
	 */
	public function testFormatArgsOrderByDisplayName() {
		$user = new \ElasticPress\Indexable\User\User();

		$user_query = new \WP_User_Query();

		$args = $user->format_args(
			[
				'orderby' => 'display_name',
			],
			$user_query
		);

		$this->assertArrayHasKey( 'display_name.sortable', $args['sort'][0] );
		$this->assertArrayNotHasKey( 'display_name', $args['sort'][0] );

		$args = $user->format_args(
			[
				'orderby' => 'name',
			],
			$user_query
		);

		$this->assertArrayHasKey( 'display_name.sortable', $args['sort'][0] );
		$this->assertArrayNotHasKey( 'display_name', $args['sort'][0] );
	}

	/**
	 * Test user query orderby paramter where we are ordering by user_nicename
	 *
	 * @since 3.6.0
	 * @group user
	 */
	public function testUserQueryOrderbyUserNicename() {
		$users_id = $this->createAndIndexUsers();

		$user_query = new \WP_User_Query(
			[
				'ep_integrate' => true,
				'orderby'      => 'user_nicename',
			]
		);

		$this->assertTrue( $user_query->query_vars['elasticsearch_success'] );

		$users_id_fetched = wp_list_pluck( $user_query->results, 'ID' );

		$this->assertCount( 5, $user_query->results );

		foreach ( $users_id as $user_id ) {
			$this->assertContains( $user_id, $users_id_fetched );
		}

		$users_display_name_fetched = wp_list_pluck( $user_query->results, 'display_name' );

		// Check if 'admin' is the first user
		$this->assertEquals( 'admin', $users_display_name_fetched[0] );
	}

	/**
	 * Test order by user_nicename in format_args().
	 *
	 * We should not use a text/string field to sort
	 * in Elasticsearch.
	 *
	 * @return void
	 * @since 3.6.0
	 * @group user
	 */
	public function testFormatArgsOrderByUserNicename() {
		$user = new \ElasticPress\Indexable\User\User();

		$user_query = new \WP_User_Query();

		$args = $user->format_args(
			[
				'orderby' => 'user_nicename',
			],
			$user_query
		);

		$this->assertArrayHasKey( 'user_nicename.raw', $args['sort'][0] );
		$this->assertArrayNotHasKey( 'user_nicename', $args['sort'][0] );

		$args = $user->format_args(
			[
				'orderby' => 'nicename',
			],
			$user_query
		);

		$this->assertArrayHasKey( 'user_nicename.raw', $args['sort'][0] );
		$this->assertArrayNotHasKey( 'user_nicename', $args['sort'][0] );
	}

	/**
	 * Test user query orderby parameter where we are ordering by user_email
	 *
	 * @since 3.6.0
	 * @group user
	 */
	public function testUserQueryOrderbyUserEmail() {
		$users_id = $this->createAndIndexUsers();

		$user_query = new \WP_User_Query(
			[
				'ep_integrate' => true,
				'orderby'      => 'user_email',
			]
		);

		$this->assertTrue( $user_query->query_vars['elasticsearch_success'] );

		$users_id_fetched = wp_list_pluck( $user_query->results, 'ID' );

		$this->assertCount( 5, $user_query->results );

		foreach ( $users_id as $user_id ) {
			$this->assertContains( $user_id, $users_id_fetched );
		}

		$users_display_name_fetched = wp_list_pluck( $user_query->results, 'display_name' );

		// Check if 'admin' is the first user
		$this->assertEquals( 'admin', $users_display_name_fetched[0] );
	}

	/**
	 * Test order by user_email in format_args().
	 *
	 * We should not use a text/string field to sort
	 * in Elasticsearch.
	 *
	 * @return void
	 * @since 3.6.0
	 * @group user
	 */
	public function testFormatArgsOrderByUserEmail() {
		$user = new \ElasticPress\Indexable\User\User();

		$user_query = new \WP_User_Query();

		$args = $user->format_args(
			[
				'orderby' => 'user_email',
			],
			$user_query
		);

		$this->assertArrayHasKey( 'user_email.raw', $args['sort'][0] );
		$this->assertArrayNotHasKey( 'user_email', $args['sort'][0] );

		$args = $user->format_args(
			[
				'orderby' => 'user_email',
			],
			$user_query
		);

		$this->assertArrayHasKey( 'user_email.raw', $args['sort'][0] );
		$this->assertArrayNotHasKey( 'user_email', $args['sort'][0] );
	}

	/**
	 * Test user query orderby parameter where we are ordering by user_url
	 *
	 * @since 3.6.0
	 * @group user
	 */
	public function testUserQueryOrderbyUserUrl() {
		$users_id = $this->createAndIndexUsers();

		$user_query = new \WP_User_Query(
			[
				'ep_integrate' => true,
				'orderby'      => 'user_url',
			]
		);

		$this->assertTrue( $user_query->query_vars['elasticsearch_success'] );

		$users_id_fetched = wp_list_pluck( $user_query->results, 'ID' );

		$this->assertCount( 5, $user_query->results );

		foreach ( $users_id as $user_id ) {
			$this->assertContains( $user_id, $users_id_fetched );
		}

		$users_display_name_fetched = wp_list_pluck( $user_query->results, 'display_name' );

		$this->assertEquals( 'mikey', $users_display_name_fetched[0] );
	}

	/**
	 * Test order by user_url in format_args().
	 *
	 * We should not use a text/string field to sort
	 * in Elasticsearch.
	 *
	 * @return void
	 * @since 3.6.0
	 * @group user
	 */
	public function testFormatArgsOrderByUserUrl() {
		$user = new \ElasticPress\Indexable\User\User();

		$user_query = new \WP_User_Query();

		$args = $user->format_args(
			[
				'orderby' => 'user_url',
			],
			$user_query
		);

		$this->assertArrayHasKey( 'user_url.raw', $args['sort'][0] );
		$this->assertArrayNotHasKey( 'user_url', $args['sort'][0] );

		$args = $user->format_args(
			[
				'orderby' => 'user_url',
			],
			$user_query
		);

		$this->assertArrayHasKey( 'user_url.raw', $args['sort'][0] );
		$this->assertArrayNotHasKey( 'user_url', $args['sort'][0] );
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

		$this->assertTrue( $user_query->query_vars['elasticsearch_success'] );

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

		$this->assertTrue( $user_query->query_vars['elasticsearch_success'] );

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

		$this->assertTrue( $user_query->query_vars['elasticsearch_success'] );

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

		$this->assertTrue( $user_query->query_vars['elasticsearch_success'] );
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

		$this->assertTrue( $user_query->query_vars['elasticsearch_success'] );
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

		$this->assertTrue( $user_query->query_vars['elasticsearch_success'] );
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

		$this->assertTrue( $user_query->query_vars['elasticsearch_success'] );
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

		$this->assertTrue( $user_query->query_vars['elasticsearch_success'] );
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

		$this->assertTrue( $user_query->query_vars['elasticsearch_success'] );
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

		$this->assertTrue( $user_query->query_vars['elasticsearch_success'] );
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

		$this->assertTrue( $user_query->query_vars['elasticsearch_success'] );
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

		$this->assertTrue( $user_query->query_vars['elasticsearch_success'] );
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

		$this->assertTrue( $user_query->query_vars['elasticsearch_success'] );
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

		$this->assertTrue( $user_query->query_vars['elasticsearch_success'] );
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
				'fields'       => [ 'ID', 'display_name' ],
			]
		);

		$this->assertTrue( $user_query->query_vars['elasticsearch_success'] );

		$ep_users = $user_query->results;

		$this->assertEquals( 5, count( $users ) );

		for ( $i = 0; $i < 5; $i++ ) {
			$this->assertSame( absint( $users[ $i ]->ID ), absint( $ep_users[ $i ]->ID ) );
			$this->assertSame( $users[ $i ]->display_name, $ep_users[ $i ]->display_name );
		}
	}

	/**
	 * Test integration with User Queries.
	 */
	public function testIntegrateSearchQueries() {
		$this->assertTrue( $this->get_feature()->integrate_search_queries( true, null ) );
		$this->assertFalse( $this->get_feature()->integrate_search_queries( false, null ) );

		$query = new \WP_User_Query(
			[
				'ep_integrate' => false,
			]
		);

		$this->assertFalse( $this->get_feature()->integrate_search_queries( true, $query ) );

		$query = new \WP_User_Query(
			[
				'ep_integrate' => 0,
			]
		);

		$this->assertFalse( $this->get_feature()->integrate_search_queries( true, $query ) );

		$query = new \WP_User_Query(
			[
				'ep_integrate' => 'false',
			]
		);

		$this->assertFalse( $this->get_feature()->integrate_search_queries( true, $query ) );

		$query = new \WP_User_Query(
			[
				'search' => 'user',
			]
		);

		$this->assertTrue( $this->get_feature()->integrate_search_queries( false, $query ) );
	}

	/**
	 * Test users that does not belong to any blog.
	 *
	 * @since 4.1.0
	 */
	public function testUserSearchLimitedToOneBlog() {
		// This user does not belong to any blog.
		$this->ep_factory->user->create(
			[
				'user_login' => 'users-and-blogs-1',
				'role'       => '',
				'first_name' => 'No Blog',
				'last_name'  => 'User',
				'user_email' => 'no-blog@test.com',
				'user_url'   => 'http://domain.test',
			]
		);
		$this->ep_factory->user->create(
			[
				'user_login' => 'users-and-blogs-2',
				'role'       => 'contributor',
				'first_name' => 'Blog',
				'last_name'  => 'User',
				'user_email' => 'blog@test.com',
				'user_url'   => 'http://domain.test',
			]
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		// Here `blog_id` defaults to `get_current_blog_id()`.
		$query = new \WP_User_Query(
			[
				'search' => 'users-and-blogs',
			]
		);

		$this->assertTrue( $this->get_feature()->integrate_search_queries( false, $query ) );
		$this->assertEquals( 1, $query->total_users );
		$this->assertTrue( $query->query_vars['elasticsearch_success'] );

		// Search accross all blogs.
		$query = new \WP_User_Query(
			[
				'search'  => 'users-and-blogs',
				'blog_id' => 0,
			]
		);

		$this->assertTrue( $this->get_feature()->integrate_search_queries( false, $query ) );
		$this->assertEquals( 2, $query->total_users );
		$this->assertTrue( $query->query_vars['elasticsearch_success'] );
	}

	/**
	 * Test user query search by user login.
	 *
	 * @since 4.4.0
	 */
	public function testUserQueryUserLogin() {
		$this->createAndIndexUsers();

		$user_query = new \WP_User_Query(
			[
				'search'         => 'contributor',
				'search_columns' => [ 'user_login' ],
			]
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$this->assertEquals( 1, $user_query->total_users );
		$this->assertEquals( 'user2-contributor', $user_query->results[0]->user_login );
		$this->assertTrue( $user_query->query_vars['elasticsearch_success'] );
	}

	/**
	 * Test user query search by user nicename.
	 *
	 * @since 4.4.0
	 */
	public function testUserQueryUserNiceName() {
		$this->createAndIndexUsers();

		$user_query = new \WP_User_Query(
			[
				'search'         => 'mike',
				'search_columns' => [ 'user_nicename' ],
			]
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$this->assertEquals( 1, $user_query->total_users );
		$this->assertEquals( 'test_admin', $user_query->results[0]->user_login );
		$this->assertTrue( $user_query->query_vars['elasticsearch_success'] );
	}

	/**
	 * Test user query default orderby set to asc.
	 *
	 * @since 4.4.0
	 */
	public function testUserQueryDefaultOrderBy() {
		$this->createAndIndexUsers();

		$expected_user_order = [
			'admin',
			'test_admin',
			'user1-author',
			'user2-contributor',
			'user3-editor',
		];

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$user_query = new \WP_User_Query(
			[
				'ep_integrate' => true,
				'orderby'      => '',
			]
		);

		$user_order = array();
		foreach ( $user_query->results as $user ) {
			$user_order[] = $user->user_login;
		}

		$this->assertTrue( $user_query->query_vars['elasticsearch_success'] );
		$this->assertEquals( $expected_user_order, $user_order );
	}

	/**
	 * Test default order set to the score when orderby is set to empty
	 *
	 * @since 4.4.0
	 */
	public function testUserQueryDefaultOrder() {
		$this->createAndIndexUsers();

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		add_action(
			'pre_http_request',
			function( $preempt, $parsed_args, $url ) {
				$body = json_decode( $parsed_args['body'], true );

				$this->assertNotEmpty( $body['sort'][0]['_score'] );

				return $preempt;
			},
			10,
			3
		);

		$user_query = new \WP_User_Query(
			[
				'orderby' => '',
				'search'  => 'user',
			]
		);

		$this->assertTrue( $user_query->query_vars['elasticsearch_success'] );
	}

	/**
	 * Test protected meta does not index.
	 *
	 * @since 4.4.0
	 */
	public function testProtectedMetaNotIndex() {

		$user_id = $this->factory->user->create(
			[
				'meta_input' => array(
					'_phone_number' => '1234567890',
				),
			]
		);

		$user = new \ElasticPress\Indexable\User\User();

		$user_args = $user->prepare_document( $user_id );

		$this->assertTrue( empty( $user_args['meta']['_phone_number'] ) );
	}

	/**
	 * Test whitelisted meta does index.
	 *
	 * @since 4.4.0
	 */
	public function testProtectedWhiteListMetaIndex() {

		add_filter(
			'ep_prepare_user_meta_allowed_protected_keys',
			function( $meta_keys ) {
				$meta_keys[] = '_phone_number';

				return $meta_keys;
			}
		);

		$user_id = $this->factory->user->create(
			[
				'meta_input' => array(
					'_phone_number' => '1234567890',
				),
			]
		);

		$user      = new \ElasticPress\Indexable\User\User();
		$user_args = $user->prepare_document( $user_id );

		$this->assertEquals( $user_args['meta']['_phone_number'][0]['value'], '1234567890' );
	}

	/**
	 * Test query_db() function.
	 *
	 * @since 4.4.0
	 */
	public function testQueryDb() {

		$this->createAndIndexUsers();
		$user_1 = $this->factory->user->create();
		$user_2 = $this->factory->user->create();

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$user = new \ElasticPress\Indexable\User\User();

		// Test the first loop of the indexing.
		$results = $user->query_db(
			[
				'per_page' => 1,
			]
		);

		$this->assertCount( 1, $results['objects'] );
		$this->assertEquals( 7, $results['total_objects'] );
		$this->assertEquals( $user_2, $results['objects'][0]->ID );

		// Test the second loop of the indexing.
		$results = $user->query_db(
			[
				'per_page' => 1,
				'offset'   => 1,
			]
		);

		$this->assertCount( 1, $results['objects'] );
		$this->assertEquals( 7, $results['total_objects'] );
		$this->assertEquals( $user_1, $results['objects'][0]->ID );
	}

	/**
	 * Test if the mapping applies the ep_stop filter correctly
	 *
	 * @since 4.7.0
	 * @group user
	 */
	public function test_mapping_ep_stop_filter() {
		$indexable      = ElasticPress\Indexables::factory()->get( 'user' );
		$index_name     = $indexable->get_index_name();
		$settings       = ElasticPress\Elasticsearch::factory()->get_index_settings( $index_name );
		$index_settings = $settings[ $index_name ]['settings'];

		$this->assertContains( 'ep_stop', $index_settings['index.analysis.analyzer.default.filter'] );
		$this->assertSame( '_english_', $index_settings['index.analysis.filter.ep_stop.stopwords'] );

		$change_lang = function( $lang, $context ) {
			return 'filter_ep_stop' === $context ? '_arabic_' : $lang;
		};
		add_filter( 'ep_analyzer_language', $change_lang, 11, 2 );

		ElasticPress\Elasticsearch::factory()->delete_all_indices();
		$indexable->put_mapping();

		$settings       = ElasticPress\Elasticsearch::factory()->get_index_settings( $index_name );
		$index_settings = $settings[ $index_name ]['settings'];
		$this->assertSame( '_arabic_', $index_settings['index.analysis.filter.ep_stop.stopwords'] );
	}
}
