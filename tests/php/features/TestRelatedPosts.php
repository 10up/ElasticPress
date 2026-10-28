<?php
/**
 * Test related posts feature
 *
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;

/**
 * Related post test class
 */
class TestRelatedPosts extends BaseTestCase {

	/**
	 * Setup each test.
	 *
	 * @since 2.1
	 * @group related_posts
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
	}

	/**
	 * Log action usage for tests
	 *
	 * @since  2.1
	 */
	public function action_ep_related_html_attached() {
		$this->fired_actions['ep_related_html_attached'] = true;
	}

	/**
	 * Test for related post args filter
	 *
	 * @group related_posts
	 */
	public function testFindRelatedPostFilter() {
		$post_id = $this->ep_factory->post->create( array( 'post_content' => 'findme test 1' ) );
		$this->ep_factory->post->create( array( 'post_content' => 'findme test 2' ) );
		$this->ep_factory->post->create(
			array(
				'post_content' => 'findme test 3',
				'post_type'    => 'page',
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		ElasticPress\Features::factory()->activate_feature( 'related_posts' );

		ElasticPress\Features::factory()->setup_features();

		$related = ElasticPress\Features::factory()->get_registered_feature( 'related_posts' )->find_related( $post_id );
		$this->assertEquals( 1, count( $related ) );
		$this->assertTrue( isset( $related[0] ) && isset( $related[0]->elasticsearch ) );

		add_filter( 'ep_find_related_args', array( $this, 'find_related_posts_filter' ), 10, 1 );
		$related = ElasticPress\Features::factory()->get_registered_feature( 'related_posts' )->find_related( $post_id );
		$this->assertEquals( 2, count( $related ) );
		$this->assertTrue( isset( $related[0] ) && isset( $related[0]->elasticsearch ) );

		// Make sure it will use the number of posts to be returned.
		$related = ElasticPress\Features::factory()->get_registered_feature( 'related_posts' )->find_related( $post_id, 1 );
		$this->assertEquals( 1, count( $related ) );
		$this->assertTrue( isset( $related[0] ) && isset( $related[0]->elasticsearch ) );
	}

	/**
	 * Test for related posts query
	 *
	 * @group related_posts
	 */
	public function testGetRelatedQuery() {
		$post_id = $this->ep_factory->post->create( array( 'post_content' => 'findme test 1' ) );

		$related_post_title = 'related post test';
		$this->ep_factory->post->create(
			array(
				'post_title'   => $related_post_title,
				'post_content' => 'findme test 2',
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();
		ElasticPress\Features::factory()->activate_feature( 'related_posts' );
		ElasticPress\Features::factory()->setup_features();

		$query = ElasticPress\Features::factory()->get_registered_feature( 'related_posts' )->get_related_query( $post_id, 1 );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertNotEmpty( $query->posts );
		$this->assertEquals( '1', $query->post_count );
		$this->assertEquals( $related_post_title, $query->posts[0]->post_title );
	}

	/**
	 * Test that date decay is disabled by default for related posts.
	 *
	 * @group related_posts
	 */
	public function testRelatedPostsDecayDisabledByDefault() {
		$post_id = $this->ep_factory->post->create( array( 'post_content' => 'findme test 1' ) );
		$this->ep_factory->post->create( array( 'post_content' => 'findme test 2' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		ElasticPress\Features::factory()->activate_feature( 'related_posts' );
		ElasticPress\Features::factory()->setup_features();

		add_filter( 'ep_formatted_args', array( $this, 'catch_ep_formatted_args' ), 20 );

		ElasticPress\Features::factory()->get_registered_feature( 'related_posts' )->find_related( $post_id );

		$this->assertTrue( isset( $this->fired_actions['ep_formatted_args'] ) );

		$query = $this->fired_actions['ep_formatted_args']['query'];

		// Should have more_like_this but NOT function_score.
		$this->assertArrayHasKey( 'more_like_this', $query );
		$this->assertArrayNotHasKey( 'function_score', $query );
	}

	/**
	 * Test that date decay can be enabled for related posts.
	 *
	 * @group related_posts
	 */
	public function testRelatedPostsDecayEnabled() {
		$post_id = $this->ep_factory->post->create( array( 'post_content' => 'findme test 1' ) );
		$this->ep_factory->post->create( array( 'post_content' => 'findme test 2' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		ElasticPress\Features::factory()->activate_feature( 'related_posts' );
		ElasticPress\Features::factory()->update_feature(
			'related_posts',
			array(
				'active'           => true,
				'decaying_enabled' => '1',
			)
		);
		ElasticPress\Features::factory()->setup_features();

		add_filter( 'ep_formatted_args', array( $this, 'catch_ep_formatted_args' ), 20 );

		ElasticPress\Features::factory()->get_registered_feature( 'related_posts' )->find_related( $post_id );

		$this->assertTrue( isset( $this->fired_actions['ep_formatted_args'] ) );

		$query = $this->fired_actions['ep_formatted_args']['query'];

		$this->assertDecayEnabled( $query );
	}

	/**
	 * Test the ep_related_posts_is_decaying_enabled filter.
	 *
	 * @group related_posts
	 */
	public function testRelatedPostsDecayFilter() {
		ElasticPress\Features::factory()->activate_feature( 'related_posts' );
		ElasticPress\Features::factory()->setup_features();

		$feature = ElasticPress\Features::factory()->get_registered_feature( 'related_posts' );

		// Default is disabled.
		$this->assertFalse( $feature->is_decaying_enabled() );

		// Filter can enable it.
		add_filter( 'ep_related_posts_is_decaying_enabled', '__return_true' );
		$this->assertTrue( $feature->is_decaying_enabled() );
		remove_filter( 'ep_related_posts_is_decaying_enabled', '__return_true' );

		// Filter can disable it.
		add_filter( 'ep_related_posts_is_decaying_enabled', '__return_false' );
		$this->assertFalse( $feature->is_decaying_enabled() );
		remove_filter( 'ep_related_posts_is_decaying_enabled', '__return_false' );
	}

	/**
	 * Test that without decay, related posts can return old posts first.
	 *
	 * @group related_posts
	 */
	public function testRelatedPostsWithoutDecayReturnsOldPosts() {
		$shared_content = 'Elasticsearch search integration plugin relevance matching';

		// The source post.
		$source_id = $this->ep_factory->post->create(
			array(
				'post_title'   => 'Source Post',
				'post_content' => $shared_content,
				'post_date'    => gmdate( 'Y-m-d H:i:s' ),
			)
		);

		// An old post with very similar content.
		$old_post_id = $this->ep_factory->post->create(
			array(
				'post_title'   => 'Old Related Post',
				'post_content' => $shared_content . ' old content matching',
				'post_date'    => gmdate( 'Y-m-d H:i:s', strtotime( '-3 years' ) ),
			)
		);

		// A recent post with very similar content.
		$new_post_id = $this->ep_factory->post->create(
			array(
				'post_title'   => 'New Related Post',
				'post_content' => $shared_content . ' new content matching',
				'post_date'    => gmdate( 'Y-m-d H:i:s', strtotime( '-1 day' ) ),
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		ElasticPress\Features::factory()->activate_feature( 'related_posts' );
		ElasticPress\Features::factory()->setup_features();

		$related = ElasticPress\Features::factory()->get_registered_feature( 'related_posts' )->find_related( $source_id, 2 );

		$this->assertCount( 2, $related );

		// Without decay, both posts are returned (order based on content relevance only).
		$related_ids = wp_list_pluck( $related, 'ID' );
		$this->assertContains( $old_post_id, $related_ids );
		$this->assertContains( $new_post_id, $related_ids );
	}

	/**
	 * Test that with decay enabled, newer related posts are favored.
	 *
	 * @group related_posts
	 */
	public function testRelatedPostsWithDecayFavorsNewerPosts() {
		$shared_content = 'Elasticsearch search integration plugin relevance matching';

		// The source post.
		$source_id = $this->ep_factory->post->create(
			array(
				'post_title'   => 'Source Post',
				'post_content' => $shared_content,
				'post_date'    => gmdate( 'Y-m-d H:i:s' ),
			)
		);

		// An old post with very similar content.
		$this->ep_factory->post->create(
			array(
				'post_title'   => 'Old Related Post',
				'post_content' => $shared_content . ' old content matching',
				'post_date'    => gmdate( 'Y-m-d H:i:s', strtotime( '-3 years' ) ),
			)
		);

		// A recent post with very similar content.
		$new_post_id = $this->ep_factory->post->create(
			array(
				'post_title'   => 'New Related Post',
				'post_content' => $shared_content . ' new content matching',
				'post_date'    => gmdate( 'Y-m-d H:i:s', strtotime( '-1 day' ) ),
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		ElasticPress\Features::factory()->activate_feature( 'related_posts' );
		ElasticPress\Features::factory()->update_feature(
			'related_posts',
			array(
				'active'           => true,
				'decaying_enabled' => '1',
			)
		);
		ElasticPress\Features::factory()->setup_features();

		// Use aggressive decay so the old post is clearly penalized.
		add_filter(
			'epwr_related_posts_scale',
			function () {
				return '30d';
			}
		);
		add_filter(
			'epwr_related_posts_offset',
			function () {
				return '7d';
			}
		);
		add_filter(
			'epwr_related_posts_decay',
			function () {
				return 0.1;
			}
		);

		$related = ElasticPress\Features::factory()->get_registered_feature( 'related_posts' )->find_related( $source_id, 2 );

		$this->assertNotEmpty( $related );

		// The newer post should be the first result.
		$this->assertEquals( $new_post_id, $related[0]->ID );
	}

	/**
	 * Detect EP fire
	 *
	 * @param array $args Query args
	 * @return mixed
	 */
	public function find_related_posts_filter( $args ) {
		$args['post_type'] = array( 'post', 'page' );

		return $args;
	}
}
