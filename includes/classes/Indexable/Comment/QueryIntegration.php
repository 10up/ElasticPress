<?php
/**
 * Integrate with WP_Comment_Query
 *
 * @since   3.6.0
 * @package elasticpress
 */

namespace ElasticPress\Indexable\Comment;

use \WP_Comment_Query;
use ElasticPress\Indexables;
use ElasticPress\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Query integration class
 */
class QueryIntegration {

	/**
	 * Comment indexable
	 *
	 * @var Comment
	 */
	public $indexable = '';

	/**
	 * Index name
	 *
	 * @var string
	 */
	public $index = '';

	/**
	 * Sets up the appropriate actions and filters.
	 *
	 * @param string $indexable_slug Indexable slug. Optional.
	 *
	 * @since 3.6.0
	 */
	public function __construct( $indexable_slug = 'comment' ) {
		/**
		 * Filter whether to enable query integration during indexing
		 *
		 * @since 4.5.2
		 * @hook ep_enable_query_integration_during_indexing
		 *
		 * @param {bool} $enable To allow query integration during indexing
		 * @param {string} $indexable_slug Indexable slug
		 * @return {bool} New value
		 */
		$allow_query_integration_during_indexing = apply_filters( 'ep_enable_query_integration_during_indexing', false, $indexable_slug );

		// Check if we are currently indexing
		if ( Utils\is_indexing() && ! $allow_query_integration_during_indexing ) {
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
	 * @since  3.6.0
	 * @return void
	 */
	public function action_pre_get_comments( WP_Comment_Query $query ) {
		/**
		 * Filter to skip WP_Comment_Query integration
		 *
		 * @hook ep_skip_comment_query_integration
		 * @since 3.6.0
		 * @param  {bool} $skip True to skip
		 * @param  {WP_Comment_Query} $query WP_Comment_Query to evaluate
		 * @return {bool} New skip value
		 */
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
	 * @since  3.6.0
	 * @return array
	 */
	public function maybe_filter_query( $results, WP_Comment_Query $query ) {
		$this->indexable = Indexables::factory()->get( 'comment' );

		if ( ! $this->indexable->elasticpress_enabled( $query ) || apply_filters( 'ep_skip_comment_query_integration', false, $query ) ) {
			return $results;
		}

		/**
		 * Filter cached comments pre-post query
		 *
		 * @hook ep_wp_query_cached_comments
		 * @since 3.6.0
		 * @param  {mixed} $comments Comments or null
		 * @param  {WP_Comment_Query} $query WP_Comment_Query object
		 * @return {array} New cached comments
		 */
		$new_comments = apply_filters( 'ep_wp_query_cached_comments', null, $query );

		if ( null !== $new_comments ) {
			return $new_comments;
		}

		$new_comments = [];

		$formatted_args = $this->indexable->format_args( $query->query_vars );

		$scope = 'current';

		$site__in     = [];
		$site__not_in = [];

		if ( ! empty( $query->query_vars['sites'] ) ) {
			_deprecated_argument( __FUNCTION__, '4.4.0', esc_html__( 'sites is deprecated. Use site__in instead.', 'elasticpress' ) );
		}

		if ( ! empty( $query->query_vars['site__in'] ) || ! empty( $query->query_vars['sites'] ) ) {
			$site__in = ! empty( $query->query_vars['site__in'] ) ? (array) $query->query_vars['site__in'] : (array) $query->query_vars['sites'];

			if ( in_array( 'all', $site__in, true ) ) {
				$scope = 'all';
			} elseif ( in_array( 'current', $site__in, true ) ) {
				$site__in = (array) get_current_blog_id();
			}
		}

		if ( ! empty( $query->query_vars['site__not_in'] ) ) {
			$site__not_in = (array) $query->query_vars['site__not_in'];
		}

		/**
		 * Filter search scope
		 *
		 * @since 3.6.0
		 *
		 * @param mixed $scope The search scope. Accepts `all` (string), a single
		 *                     site id (int or string), or an array of site ids (array).
		 */
		$scope = apply_filters( 'ep_comment_search_scope', $scope );

		if ( ! defined( 'EP_IS_NETWORK' ) || ! EP_IS_NETWORK ) {
			$scope = 'current';
		}

		$this->index = null;

		if ( 'all' === $scope ) {
			$this->index = $this->indexable->get_network_alias();
		} elseif ( ! empty( $site__in ) ) {
			$this->index = [];

			foreach ( $site__in as $site_id ) {
				$this->index[] = $this->indexable->get_index_name( $site_id );
			}

			$this->index = implode( ',', $this->index );
		} elseif ( ! empty( $site__not_in ) ) {

			$sites = \get_sites(
				array(
					'fields'       => 'ids',
					'site__not_in' => $site__not_in,
				)
			);
			foreach ( $sites as $site_id ) {
				if ( ! Utils\is_site_indexable( $site_id ) ) {
					continue;
				}
				$index[] = Indexables::factory()->get( 'comment' )->get_index_name( $site_id );
			}

			$this->index = implode( ',', $index );

		}

		$ep_query = $this->indexable->query_es( $formatted_args, $query->query_vars, $this->index, $query );

		if ( false === $ep_query ) {
			$query->elasticsearch_success = false;
			return $results;
		}

		if ( ! empty( $query->query_vars['count'] ) ) {
			return count( $ep_query['documents'] );
		}

		$query->found_comments        = $ep_query['found_documents']['value'];
		$query->max_num_pages         = $query->query_vars['number'] <= 0 ? 1 : max( 1, ceil( $query->found_comments / absint( $query->query_vars['number'] ) ) );
		$query->elasticsearch_success = true;

		// Determine how we should format the results from ES based on the fields parameter.
		$fields = $query->query_vars['fields'];

		switch ( $fields ) {
			case 'count':
				$new_comments = count( $ep_query['documents'] );
				break;

			case 'ids':
				$new_comments = $this->format_hits_as_ids( $ep_query['documents'], $new_comments );
				break;

			default:
				$new_comments = $this->format_hits_as_comments( $ep_query['documents'], $new_comments, $query->query_vars );
				break;
		}

		return $new_comments;
	}

	/**
	 * Format the ES hits/results as comments objects.
	 *
	 * @param  array $comments The comments that should be formatted.
	 * @param  array $new_comments Array of comments from cache.
	 * @param  array $query_vars Query variables.
	 * @since  3.6.0
	 * @return array
	 */
	protected function format_hits_as_comments( $comments, $new_comments, $query_vars ) {
		$hierarchical = isset( $query_vars['hierarchical'] ) ? $query_vars['hierarchical'] : false;

		foreach ( $comments as $comment_array ) {
			$comment = new \WP_Comment( (object) $comment_array );
			if ( ! empty( $comment_array['site_id'] ) ) {
				$comment->site_id = $comment_array['site_id'];
			} else {
				$comment->site_id = get_current_blog_id();
			}

			$comment->elasticsearch = true; // Super useful for debugging

			if ( $comment ) {
				$new_comments[] = $comment;
			}
		}

		if ( $hierarchical ) {
			$new_comments = $this->fill_descendants( $new_comments, $query_vars );
		}

		return $new_comments;
	}

	/**
	 * Format the ES hits/results as an array of ids.
	 *
	 * @param  array $comments The comments that should be formatted.
	 * @param  array $new_comments Array of comments from cache.
	 * @since  3.6.0
	 * @return array
	 */
	protected function format_hits_as_ids( $comments, $new_comments ) {
		foreach ( $comments as $comment_array ) {
			$new_comments[] = $comment_array['comment_ID'];
		}

		return $new_comments;
	}

	/**
	 * Fetch descendants for located comments.
	 *
	 * @param array $comments Array of top-level comments whose descendants should be filled in.
	 * @param array $query_vars Current query vars.
	 * @since  3.6.0
	 * @return array
	 */
	protected function fill_descendants( $comments, $query_vars ) {
		$levels = [
			0 => $comments,
		];

		// Fetch an entire level of the descendant tree at a time.
		$level        = 0;
		$exclude_keys = [ 'parent', 'parent__in', 'parent__not_in' ];

		do {
			$child_comments = [];
			$_parent_ids    = wp_list_pluck( $levels[ $level ], 'comment_ID' );

			if ( $_parent_ids ) {
				$parent_query_args = $query_vars;

				foreach ( $exclude_keys as $exclude_key ) {
					$parent_query_args[ $exclude_key ] = '';
				}

				$parent_query_args['parent__in']   = $_parent_ids;
				$parent_query_args['hierarchical'] = false;
				$parent_query_args['offset']       = 0;
				$parent_query_args['number']       = 0;

				$formatted_args = $this->indexable->format_args( $parent_query_args );
				$ep_query       = $this->indexable->query_es( $formatted_args, $query_vars, $this->index );

				if ( false === $ep_query ) {
					$level_comments = [];
				} else {
					$level_comments = $this->format_hits_as_comments( $ep_query['documents'], [], [] );
				}

				foreach ( $level_comments as $level_comment ) {
					$child_comments[] = $level_comment;
				}
			}

			$level ++;
			$levels[ $level ] = $child_comments;
		} while ( $child_comments );

		// Pull out just the descendant comments
		$descendants = [];
		for ( $i = 1, $c = count( $levels ); $i < $c; $i++ ) {
			$descendants = array_merge( $descendants, $levels[ $i ] );
		}

		// Assemble a flat array of all comments + descendants.
		$all_comments = $comments;
		foreach ( $descendants as $descendant ) {
			$all_comments[] = $descendant;
		}

		// If a threaded representation was requested, build the tree.
		if ( 'threaded' === $query_vars['hierarchical'] ) {
			$threaded_comments = [];
			$ref               = [];

			foreach ( $all_comments as $c ) {
				// If the comment isn't in the reference array, it goes in the top level of the thread.
				if ( ! isset( $ref[ $c->comment_parent ] ) ) {
					$threaded_comments[ $c->comment_ID ] = $c;
					$ref[ $c->comment_ID ]               = $threaded_comments[ $c->comment_ID ];

					// Otherwise, set it as a child of its parent.
				} else {
					$ref[ $c->comment_parent ]->add_child( $c );
					$ref[ $c->comment_ID ] = $c;
				}
			}

			$comments = $threaded_comments;
		} else {
			$comments = $all_comments;
		}

		return $comments;
	}

}
