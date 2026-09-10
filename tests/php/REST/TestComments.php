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

		unset( $_COOKIE[ 'wp-postpass_' . COOKIEHASH ] );

		Features::factory()->activate_feature( 'search' );
		Features::factory()->activate_feature( 'comments' );
		Features::factory()->setup_features();

		Elasticsearch::factory()->delete_all_indices();
		Indexables::factory()->get( 'comment' )->put_mapping();
		Indexables::factory()->get( 'comment' )->sync_manager->reset_sync_queue();

		Features::factory()->get_registered_feature( 'comments' )->search_setup();
	}

	/**
	 * Test that comments on password-protected posts are omitted for anonymous visitors.
	 *
	 * @group rest
	 * @group rest-comments
	 * @group comments
	 */
	public function test_get_comments_omits_comments_on_password_protected_posts() {
		$fixtures = $this->create_findme_comments();

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

		Elasticsearch::factory()->refresh_indices();

		wp_set_current_user( 0 );

		$response = $this->request_comment_search();

		$this->assertArrayHasKey( $fixtures['public_comment_id'], $response );
		$this->assertEquals( 'findme public comment', $response[ $fixtures['public_comment_id'] ]['content'] );

		$this->assertArrayNotHasKey( $stale_comment_id, $response );
		$this->assertArrayNotHasKey( $fixtures['protected_comment_id'], $response );

		$encoded = wp_json_encode( $response );
		$this->assertStringNotContainsString( 'findme stale protected comment', $encoded );
		$this->assertStringNotContainsString( 'findme protected comment', $encoded );
	}

	/**
	 * Test that users who can edit the parent post still see its comments.
	 *
	 * @group rest
	 * @group rest-comments
	 * @group comments
	 */
	public function test_get_comments_includes_password_protected_comments_for_editors() {
		$fixtures = $this->create_findme_comments();

		$editor_id = $this->factory->user->create( [ 'role' => 'editor' ] );
		wp_set_current_user( $editor_id );

		$response = $this->request_comment_search();

		$this->assertArrayHasKey( $fixtures['public_comment_id'], $response );
		$this->assertArrayHasKey( $fixtures['protected_comment_id'], $response );
		$this->assertEquals( 'findme protected comment', $response[ $fixtures['protected_comment_id'] ]['content'] );
	}

	/**
	 * Test that a visitor with the correct post password cookie can see protected comments.
	 *
	 * @group rest
	 * @group rest-comments
	 * @group comments
	 */
	public function test_get_comments_includes_password_protected_comments_with_password_cookie() {
		$fixtures = $this->create_findme_comments();

		wp_set_current_user( 0 );
		$this->set_post_password_cookie( 'secret' );

		$response = $this->request_comment_search();

		$this->assertArrayHasKey( $fixtures['public_comment_id'], $response );
		$this->assertArrayHasKey( $fixtures['protected_comment_id'], $response );
		$this->assertEquals( 'findme protected comment', $response[ $fixtures['protected_comment_id'] ]['content'] );
	}

	/**
	 * Create a public comment and a password-protected comment sharing a search term.
	 *
	 * @return array {
	 *     @type int $public_comment_id    Comment on a public post.
	 *     @type int $protected_comment_id Comment on a password-protected post.
	 * }
	 */
	protected function create_findme_comments() {
		$public_post_id    = $this->ep_factory->post->create();
		$public_comment_id = $this->ep_factory->comment->create(
			[
				'comment_content' => 'findme public comment',
				'comment_post_ID' => $public_post_id,
			]
		);

		$protected_post_id    = $this->ep_factory->post->create(
			[
				'post_password' => 'secret',
			]
		);
		$protected_comment_id = $this->ep_factory->comment->create(
			[
				'comment_content' => 'findme protected comment',
				'comment_post_ID' => $protected_post_id,
			]
		);

		Elasticsearch::factory()->refresh_indices();

		return [
			'public_comment_id'    => $public_comment_id,
			'protected_comment_id' => $protected_comment_id,
		];
	}

	/**
	 * Request a comment search from the REST controller.
	 *
	 * @param string $search Search term.
	 * @return array
	 */
	protected function request_comment_search( $search = 'findme' ) {
		$controller = new Comments();
		$request    = new \WP_REST_Request( 'GET', '/elasticpress/v1/comments' );
		$request->set_param( 's', $search );

		return $controller->get_comments( $request );
	}

	/**
	 * Set the WordPress post password cookie.
	 *
	 * @param string $password Post password.
	 */
	protected function set_post_password_cookie( $password ) {
		require_once ABSPATH . WPINC . '/class-phpass.php';

		$hasher = new \PasswordHash( 8, true );

		$_COOKIE[ 'wp-postpass_' . COOKIEHASH ] = $hasher->HashPassword( $password );
	}
}
