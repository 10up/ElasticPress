<?php
/**
 * Test comments REST controller
 *
 * @since 5.3.5
 * @package elasticpress
 */

namespace ElasticPressTest\REST;

use ElasticPress\Elasticsearch;
use ElasticPress\Features;
use ElasticPress\Indexables;
use ElasticPress\REST\Comments;

/**
 * Comments REST test class
 */
class TestComments extends \ElasticPressTest\BaseTestCase {

	/**
	 * Setup each test.
	 */
	public function set_up() {
		parent::set_up();

		Features::factory()->activate_feature( 'search' );
		Features::factory()->activate_feature( 'comments' );
		Features::factory()->setup_features();

		Elasticsearch::factory()->delete_all_indices();
		Indexables::factory()->get( 'comment' )->put_mapping();
		Indexables::factory()->get( 'comment' )->sync_manager->reset_sync_queue();

		Features::factory()->get_registered_feature( 'comments' )->search_setup();
	}

	/**
	 * Test that comments on password-protected posts are omitted from the REST response.
	 *
	 * @group rest
	 * @group rest-comments
	 * @group comments
	 */
	public function test_get_comments_omits_comments_on_password_protected_posts() {
		$controller = new Comments();

		$public_post_id    = $this->ep_factory->post->create();
		$public_comment_id = $this->ep_factory->comment->create(
			[
				'comment_content' => 'findme public comment',
				'comment_post_ID' => $public_post_id,
			]
		);

		$later_protected_post_id = $this->ep_factory->post->create();
		$stale_comment_id        = $this->ep_factory->comment->create(
			[
				'comment_content' => 'findme stale protected comment',
				'comment_post_ID' => $later_protected_post_id,
			]
		);

		wp_update_post(
			[
				'ID'            => $later_protected_post_id,
				'post_password' => 'secret',
			]
		);

		$already_protected_post_id = $this->ep_factory->post->create(
			[
				'post_password' => 'secret',
			]
		);
		$protected_comment_id      = $this->ep_factory->comment->create(
			[
				'comment_content' => 'findme already protected comment',
				'comment_post_ID' => $already_protected_post_id,
			]
		);

		Elasticsearch::factory()->refresh_indices();

		wp_set_current_user( 0 );

		$request = new \WP_REST_Request( 'GET', '/elasticpress/v1/comments' );
		$request->set_param( 's', 'findme' );

		$response = $controller->get_comments( $request );

		$this->assertArrayHasKey( $public_comment_id, $response );
		$this->assertEquals( 'findme public comment', $response[ $public_comment_id ]['content'] );

		$this->assertArrayNotHasKey( $stale_comment_id, $response );
		$this->assertArrayNotHasKey( $protected_comment_id, $response );

		$encoded = wp_json_encode( $response );
		$this->assertStringNotContainsString( 'findme stale protected comment', $encoded );
		$this->assertStringNotContainsString( 'findme already protected comment', $encoded );
	}
}
