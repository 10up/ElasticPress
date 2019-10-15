<?php
/**
 * Integrate with WP_Comment_Query
 *
 * @since   3.1
 * @package elasticpress
 */

namespace ElasticPress\Indexable\Comment;

use ElasticPress\Indexables as Indexables;
use \WP_Comment_Query as WP_Comment_Query;
use ElasticPress\Utils as Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Query integration class
 */
class QueryIntegration {

	/**
	 * Sets up the appropriate actions and filters.
	 *
	 * @since 3.1
	 */
	public function __construct() {
		// Check if we are currently indexing
		if ( Utils\is_indexing() ) {
			return;
		}

		// Add header
		add_action( 'pre_get_comments', array( $this, 'action_pre_get_comments' ), 5 );

		// Filter comment query
		add_filter( 'comments_pre_query', [ $this, 'maybe_filter_query' ], 10, 2 );
	}

	/**
	 * Add EP header
	 *
	 * @param  WP_Comment_Query $query Query object
	 * @since  3.1
	 * @return void
	 */
	public function action_pre_get_comments( WP_Comment_Query $query ) {
		if ( ! Indexables::factory()->get( 'comment' )->elasticpress_enabled( $query ) || apply_filters( 'ep_skip_comment_query_integration', false, $query ) ) {
			return;
		}

		if ( ! headers_sent() ) {
			/**
			 * Manually setting a header as $wp_query isn't yet initialized
			 * when we call: add_filter('wp_headers', 'filter_wp_headers');
			 */
			header( 'X-ElasticPress-Search: true' );
		}
	}

	/**
	 * If WP_Comment_Query meets certain conditions, query results from ES
	 *
	 * @param  array            $results Query results.
	 * @param  WP_Comment_Query $query   Current query.
	 * @since  3.1
	 * @return array
	 */
	public function maybe_filter_query( $results, WP_Comment_Query $query ) {
		$indexable = Indexables::factory()->get( 'comment' );

		if ( ! $indexable->elasticpress_enabled( $query ) || apply_filters( 'ep_skip_comment_query_integration', false, $query ) ) {
			return $results;
		}

		$new_comments = apply_filters( 'ep_wp_query_cached_comments', null, $query );

		if ( null !== $new_comments ) {
			return $new_comments;
		}

		$formatted_args = $indexable->format_args( $query->query_vars );

		$scope = 'current';
		if ( ! empty( $query->query_vars['sites'] ) ) {
			$scope = $query->query_vars['sites'];
		}

		/**
		 * Filter search scope
		 *
		 * @since 3.1
		 *
		 * @param mixed $scope The search scope. Accepts `all` (string), a single
		 *                     site id (int or string), or an array of site ids (array).
		 */
		$scope = apply_filters( 'ep_comment_search_scope', $scope );

		if ( ! defined( 'EP_IS_NETWORK' ) || ! EP_IS_NETWORK ) {
			$scope = 'current';
		}

		$index = null;

		if ( 'all' === $scope ) {
			$index = $indexable->get_network_alias();
		} elseif ( is_numeric( $scope ) ) {
			$index = $indexable->get_index_name( (int) $scope );
		} elseif ( is_array( $scope ) ) {
			$index = [];

			foreach ( $scope as $site_id ) {
				$index[] = $indexable->get_index_name( $site_id );
			}

			$index = implode( ',', $index );
		}

		$ep_query = $indexable->query_es( $formatted_args, $query->query_vars, $index );

		if ( false === $ep_query ) {
			$query->elasticsearch_success = false;
			return $results;
		}

		$query->found_comments        = $ep_query['found_documents'];
		$query->elasticsearch_success = true;

		// Determine how we should format the results from ES based on the fields parameter.
		$fields = $query->query_vars['fields'];

		switch ( $fields ) {
			case 'ids':
				$new_comments = $this->format_hits_as_ids( $ep_query['documents'], $new_comments );
				break;

			default:
				$new_comments = $this->format_hits_as_comments( $ep_query['documents'], $new_comments, $query->query_vars );
				break;
		}

		if ( ! empty( $query->query_vars['count'] ) ) {
			$new_comments = count( $ep_query['documents'] );
		}

		return $new_comments;
	}

	/**
	 * Format the ES hits/results as comments objects.
	 *
	 * @param  array $comments The comments that should be formatted.
	 * @param  array $new_comments Array of comments from cache.
	 * @param  array $query_vars Query variables.
	 * @since  3.1
	 * @return array
	 */
	protected function format_hits_as_comments( $comments, $new_comments, $query_vars ) {
		foreach ( $comments as $comment_array ) {
			$comment = new \stdClass();

			$comment->ID      = $comment_array['comment_ID'];
			$comment->site_id = get_current_blog_id();

			if ( ! empty( $comment_array['site_id'] ) ) {
				$comment->site_id = $comment_array['site_id'];
			}

			$comment_return_args = apply_filters(
				'ep_search_comment_return_args',
				[
					'comment_ID',
					'comment_post_ID',
					'comment_author',
					'comment_author_email',
					'comment_author_url',
					'comment_author_IP',
					'comment_date',
					'comment_date_gmt',
					'comment_content',
					'comment_karma',
					'comment_approved',
					'comment_agent',
					'comment_type',
					'comment_parent',
					'user_id',
				]
			);

			foreach ( $comment_return_args as $key ) {
				if ( isset( $comment_array[ $key ] ) ) {
					$comment->$key = $comment_array[ $key ];
				}
			}

			$comment->elasticsearch = true; // Super useful for debugging

			if ( $comment ) {
				$new_comments[] = $comment;
			}
		}

		return $new_comments;
	}

	/**
	 * Format the ES hits/results as an array of ids.
	 *
	 * @param  array  $comments The comments that should be formatted.
	 * @param  array  $new_comments Array of comments from cache.
	 * @since  3.1
	 * @return array
	 */
	protected function format_hits_as_ids( $comments, $new_comments ) {
		foreach ( $comments as $comment_array ) {
			$new_comments[] = $comment_array['comment_ID'];
		}

		return $new_comments;
	}

}
