<?php
/**
 * Post indexable
 *
 * @since  3.0
 * @package  elasticpress
 */

namespace ElasticPress\Indexable\Post;

use \WP_Query;
use \WP_User;
use ElasticPress\Elasticsearch;
use ElasticPress\Indexable;

if ( ! defined( 'ABSPATH' ) ) {
	// @codeCoverageIgnoreStart
	exit; // Exit if accessed directly.
	// @codeCoverageIgnoreEnd
}

/**
 * Post indexable class
 */
class Post extends Indexable {

	/**
	 * Indexable slug used for identification
	 *
	 * @var   string
	 * @since 3.0
	 */
	public $slug = 'post';

	/**
	 * Flag to indicate if the indexable has support for
	 * `id_range` pagination method during a sync.
	 *
	 * @var boolean
	 * @since 4.1.0
	 */
	public $support_indexing_advanced_pagination = true;

	/**
	 * Create indexable and initialize dependencies
	 *
	 * @since  3.0
	 */
	public function __construct() {
		$this->labels = [
			'plural'   => esc_html__( 'Posts', 'elasticpress' ),
			'singular' => esc_html__( 'Post', 'elasticpress' ),
		];

		$this->sync_manager      = new SyncManager( $this->slug );
		$this->query_integration = new QueryIntegration( $this->slug );
	}

	/**
	 * Query database for posts
	 *
	 * @param  array $args Query DB args
	 * @since  3.0
	 * @return array
	 */
	public function query_db( $args ) {
		$defaults = [
			'posts_per_page'                  => $this->get_bulk_items_per_page(),
			'post_type'                       => $this->get_indexable_post_types(),
			'post_status'                     => $this->get_indexable_post_status(),
			'offset'                          => 0,
			'ignore_sticky_posts'             => true,
			'orderby'                         => 'ID',
			'order'                           => 'desc',
			'no_found_rows'                   => false,
			'ep_indexing_advanced_pagination' => true,
			'has_password'                    => false,
		];

		if ( isset( $args['per_page'] ) ) {
			$args['posts_per_page'] = $args['per_page'];
		}

		if ( isset( $args['include'] ) ) {
			$args['post__in'] = $args['include'];
		}

		if ( isset( $args['exclude'] ) ) {
			$args['post__not_in'] = $args['exclude'];
		}

		/**
		 * Filter arguments used to query posts from database
		 *
		 * @hook ep_post_query_db_args
		 * @param  {array} $args Database arguments
		 * @return  {array} New arguments
		 */
		$args = apply_filters( 'ep_index_posts_args', apply_filters( 'ep_post_query_db_args', wp_parse_args( $args, $defaults ) ) );

		if ( isset( $args['post__in'] ) || 0 < $args['offset'] ) {
			// Disable advanced pagination. Not useful if only indexing specific IDs.
			$args['ep_indexing_advanced_pagination'] = false;
		}

		// Enforce the following query args during advanced pagination to ensure things work correctly.
		if ( $args['ep_indexing_advanced_pagination'] ) {
			$args = array_merge(
				$args,
				[
					'suppress_filters' => false,
					'orderby'          => 'ID',
					'order'            => 'DESC',
					'paged'            => 1,
					'offset'           => 0,
					'no_found_rows'    => true,
				]
			);
			add_filter( 'posts_where', array( $this, 'bulk_indexing_filter_posts_where' ), 9999, 2 );

			$query         = new WP_Query( $args );
			$total_objects = $this->get_total_objects_for_query( $args );

			remove_filter( 'posts_where', array( $this, 'bulk_indexing_filter_posts_where' ), 9999, 2 );
		} else {
			$query         = new WP_Query( $args );
			$total_objects = $query->found_posts;
		}

		return [
			'objects'       => $query->posts,
			'total_objects' => $total_objects,
		];
	}

		/**
		 * Manipulate the WHERE clause of the bulk indexing query to paginate by ID in order to avoid performance issues with SQL offset.
		 *
		 * @param string   $where The current $where clause.
		 * @param WP_Query $query WP_Query object.
		 * @return string WHERE clause with our pagination added if needed.
		 */
	public function bulk_indexing_filter_posts_where( $where, $query ) {
		$using_advanced_pagination = $query->get( 'ep_indexing_advanced_pagination', false );

		if ( $using_advanced_pagination ) {
			$requested_upper_limit_id      = $query->get( 'ep_indexing_upper_limit_object_id', PHP_INT_MAX );
			$requested_lower_limit_post_id = $query->get( 'ep_indexing_lower_limit_object_id', 0 );
			$last_processed_id             = $query->get( 'ep_indexing_last_processed_object_id', null );

			// On the first loopthrough we begin with the requested upper limit ID. Afterwards, use the last processed ID to paginate.
			$upper_limit_range_post_id = $requested_upper_limit_id;
			if ( is_numeric( $last_processed_id ) ) {
				$upper_limit_range_post_id = $last_processed_id - 1;
			}

			// Sanitize. Abort if unexpected data at this point.
			if ( ! is_numeric( $upper_limit_range_post_id ) || ! is_numeric( $requested_lower_limit_post_id ) ) {
				return $where;
			}

			$range = [
				'upper_limit' => "{$GLOBALS['wpdb']->posts}.ID <= {$upper_limit_range_post_id}",
				'lower_limit' => "{$GLOBALS['wpdb']->posts}.ID >= {$requested_lower_limit_post_id}",
			];

			// Skip the end range if it's unnecessary.
			$skip_ending_range = 0 === $requested_lower_limit_post_id;
			$where             = $skip_ending_range ? "AND {$range['upper_limit']} {$where}" : "AND {$range['upper_limit']} AND {$range['lower_limit']} {$where}";
		}

		return $where;
	}

	/**
	 * Get SQL_CALC_FOUND_ROWS for a specific query based on it's args.
	 *
	 * @param array $query_args The query args.
	 * @return int The query result's found_posts.
	 */
	protected function get_total_objects_for_query( $query_args ) {
		static $object_counts = [];

		// Reset the pagination-related args for optimal caching.
		$normalized_query_args = array_merge(
			$query_args,
			[
				'offset'                               => 0,
				'paged'                                => 1,
				'posts_per_page'                       => 1,
				'no_found_rows'                        => false,
				'ep_indexing_last_processed_object_id' => null,
			]
		);

		$cache_key = md5( get_current_blog_id() . wp_json_encode( $normalized_query_args ) );

		if ( ! isset( $object_counts[ $cache_key ] ) ) {
			$object_counts[ $cache_key ] = ( new WP_Query( $normalized_query_args ) )->found_posts;
		}

		if ( 0 === $object_counts[ $cache_key ] ) {
			// Do a DB count to make sure the query didn't just die and return 0.
			$db_post_count = $this->get_total_objects_for_query_from_db( $normalized_query_args );

			if ( $db_post_count !== $object_counts[ $cache_key ] ) {
				$object_counts[ $cache_key ] = $db_post_count;
			}
		}

		return $object_counts[ $cache_key ];
	}

	/**
	 * Get total posts from DB for a specific query based on it's args.
	 *
	 * @param array $query_args The query args.
	 * @since 4.0.0
	 * @return int The total posts.
	 */
	protected function get_total_objects_for_query_from_db( $query_args ) {
		global $wpdb;

		$post_count = 0;

		if ( ! isset( $query_args['post_type'] ) || isset( $query_args['ep_indexing_upper_limit_object_id'] )
		|| isset( $query_args['ep_indexing_lower_limit_object_id'] ) ) {
			return $post_count;
		}

		foreach ( $query_args['post_type'] as $post_type ) {
			$post_counts_by_post_status = wp_count_posts( $post_type );
			foreach ( $post_counts_by_post_status as $post_status => $post_status_count ) {
				if ( ! in_array( $post_status, $query_args['post_status'], true ) ) {
					continue;
				}
				$post_count += $post_status_count;
			}
		}

		/**
		 * As `wp_count_posts` will also count posts with password, we need to remove
		 * them from the final count if they will not be used.
		 *
		 * The if below will pass if `has_password` is false but not null.
		 */
		if ( isset( $query_args['has_password'] ) && ! $query_args['has_password'] ) {
			$posts_with_password = (int) $wpdb->get_var( "SELECT COUNT(1) AS posts_with_password FROM {$wpdb->posts} WHERE post_password != ''" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

			$post_count -= $posts_with_password;
		}

		return $post_count;
	}

	/**
	 * Returns indexable post types for the current site
	 *
	 * @since 0.9
	 * @return mixed|void
	 */
	public function get_indexable_post_types() {
		$post_types = get_post_types( array( 'public' => true ) );

		/**
		 * Remove attachments by default
		 *
		 * @since  3.0
		 */
		unset( $post_types['attachment'] );

		/**
		 * Filter indexable post types
		 *
		 * @hook ep_indexable_post_types
		 * @param  {array} $post_types Indexable post types
		 * @return  {array} New post types
		 */
		return apply_filters( 'ep_indexable_post_types', $post_types );
	}

	/**
	 * Return indexable post_status for the current site
	 *
	 * @since 1.3
	 * @return array
	 */
	public function get_indexable_post_status() {
		/**
		 * Filter indexable post statuses
		 *
		 * @hook ep_indexable_post_status
		 * @param  {array} $post_statuses Indexable post statuses
		 * @return  {array} New post statuses
		 */
		return apply_filters( 'ep_indexable_post_status', array( 'publish' ) );
	}

	/**
	 * Determine required mapping file
	 *
	 * @since 3.6.2
	 * @return string
	 */
	public function get_mapping_name() {
		$es_version = Elasticsearch::factory()->get_elasticsearch_version();

		if ( empty( $es_version ) ) {
			/**
			 * Filter fallback Elasticsearch version
			 *
			 * @hook ep_fallback_elasticsearch_version
			 * @param {string} $version Fall back Elasticsearch version
			 * @return  {string} New version
			 */
			$es_version = apply_filters( 'ep_fallback_elasticsearch_version', '2.0' );
		}
		$es_version = (string) $es_version;

		$mapping_file = '7-0.php';

		if ( version_compare( $es_version, '7.0', '<' ) ) {
			$mapping_file = '5-2.php';
		}

		return apply_filters( 'ep_post_mapping_version', $mapping_file );
	}

	/**
	 * Generate the mapping array
	 *
	 * @since 4.1.0
	 * @return array
	 */
	public function generate_mapping() {
		$mapping_file = $this->get_mapping_name();

		/**
		 * Filter post indexable mapping file
		 *
		 * @hook ep_post_mapping_file
		 * @param {string} $file Path to file
		 * @return  {string} New file path
		 */
		$mapping = require apply_filters( 'ep_post_mapping_file', __DIR__ . '/../../../mappings/post/' . $mapping_file );

		/**
		 * Filter post indexable mapping
		 *
		 * @hook ep_post_mapping
		 * @param {array} $mapping Mapping
		 * @return  {array} New mapping
		 */
		$mapping = apply_filters( 'ep_post_mapping', $mapping );

		delete_transient( 'ep_post_mapping_version' );

		return $mapping;
	}

	/**
	 * Determine version of mapping currently on the post index.
	 *
	 * @since 3.6.2
	 * @return string|WP_Error|false $version
	 */
	public function determine_mapping_version() {
		$version = get_transient( 'ep_post_mapping_version' );

		if ( empty( $version ) ) {
			$index   = $this->get_index_name();
			$mapping = Elasticsearch::factory()->get_mapping( $index );

			if ( empty( $mapping ) ) {
				return new \WP_Error( 'ep_failed_mapping_version', esc_html__( 'Error while fetching the mapping version.', 'elasticpress' ) );
			}

			if ( ! isset( $mapping[ $index ] ) ) {
				return false;
			}

			$version = $this->determine_mapping_version_based_on_existing( $mapping, $index );

			set_transient(
				'ep_post_mapping_version',
				$version,
				/**
				 * Filter the post mapping version cache expiration.
				 *
				 * @hook ep_post_mapping_version_cache_expiration
				 * @since 3.6.5
				 * @param  {int} $version Time in seconds for the transient expiration
				 * @return {int} New time
				 */
				apply_filters( 'ep_post_mapping_version_cache_expiration', DAY_IN_SECONDS )
			);
		}

		/**
		 * Filter the mapping version for posts.
		 *
		 * @hook ep_post_mapping_version_determined
		 * @since 3.6.2
		 * @param {string} $version Determined version string
		 * @return  {string} New version string
		 */
		return apply_filters( 'ep_post_mapping_version_determined', $version );
	}

	/**
	 * Prepare a post for syncing
	 *
	 * @param int $post_id Post ID.
	 * @since 0.9.1
	 * @return bool|array
	 */
	public function prepare_document( $post_id ) {
		global $post;
		$post = get_post( $post_id );
		setup_postdata( $post );

		if ( empty( $post ) ) {
			return false;
		}

		$user = get_userdata( $post->post_author );

		if ( $user instanceof WP_User ) {
			$user_data = array(
				'raw'          => $user->user_login,
				'login'        => $user->user_login,
				'display_name' => $user->display_name,
				'id'           => $user->ID,
			);
		} else {
			$user_data = array(
				'raw'          => '',
				'login'        => '',
				'display_name' => '',
				'id'           => '',
			);
		}

		$post_date         = $post->post_date;
		$post_date_gmt     = $post->post_date_gmt;
		$post_modified     = $post->post_modified;
		$post_modified_gmt = $post->post_modified_gmt;
		$comment_count     = absint( $post->comment_count );
		$comment_status    = $post->comment_status;
		$ping_status       = $post->ping_status;
		$menu_order        = (int) $post->menu_order;

		/**
		 * Filter to ignore invalid dates
		 *
		 * @hook ep_ignore_invalid_dates
		 * @param  {bool} $ignore True to ignore
		 * @param {int} $post_id Post ID
		 * @param  {WP_Post} $post Post object
		 * @return  {bool} New ignore value
		 */
		if ( apply_filters( 'ep_ignore_invalid_dates', true, $post_id, $post ) ) {
			if ( ! strtotime( $post_date ) || '0000-00-00 00:00:00' === $post_date ) {
				$post_date = null;
			}

			if ( ! strtotime( $post_date_gmt ) || '0000-00-00 00:00:00' === $post_date_gmt ) {
				$post_date_gmt = null;
			}

			if ( ! strtotime( $post_modified ) || '0000-00-00 00:00:00' === $post_modified ) {
				$post_modified = null;
			}

			if ( ! strtotime( $post_modified_gmt ) || '0000-00-00 00:00:00' === $post_modified_gmt ) {
				$post_modified_gmt = null;
			}
		}

		// To prevent infinite loop, we don't queue when updated_postmeta.
		remove_action( 'updated_postmeta', [ $this->sync_manager, 'action_queue_meta_sync' ], 10 );

		/**
		 * Filter to allow indexing of filtered post content
		 *
		 * @hook ep_allow_post_content_filtered_index
		 * @param  {bool} $ignore True to allow
		 * @return  {bool} New value
		 */
		$post_content_filtered_allowed = apply_filters( 'ep_allow_post_content_filtered_index', true );

		$post_args = array(
			'post_id'               => $post_id,
			'ID'                    => $post_id,
			'post_author'           => $user_data,
			'post_date'             => $post_date,
			'post_date_gmt'         => $post_date_gmt,
			'post_title'            => $post->post_title,
			'post_excerpt'          => $post->post_excerpt,
			'post_content_filtered' => $post_content_filtered_allowed ? apply_filters( 'the_content', $post->post_content ) : '',
			'post_content'          => $post->post_content,
			'post_status'           => $post->post_status,
			'post_name'             => $post->post_name,
			'post_modified'         => $post_modified,
			'post_modified_gmt'     => $post_modified_gmt,
			'post_parent'           => $post->post_parent,
			'post_type'             => $post->post_type,
			'post_mime_type'        => $post->post_mime_type,
			'permalink'             => get_permalink( $post_id ),
			'terms'                 => $this->prepare_terms( $post ),
			'meta'                  => $this->prepare_meta_types( $this->prepare_meta( $post ) ), // post_meta removed in 2.4.
			'date_terms'            => $this->prepare_date_terms( $post_date ),
			'comment_count'         => $comment_count,
			'comment_status'        => $comment_status,
			'ping_status'           => $ping_status,
			'menu_order'            => $menu_order,
			'guid'                  => $post->guid,
			'thumbnail'             => $this->prepare_thumbnail( $post ),
		);

		/**
		 * Filter sync arguments for a post. For backwards compatibility.
		 *
		 * @hook ep_post_sync_args
		 * @param  {array} $post_args Post arguments
		 * @param  {int} $post_id Post ID
		 * @return  {array} New arguments
		 */
		$post_args = apply_filters( 'ep_post_sync_args', $post_args, $post_id );

		/**
		 * Filter sync arguments for a post after meta preparation.
		 *
		 * @hook ep_post_sync_args_post_prepare_meta
		 * @param  {array} $post_args Post arguments
		 * @param  {int} $post_id Post ID
		 * @return  {array} New arguments
		 */
		$post_args = apply_filters( 'ep_post_sync_args_post_prepare_meta', $post_args, $post_id );

		// Turn back on updated_postmeta hook
		add_action( 'updated_postmeta', [ $this->sync_manager, 'action_queue_meta_sync' ], 10, 4 );

		return $post_args;
	}

	/**
	 * Prepare thumbnail to send to ES.
	 *
	 * @param WP_Post $post Post object.
	 * @return array|null Thumbnail data.
	 */
	public function prepare_thumbnail( $post ) {
		$attachment_id = get_post_thumbnail_id( $post );

		if ( ! $attachment_id ) {
			return null;
		}

		/**
		 * Filters the image size to use when indexing the post thumbnail.
		 *
		 * Defaults to the `woocommerce_thumbnail` size if WooCommerce is in
		 * use. Otherwise the `thumbnail` size is used.
		 *
		 * @hook ep_thumbnail_image_size
		 * @since 4.0.0
		 * @param {string|int[]} $image_size Image size. Can be any registered
		 *                                 image size name, or an array of
		 *                                 width and height values in pixels
		 *                                 (in that order).
		 * @param {WP_Post} $post Post being indexed.
		 * @return {array} Image size to pass to wp_get_attachment_image_src().
		 */
		$image_size = apply_filters(
			'ep_post_thumbnail_image_size',
			function_exists( 'WC' ) ? 'woocommerce_thumbnail' : 'thumbnail',
			$post
		);

		$image_src = wp_get_attachment_image_src( $attachment_id, $image_size );
		$image_alt = trim( wp_strip_all_tags( get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ) );

		if ( ! $image_src ) {
			return null;
		}

		return [
			'ID'     => $attachment_id,
			'src'    => $image_src[0],
			'width'  => $image_src[1],
			'height' => $image_src[2],
			'alt'    => $image_alt,
		];
	}

	/**
	 * Prepare date terms to send to ES.
	 *
	 * @param string $date_to_prepare Post date
	 * @since 0.1.4
	 * @return array
	 */
	public function prepare_date_terms( $date_to_prepare ) {
		$terms_to_prepare = [
			'year'          => 'Y',
			'month'         => 'm',
			'week'          => 'W',
			'dayofyear'     => 'z',
			'day'           => 'd',
			'dayofweek'     => 'w',
			'dayofweek_iso' => 'N',
			'hour'          => 'H',
			'minute'        => 'i',
			'second'        => 's',
			'm'             => 'Ym', // yearmonth
		];

		// Combine all the date term formats and perform one single call to date_i18n() for performance.
		$date_format    = implode( '||', array_values( $terms_to_prepare ) );
		$combined_dates = explode( '||', date_i18n( $date_format, strtotime( $date_to_prepare ) ) );

		// Then split up the results for individual indexing.
		$date_terms = [];
		foreach ( $terms_to_prepare as $term_name => $date_format ) {
			$index_in_combined_format = array_search( $term_name, array_keys( $terms_to_prepare ), true );
			$date_terms[ $term_name ] = (int) $combined_dates[ $index_in_combined_format ];
		}

		return $date_terms;
	}

	/**
	 * Get an array of taxonomies that are indexable for the given post
	 *
	 * @since 4.0.0
	 * @param WP_Post $post Post object
	 * @return array Array of WP_Taxonomy objects that should be indexed
	 */
	public function get_indexable_post_taxonomies( $post ) {
		$taxonomies          = get_object_taxonomies( $post->post_type, 'objects' );
		$selected_taxonomies = [];

		foreach ( $taxonomies as $taxonomy ) {
			if ( $taxonomy->public || $taxonomy->publicly_queryable ) {
				$selected_taxonomies[] = $taxonomy;
			}
		}

		/**
		 * Filter taxonomies to be synced with post
		 *
		 * @hook ep_sync_taxonomies
		 * @param  {array} $selected_taxonomies Selected taxonomies
		 * @param  {WP_Post} Post object
		 * @return  {array} New taxonomies
		 */
		$selected_taxonomies = (array) apply_filters( 'ep_sync_taxonomies', $selected_taxonomies, $post );

		// Important we validate here to ensure there are no invalid taxonomy values returned from the filter, as just one would cause wp_get_object_terms() to fail.
		$validated_taxonomies = [];
		foreach ( $selected_taxonomies as $selected_taxonomy ) {
			// If we get a taxonomy name, we need to convert it to taxonomy object
			if ( ! is_object( $selected_taxonomy ) && taxonomy_exists( (string) $selected_taxonomy ) ) {
				$selected_taxonomy = get_taxonomy( $selected_taxonomy );
			}

			// We check if the $taxonomy object has a valid name property. Backward compatibility since WP_Taxonomy introduced in WP 4.7
			if ( ! is_a( $selected_taxonomy, '\WP_Taxonomy' ) || ! property_exists( $selected_taxonomy, 'name' ) || ! taxonomy_exists( $selected_taxonomy->name ) ) {
				continue;
			}

			$validated_taxonomies[] = $selected_taxonomy;
		}

		return $validated_taxonomies;
	}

	/**
	 * Prepare terms to send to ES.
	 *
	 * @param WP_Post $post Post object
	 * @since 0.1.0
	 * @return array
	 */
	private function prepare_terms( $post ) {
		$selected_taxonomies = $this->get_indexable_post_taxonomies( $post );

		if ( empty( $selected_taxonomies ) ) {
			return [];
		}

		$terms = [];

		/**
		 * Filter to allow child terms to be indexed
		 *
		 * @hook ep_sync_terms_allow_hierarchy
		 * @param  {bool} $allow True means allow
		 * @return  {bool} New value
		 */
		$allow_hierarchy = apply_filters( 'ep_sync_terms_allow_hierarchy', true );

		foreach ( $selected_taxonomies as $taxonomy ) {
			$object_terms = get_the_terms( $post->ID, $taxonomy->name );

			if ( ! $object_terms || is_wp_error( $object_terms ) ) {
				continue;
			}

			$terms_dic = [];

			foreach ( $object_terms as $term ) {
				if ( ! isset( $terms_dic[ $term->term_id ] ) ) {
					$terms_dic[ $term->term_id ] = $this->get_formatted_term( $term, $post->ID );

					if ( $allow_hierarchy ) {
						$terms_dic = $this->get_parent_terms( $terms_dic, $term, $taxonomy->name, $post->ID );
					}
				}
			}
			$terms[ $taxonomy->name ] = array_values( $terms_dic );
		}

		return $terms;
	}

	/**
	 * Recursively get all the ancestor terms of the given term
	 *
	 * @param array   $terms     Terms array
	 * @param WP_Term $term      Current term
	 * @param string  $tax_name  Taxonomy
	 * @param int     $object_id Post ID
	 *
	 * @return array
	 */
	private function get_parent_terms( $terms, $term, $tax_name, $object_id ) {
		$parent_term = get_term( $term->parent, $tax_name );
		if ( ! $parent_term || is_wp_error( $parent_term ) ) {
			return $terms;
		}
		if ( ! isset( $terms[ $parent_term->term_id ] ) ) {
			$terms[ $parent_term->term_id ] = $this->get_formatted_term( $parent_term, $object_id );

		}
		return $this->get_parent_terms( $terms, $parent_term, $tax_name, $object_id );
	}

	/**
	 * Given a term, format it to be appended to the post ES document.
	 *
	 * @since 4.5.0
	 * @param \WP_Term $term    Term to be formatted
	 * @param int      $post_id The post ID
	 * @return array
	 */
	private function get_formatted_term( \WP_Term $term, int $post_id ) : array {
		$formatted_term = [
			'term_id'          => $term->term_id,
			'slug'             => $term->slug,
			'name'             => $term->name,
			'parent'           => $term->parent,
			'term_taxonomy_id' => $term->term_taxonomy_id,
			'term_order'       => (int) $this->get_term_order( $term->term_taxonomy_id, $post_id ),
		];

		/**
		 * As the name implies, the facet attribute is used to list all terms in facets.
		 * As in facets, the term_order associated with a post does not matter, we set it as 0 here.
		 * Note that this is set as 0 instead of simply removed to keep backward compatibility.
		 */
		$term_facet               = $formatted_term;
		$term_facet['term_order'] = 0;
		$formatted_term['facet']  = wp_json_encode( $term_facet );

		return $formatted_term;
	}

	/**
	 * Retreives term order for the object/term_taxonomy_id combination
	 *
	 * @param int $term_taxonomy_id Term Taxonomy ID
	 * @param int $object_id        Post ID
	 *
	 * @return int Term Order
	 */
	protected function get_term_order( $term_taxonomy_id, $object_id ) {
		global $wpdb;

		$cache_key   = "{$object_id}_term_order";
		$term_orders = wp_cache_get( $cache_key );

		if ( false === $term_orders ) {
			$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->prepare(
					"SELECT term_taxonomy_id, term_order from $wpdb->term_relationships where object_id=%d;",
					$object_id
				),
				ARRAY_A
			);

			$term_orders = [];

			foreach ( $results as $result ) {
				$term_orders[ $result['term_taxonomy_id'] ] = $result['term_order'];
			}

			wp_cache_set( $cache_key, $term_orders );
		}

		return isset( $term_orders[ $term_taxonomy_id ] ) ? (int) $term_orders[ $term_taxonomy_id ] : 0;

	}

	/**
	 * Checks if meta key is allowed
	 *
	 * @param string  $meta_key meta key to check
	 * @param WP_Post $post Post object
	 * @since 4.3.0
	 * @return boolean
	 */
	public function is_meta_allowed( $meta_key, $post ) {
		$test_metas = [
			$meta_key => true,
		];

		$filtered_test_metas = $this->filter_allowed_metas( $test_metas, $post );

		return array_key_exists( $meta_key, $filtered_test_metas );
	}

	/**
	 * Filter post meta to only the allowed ones to be send to ES
	 *
	 * @param array   $metas Key => value pairs of post meta
	 * @param WP_Post $post Post object
	 * @since 4.3.0
	 * @return array
	 */
	public function filter_allowed_metas( $metas, $post ) {
		$filtered_metas = [];

		/**
		 * Filter indexable protected meta keys for posts
		 *
		 * @hook ep_prepare_meta_allowed_protected_keys
		 * @param  {array} $keys Allowed protected keys
		 * @param  {WP_Post} $post Post object
		 * @since  1.7
		 * @return  {array} New keys
		 */
		$allowed_protected_keys = apply_filters( 'ep_prepare_meta_allowed_protected_keys', [], $post );

		/**
		 * Filter public keys to exclude from indexed post
		 *
		 * @hook ep_prepare_meta_excluded_public_keys
		 * @param  {array} $keys Excluded protected keys
		 * @param  {WP_Post} $post Post object
		 * @since  1.7
		 * @return  {array} New keys
		 */
		$excluded_public_keys = apply_filters( 'ep_prepare_meta_excluded_public_keys', [], $post );

		foreach ( $metas as $key => $value ) {

			$allow_index = false;

			if ( is_protected_meta( $key ) ) {

				if ( true === $allowed_protected_keys || in_array( $key, $allowed_protected_keys, true ) ) {
					$allow_index = true;
				}
			} else {

				if ( true !== $excluded_public_keys && ! in_array( $key, $excluded_public_keys, true ) ) {
					$allow_index = true;
				}
			}

			/**
			 * Filter force whitelisting a meta key
			 *
			 * @hook ep_prepare_meta_whitelist_key
			 * @param  {bool} $whitelist True to whitelist key
			 * @param  {string} $key Meta key
			 * @param  {WP_Post} $post Post object
			 * @return  {bool} New whitelist value
			 */
			if ( true === $allow_index || apply_filters( 'ep_prepare_meta_whitelist_key', false, $key, $post ) ) {
				$filtered_metas[ $key ] = $value;
			}
		}
		return $filtered_metas;
	}

	/**
	 * Prepare post meta to send to ES
	 *
	 * @param WP_Post $post Post object
	 * @since 0.1.0
	 * @return array
	 */
	public function prepare_meta( $post ) {
		/**
		 * Filter pre-prepare meta for a post
		 *
		 * @hook ep_prepare_meta_data
		 * @param  {array} $meta Meta data
		 * @param  {WP_Post} $post Post object
		 * @return  {array} New meta
		 */
		$meta = apply_filters( 'ep_prepare_meta_data', (array) get_post_meta( $post->ID ), $post );

		if ( empty( $meta ) ) {
			/**
			 * Filter final list of prepared meta.
			 *
			 * @hook ep_prepared_post_meta
			 * @param  {array} $prepared_meta Prepared meta
			 * @param  {WP_Post} $post Post object
			 * @since  3.4
			 * @return  {array} Prepared meta
			 */
			return apply_filters( 'ep_prepared_post_meta', [], $post );
		}

		$filtered_metas = $this->filter_allowed_metas( $meta, $post );
		$prepared_meta  = [];

		foreach ( $filtered_metas as $key => $value ) {
			if ( ! empty( $key ) ) {
				$prepared_meta[ $key ] = maybe_unserialize( $value );
			}
		}

		/**
		 * Filter final list of prepared meta.
		 *
		 * @hook ep_prepared_post_meta
		 * @param  {array} $prepared_meta Prepared meta
		 * @param  {WP_Post} $post Post object
		 * @since  3.4
		 * @return  {array} Prepared meta
		 */
		return apply_filters( 'ep_prepared_post_meta', $prepared_meta, $post );

	}

	/**
	 * Format WP query args for ES
	 *
	 * @param  array    $args     WP_Query arguments.
	 * @param  WP_Query $wp_query WP_Query object
	 * @since 0.9.0
	 * @return array
	 */
	public function format_args( $args, $wp_query ) {
		$args = $this->sanitize_wp_query_args( $args );

		$formatted_args = [
			'from' => $this->parse_from( $args ),
			'size' => $this->parse_size( $args ),
		];

		$filters = $this->parse_filters( $args, $wp_query );

		if ( ! empty( $filters ) ) {
			$formatted_args['post_filter'] = $filters;
		}

		$formatted_args = $this->maybe_set_search_fields( $formatted_args, $args );
		$formatted_args = $this->maybe_set_fields( $formatted_args, $args );
		$formatted_args = $this->maybe_orderby( $formatted_args, $args );
		$formatted_args = $this->maybe_add_sticky_posts( $formatted_args, $args );
		$formatted_args = $this->maybe_set_aggs( $formatted_args, $args, $filters );

		/**
		 * Filter formatted Elasticsearch [ost ]query (entire query)
		 *
		 * @hook ep_formatted_args
		 * @param {array} $formatted_args Formatted Elasticsearch query
		 * @param {array} $query_vars Query variables
		 * @param {array} $query Query part
		 * @return  {array} New query
		 */
		$formatted_args = apply_filters( 'ep_formatted_args', $formatted_args, $args, $wp_query );

		/**
		 * Filter formatted Elasticsearch [ost ]query (entire query)
		 *
		 * @hook ep_post_formatted_args
		 * @param {array} $formatted_args Formatted Elasticsearch query
		 * @param {array} $query_vars Query variables
		 * @param {array} $query Query part
		 * @return  {array} New query
		 */
		$formatted_args = apply_filters( 'ep_post_formatted_args', $formatted_args, $args, $wp_query );

		return $formatted_args;
	}

	/**
	 * Adjust the fuzziness parameter if needed.
	 *
	 * If using fields with type `long`, queries should not have a fuzziness parameter.
	 *
	 * @param array  $query         Current query
	 * @param array  $query_vars    Query variables
	 * @param string $search_text   Search text
	 * @param array  $search_fields Search fields
	 * @return array New query
	 */
	public function adjust_query_fuzziness( $query, $query_vars, $search_text, $search_fields ) {
		if ( empty( array_intersect( $search_fields, [ 'ID', 'post_id', 'post_parent' ] ) ) ) {
			return $query;
		}

		if ( ! isset( $query['bool'] ) || ! isset( $query['bool']['should'] ) ) {
			return $query;
		}

		foreach ( $query['bool']['should'] as &$clause ) {
			if ( ! isset( $clause['multi_match'] ) ) {
				continue;
			}

			if ( isset( $clause['multi_match']['fuzziness'] ) ) {
				unset( $clause['multi_match']['fuzziness'] );
			}
		}

		return $query;
	}

	/**
	 * Parse and build out our tax query.
	 *
	 * @access protected
	 *
	 * @param array $query Tax query
	 * @return array
	 */
	protected function parse_tax_query( $query ) {
		$tax_query = [
			'tax_filter'          => [],
			'tax_must_not_filter' => [],
		];
		$relation  = '';

		foreach ( $query as $tax_queries ) {
			// If we have a nested tax query, recurse through that
			if ( is_array( $tax_queries ) && empty( $tax_queries['taxonomy'] ) ) {
				$result      = $this->parse_tax_query( $tax_queries );
				$relation    = ( ! empty( $tax_queries['relation'] ) ) ? strtolower( $tax_queries['relation'] ) : 'and';
				$filter_type = 'and' === $relation ? 'must' : 'should';

				// Set the proper filter type and must_not filter, as needed
				if ( ! empty( $result['tax_must_not_filter'] ) ) {
					$tax_query['tax_filter'][] = [
						'bool' => [
							$filter_type => $result['tax_filter'],
							'must_not'   => $result['tax_must_not_filter'],
						],
					];
				} else {
					$tax_query['tax_filter'][] = [
						'bool' => [
							$filter_type => $result['tax_filter'],
						],
					];
				}
			}

			// Parse each individual tax query part
			$single_tax_query = $tax_queries;
			if ( ! empty( $single_tax_query['taxonomy'] ) ) {
				$terms = isset( $single_tax_query['terms'] ) ? (array) $single_tax_query['terms'] : array();
				$field = $this->parse_tax_query_field( $single_tax_query['field'] );

				if ( 'slug' === $field ) {
					$terms = array_map( 'sanitize_title', $terms );
				}

				// Set up our terms object
				$terms_obj = array(
					'terms.' . $single_tax_query['taxonomy'] . '.' . $field => array_values( array_filter( $terms ) ),
				);

				$operator = ( ! empty( $single_tax_query['operator'] ) ) ? strtolower( $single_tax_query['operator'] ) : 'in';

				switch ( $operator ) {
					case 'exists':
						/**
						 * add support for "EXISTS" operator
						 *
						 * @since 2.5
						 */
						$tax_query['tax_filter'][]['bool'] = array(
							'must' => array(
								array(
									'exists' => array(
										'field' => key( $terms_obj ),
									),
								),
							),
						);

						break;
					case 'not exists':
						/**
						 * add support for "NOT EXISTS" operator
						 *
						 * @since 2.5
						 */
						$tax_query['tax_filter'][]['bool'] = array(
							'must_not' => array(
								array(
									'exists' => array(
										'field' => key( $terms_obj ),
									),
								),
							),
						);

						break;
					case 'not in':
						/**
						 * add support for "NOT IN" operator
						 *
						 * @since 2.1
						 */
						// If "NOT IN" than it should filter as must_not
						$tax_query['tax_must_not_filter'][]['terms'] = $terms_obj;

						break;
					case 'and':
						/**
						 * add support for "and" operator
						 *
						 * @since 2.4
						 */
						$and_nest = array(
							'bool' => array(
								'must' => array(),
							),
						);

						foreach ( $terms as $term ) {
							$and_nest['bool']['must'][] = array(
								'terms' => array(
									'terms.' . $single_tax_query['taxonomy'] . '.' . $field => (array) $term,
								),
							);
						}

						$tax_query['tax_filter'][] = $and_nest;

						break;
					case 'in':
					default:
						/**
						 * Default to IN operator
						 */
						// Add the tax query filter
						$tax_query['tax_filter'][]['terms'] = $terms_obj;

						break;
				}
			}
		}

		return $tax_query;
	}

	/**
	 * Parse an 'order' query variable and cast it to ASC or DESC as necessary.
	 *
	 * @since 1.1
	 * @access protected
	 *
	 * @param string $order The 'order' query variable.
	 * @return string The sanitized 'order' query variable.
	 */
	protected function parse_order( $order ) {
		// Core will always set sort order to DESC for any invalid value,
		// so we can't do any automated testing of this function.
		// @codeCoverageIgnoreStart
		if ( ! is_string( $order ) || empty( $order ) ) {
			return 'desc';
		}
		// @codeCoverageIgnoreEnd

		if ( 'ASC' === strtoupper( $order ) ) {
			return 'asc';
		} else {
			return 'desc';
		}
	}

	/**
	 * Convert the alias to a properly-prefixed sort value.
	 *
	 * @since 1.1
	 * @access protected
	 *
	 * @param string $orderbys Alias or path for the field to order by.
	 * @param string $default_order Default order direction
	 * @param  array  $args Query args
	 * @return array
	 */
	protected function parse_orderby( $orderbys, $default_order, $args ) {
		$orderbys = $this->get_orderby_array( $orderbys );

		$from_to = [
			'relevance' => '_score',
			'date'      => 'post_date',
			'type'      => 'post_type.raw',
			'modified'  => 'post_modified',
			'name'      => 'post_name.raw',
			'title'     => 'post_title.sortable',
		];

		$sort = [];

		foreach ( $orderbys as $key => $value ) {
			if ( is_string( $key ) ) {
				$orderby_clause = $key;
				$order          = $value;
			} else {
				$orderby_clause = $value;
				$order          = $default_order;
			}

			if ( empty( $orderby_clause ) || 'rand' === $orderby_clause ) {
				continue;
			}

			/**
			 * If `orderby` is 'none', WordPress will let the database decide on what should be used to order.
			 * It will use the primary key ASC.
			 */
			if ( 'none' === $orderby_clause ) {
				$orderby_clause = 'ID';
				$order          = 'asc';
			}

			if ( ! empty( $from_to[ $orderby_clause ] ) ) {
				$orderby_clause = $from_to[ $orderby_clause ];
			} else {
				$orderby_clause = $this->parse_orderby_meta_fields( $orderby_clause, $args );
			}

			$sort[] = array(
				$orderby_clause => array(
					'order' => $order,
				),
			);
		}

		return $sort;
	}

	/**
	 * Try to parse orderby meta fields
	 *
	 * @since 4.6.0
	 * @param string $orderby_clause Current orderby value
	 * @param array  $args           Query args
	 * @return string New orderby value
	 */
	protected function parse_orderby_meta_fields( $orderby_clause, $args ) {
		global $wpdb;

		$from_to_metatypes = [
			'num'      => 'long',
			'numeric'  => 'long',
			'binary'   => 'value.sortable',
			'char'     => 'value.sortable',
			'date'     => 'date',
			'datetime' => 'datetime',
			'decimal'  => 'double',
			'signed'   => 'long',
			'time'     => 'time',
			'unsigned' => 'long',
		];

		// Code is targeting Elasticsearch directly
		if ( preg_match( '/^meta\.(.*?)\.(.*)/', $orderby_clause, $match_meta ) ) {
			return $orderby_clause;
		}

		// WordPress meta_value_* compatibility
		if ( preg_match( '/^meta_value_?(.*)/', $orderby_clause, $match_type ) ) {
			$meta_type = $from_to_metatypes[ strtolower( $match_type[1] ) ] ?? 'value.sortable';
		}

		if ( ! empty( $args['meta_key'] ) ) {
			$meta_field = $args['meta_key'];
		}

		// Already have everything needed
		if ( isset( $meta_type ) && isset( $meta_field ) ) {
			return "meta.{$meta_field}.{$meta_type}";
		}

		// Don't have any other ways to guess
		if ( empty( $args['meta_query'] ) ) {
			return $orderby_clause;
		}

		$meta_query = new \WP_Meta_Query( $args['meta_query'] );
		// Calling get_sql() to populate the WP_Meta_Query->clauses attribute
		$meta_query->get_sql( 'post', $wpdb->posts, 'ID' );

		$clauses = $meta_query->get_clauses();

		// If it refers to a named meta_query clause
		if ( ! empty( $clauses[ $orderby_clause ] ) ) {
			$meta_field       = $clauses[ $orderby_clause ]['key'];
			$clause_meta_type = strtolower( $clauses[ $orderby_clause ]['type'] ?? $clauses[ $orderby_clause ]['cast'] );
		} else {
			/**
			 * At this point we:
			 * 1. Try to find the meta key in any meta_query clause and use the type WP found
			 * 2. If ordering by `meta_value*`, use the first meta_query clause
			 * 3. Give up and use the orderby clause as is (code could be capturing it later on)
			 */
			$meta_keys_and_types = wp_list_pluck( $clauses, 'cast', 'key' );
			if ( isset( $meta_keys_and_types[ $orderby_clause ] ) ) {
				$meta_field       = $orderby_clause;
				$clause_meta_type = strtolower( $meta_keys_and_types[ $orderby_clause ] ?? $meta_keys_and_types[ $orderby_clause ] );
			} elseif ( isset( $meta_type ) ) {
				$primary_clause = reset( $clauses );
				$meta_field     = $primary_clause['key'];
			} else {
				unset( $meta_type );
				unset( $meta_field );
			}
		}

		if ( ! isset( $meta_type ) && isset( $clause_meta_type ) ) {
			$meta_type = $from_to_metatypes[ $clause_meta_type ] ?? 'value.sortable';
		}

		if ( isset( $meta_type ) && isset( $meta_field ) ) {
			$orderby_clause = "meta.{$meta_field}.{$meta_type}";
		}

		return $orderby_clause;
	}

	/**
	 * Get Order by args Array
	 *
	 * @param string|array $orderbys Order by string or array
	 * @since 2.1
	 * @return array
	 */
	protected function get_orderby_array( $orderbys ) {
		if ( ! is_array( $orderbys ) ) {
			$orderbys = explode( ' ', $orderbys );
		}

		return $orderbys;
	}

	/**
	 * Given a mapping content, try to determine the version used.
	 *
	 * @since 3.6.3
	 *
	 * @param array  $mapping Mapping content.
	 * @param string $index   Index name
	 * @return string         Version of the mapping being used.
	 */
	protected function determine_mapping_version_based_on_existing( $mapping, $index ) {
		if ( isset( $mapping[ $index ]['mappings']['post']['_meta']['mapping_version'] ) ) {
			return $mapping[ $index ]['mappings']['post']['_meta']['mapping_version'];
		}
		if ( isset( $mapping[ $index ]['mappings']['_meta']['mapping_version'] ) ) {
			return $mapping[ $index ]['mappings']['_meta']['mapping_version'];
		}

		/**
		 * Check for 7-0 mapping.
		 * If mapping has a `post` type, it can't be ES 7, as mapping types were removed in that release.
		 *
		 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/removal-of-types.html
		 */
		if ( ! isset( $mapping[ $index ]['mappings']['post'] ) ) {
			return '7-0.php';
		}

		$post_mapping = $mapping[ $index ]['mappings']['post'];

		/**
		 * Starting at this point, our tests rely on the post_title.fields.sortable field.
		 * As this field is present in all our mappings, if this field is not present in
		 * the mapping, this is a custom mapping.
		 *
		 * To have this code working with custom mappings, use the `ep_post_mapping_version_determined` filter.
		 */
		if ( ! isset( $post_mapping['properties']['post_title']['fields']['sortable'] ) ) {
			return 'unknown';
		}

		$post_title_sortable = $post_mapping['properties']['post_title']['fields']['sortable'];

		/**
		 * Check for 5-2 mapping.
		 * Normalizers on keyword fields were only made available in ES 5.2
		 *
		 * @see https://www.elastic.co/guide/en/elasticsearch/reference/5.2/release-notes-5.2.0.html
		 */
		if ( isset( $post_title_sortable['normalizer'] ) ) {
			return '5-2.php';
		}

		return 'unknown';
	}

	/**
	 * Given ES args, add aggregations to it.
	 *
	 * @since 4.1.0
	 * @param array   $formatted_args Formatted Elasticsearch query
	 * @param array   $agg            Aggregation data.
	 * @param boolean $use_filters    Whether filters should be used or not.
	 * @param array   $filter         Filters defined so far.
	 * @return array Formatted Elasticsearch query with the aggregation added.
	 */
	protected function apply_aggregations( $formatted_args, $agg, $use_filters, $filter ) {
		if ( empty( $agg['aggs'] ) ) {
			return $formatted_args;
		}

		// Add a name to the aggregation if it was passed through
		$agg_name = ( ! empty( $agg['name'] ) ) ? $agg['name'] : 'aggregation_name';

		// Add/use the filter if warranted
		if ( isset( $agg['use-filter'] ) && false !== $agg['use-filter'] && $use_filters ) {

			// If a filter is being used, use it on the aggregation as well to receive relevant information to the query
			$formatted_args['aggs'][ $agg_name ]['filter'] = $filter;
			$formatted_args['aggs'][ $agg_name ]['aggs']   = $agg['aggs'];
		} else {
			$formatted_args['aggs'][ $agg_name ] = $agg['aggs'];
		}

		return $formatted_args;
	}

	/**
	 * Get the search algorithm that should be used.
	 *
	 * @since 4.3.0
	 * @param string $search_text   Search term(s)
	 * @param array  $search_fields Search fields
	 * @param array  $query_vars    Query vars
	 * @return SearchAlgorithm Instance of search algorithm to be used
	 */
	public function get_search_algorithm( string $search_text, array $search_fields, array $query_vars ) : \ElasticPress\SearchAlgorithm {
		$search_algorithm_version_option = \ElasticPress\Utils\get_option( 'ep_search_algorithm_version', '4.0' );

		/**
		 * Filter the algorithm version to be used.
		 *
		 * @since  3.5
		 * @hook ep_search_algorithm_version
		 * @param  {string} $search_algorithm_version Algorithm version.
		 * @return  {string} New algorithm version
		 */
		$search_algorithm = apply_filters( 'ep_search_algorithm_version', $search_algorithm_version_option );

		/**
		 * Filter the search algorithm to be used
		 *
		 * @hook ep_{$indexable_slug}_search_algorithm
		 * @since  4.3.0
		 * @param  {string} $search_algorithm Slug of the search algorithm used as fallback
		 * @param  {string} $search_term      Search term
		 * @param  {array}  $search_fields    Fields to be searched
		 * @param  {array}  $query_vars       Query variables
		 * @return {string} New search algorithm slug
		 */
		$search_algorithm = apply_filters( "ep_{$this->slug}_search_algorithm", $search_algorithm, $search_text, $search_fields, $query_vars );

		return \ElasticPress\SearchAlgorithms::factory()->get( $search_algorithm );
	}

	/**
	 * Based on WP_Query arguments, parses the various filters that could be applied into the ES query.
	 *
	 * @since 4.4.0
	 * @param array    $args  WP_Query arguments
	 * @param WP_Query $query WP_Query object
	 * @return array
	 */
	protected function parse_filters( $args, $query ) {
		/**
		 * A note about the order of this array indices:
		 * As previously there was no way to access each part, some snippets might be accessing
		 * these filters by its usual numeric indices (see the array_values() call below.)
		 */
		$filters = [
			'tax_query'           => $this->parse_tax_queries( $args, $query ),
			'post_parent'         => $this->parse_post_parent( $args ),
			'post_parent__in'     => $this->parse_post_parent__in( $args ),
			'post_parent__not_in' => $this->parse_post_parent__not_in( $args ),
			'post__in'            => $this->parse_post__in( $args ),
			'post_name__in'       => $this->parse_post_name__in( $args ),
			'post__not_in'        => $this->parse_post__not_in( $args ),
			'category__not_in'    => $this->parse_category__not_in( $args ),
			'tag__not_in'         => $this->parse_tag__not_in( $args ),
			'author'              => $this->parse_author( $args ),
			'post_mime_type'      => $this->parse_post_mime_type( $args ),
			'date'                => $this->parse_date( $args ),
			'meta_query'          => $this->parse_meta_queries( $args ),
			'post_type'           => $this->parse_post_type( $args ),
			'post_status'         => $this->parse_post_status( $args ),
		];

		/**
		 * Filter the ES filters that will be applied to the ES query.
		 *
		 * Although each index of the `$filters` array contains the related WP Query argument,
		 * it will be removed before applied to the ES query.
		 *
		 * @hook ep_post_filters
		 * @param  {array}    Current filters
		 * @param  {array}    WP Query args
		 * @param  {WP_Query} WP Query object
		 * @return {array} New filters
		 */
		$filters = apply_filters( 'ep_post_filters', $filters, $args, $query );

		$filters = array_values( array_filter( $filters ) );

		if ( ! empty( $filters ) ) {
			$filters = [
				'bool' => [
					'must' => $filters,
				],
			];
		}

		return $filters;
	}

	/**
	 * Sanitize WP_Query arguments to be used to create the ES query.
	 *
	 * Elasticsearch will error if a terms query contains empty items like an empty string.
	 *
	 * @since 4.4.0
	 * @param array $args WP_Query arguments
	 * @return array
	 */
	protected function sanitize_wp_query_args( $args ) {
		$keys_to_sanitize = [
			'author__in',
			'author__not_in',
			'category__and',
			'category__in',
			'category__not_in',
			'tag__and',
			'tag__in',
			'tag__not_in',
			'tag_slug__and',
			'tag_slug__in',
			'post_parent__in',
			'post_parent__not_in',
			'post__in',
			'post__not_in',
			'post_name__in',
		];
		foreach ( $keys_to_sanitize as $key ) {
			if ( ! isset( $args[ $key ] ) ) {
				continue;
			}
			$args[ $key ] = array_filter( (array) $args[ $key ] );
		}

		return $args;
	}

	/**
	 * Parse the `from` clause of the ES Query.
	 *
	 * @since 4.4.0
	 * @param array $args WP_Query arguments
	 * @return int
	 */
	protected function parse_from( $args ) {
		$from = 0;

		if ( isset( $args['offset'] ) ) {
			$from = (int) $args['offset'];
		}

		if ( isset( $args['paged'] ) && $args['paged'] > 1 ) {
			$from = $args['posts_per_page'] * ( $args['paged'] - 1 );
		}

		/**
		 * Fix negative offset. This happens, for example, on hierarchical post types.
		 *
		 * Ref: https://github.com/10up/ElasticPress/issues/2480
		 */
		if ( $from < 0 ) {
			$from = 0;
		}

		return $from;
	}

	/**
	 * Parse the `size` clause of the ES Query.
	 *
	 * @since 4.4.0
	 * @param array $args WP_Query arguments
	 * @return int
	 */
	protected function parse_size( $args ) {
		if ( empty( $args['posts_per_page'] ) ) {
			return (int) get_option( 'posts_per_page' );
		}

		$posts_per_page = (int) $args['posts_per_page'];

		// ES have a maximum size allowed so we have to convert "-1" to a maximum size.
		if ( -1 === $posts_per_page ) {
			/**
			 * Filter max result size if set to -1
			 *
			 * The request will return a HTTP 500 Internal Error if the size of the
			 * request is larger than the [index.max_result_window] parameter in ES.
			 * See the scroll api for a more efficient way to request large data sets.
			 *
			 * @hook ep_max_results_window
			 * @param  {int} Max result window
			 * @return {int} New window
			 */
			$posts_per_page = apply_filters( 'ep_max_results_window', 10000 );
		}

		return $posts_per_page;
	}

	/**
	 * Parse the order of results in the ES query. It could simply be a `sort` clause or a function score query if using RAND.
	 *
	 * @since 4.4.0
	 * @param array $formatted_args Formatted Elasticsearch query
	 * @param array $args           WP_Query arguments
	 * @return array
	 */
	protected function maybe_orderby( $formatted_args, $args ) {
		/**
		 * Order and Orderby arguments
		 *
		 * Used for how Elasticsearch will sort results
		 *
		 * @since 1.1
		 */

		// Set sort order, default is 'desc'.
		if ( ! empty( $args['order'] ) ) {
			$order = $this->parse_order( $args['order'] );
		} else {
			$order = 'desc';
		}

		// Default sort for non-searches to date.
		if ( empty( $args['orderby'] ) && ( ! isset( $args['s'] ) || '' === $args['s'] ) ) {
			/**
			 * Filter default post query order by
			 *
			 * @hook ep_set_default_sort
			 * @param  {string} $sort Default sort
			 * @param  {string $order Order direction
			 * @return  {string} New default
			 */
			$args['orderby'] = apply_filters( 'ep_set_default_sort', 'date', $order );
		}

		// Set sort type.
		if ( ! empty( $args['orderby'] ) ) {
			$formatted_args['sort'] = $this->parse_orderby( $args['orderby'], $order, $args );
		} else {
			// Default sort is to use the score (based on relevance).
			$default_sort = array(
				array(
					'_score' => array(
						'order' => $order,
					),
				),
			);

			/**
			 * Filter the ES query order (`sort` clause)
			 *
			 * This filter is used in searches if `orderby` is not set in the WP_Query args.
			 * The default value is:
			 *
			 *    $default_sort = array(
			 *        array(
			 *            '_score' => array(
			 *                'order' => $order,
			 *            ),
			 *        ),
			 *    );
			 *
			 * @hook ep_set_sort
			 * @since 3.6.3
			 * @param  {array}  $sort  Default sort.
			 * @param  {string} $order Order direction
			 * @return {array}  New default
			 */
			$default_sort = apply_filters( 'ep_set_sort', $default_sort, $order );

			$formatted_args['sort'] = $default_sort;
		}

		/**
		 * Order by 'rand' support
		 *
		 * Ref: https://github.com/elastic/elasticsearch/issues/1170
		 */
		if ( ! empty( $args['orderby'] ) ) {
			$orderbys = $this->get_orderby_array( $args['orderby'] );
			if ( in_array( 'rand', $orderbys, true ) ) {
				$formatted_args_query                                      = $formatted_args['query'];
				$formatted_args['query']                                   = [];
				$formatted_args['query']['function_score']['query']        = $formatted_args_query;
				$formatted_args['query']['function_score']['random_score'] = (object) [];
			}
		}

		return $formatted_args;
	}

	/**
	 * Parse all taxonomy queries.
	 *
	 * Although the name may be misleading, it handles the `tax_query` argument. There is a `parse_tax_query` that handles each "small" query.
	 *
	 * @since 4.4.0
	 * @param array    $args  WP_Query arguments
	 * @param WP_Query $query WP_Query object
	 * @return array
	 */
	protected function parse_tax_queries( $args, $query ) {
		/**
		 * Tax Query support
		 *
		 * Support for the tax_query argument of WP_Query. Currently only provides support for the 'AND' relation
		 * between taxonomies. Field only supports slug, term_id, and name defaulting to term_id.
		 *
		 * @use field = slug
		 *      terms array
		 * @since 0.9.1
		 */
		if ( ! empty( $query->tax_query ) && ! empty( $query->tax_query->queries ) ) {
			$args['tax_query'] = $query->tax_query->queries;
		}

		if ( empty( $args['tax_query'] ) ) {
			return [];
		}

		// Main tax_query array for ES.
		$es_tax_query = [];

		$tax_queries = $this->parse_tax_query( $args['tax_query'] );

		if ( ! empty( $tax_queries['tax_filter'] ) ) {
			$relation = 'must';

			if ( ! empty( $args['tax_query']['relation'] ) && 'or' === strtolower( $args['tax_query']['relation'] ) ) {
				$relation = 'should';
			}

			$es_tax_query[ $relation ] = $tax_queries['tax_filter'];
		}

		if ( ! empty( $tax_queries['tax_must_not_filter'] ) ) {
			$es_tax_query['must_not'] = $tax_queries['tax_must_not_filter'];
		}

		if ( ! empty( $es_tax_query ) ) {
			return [ 'bool' => $es_tax_query ];
		}

		return [];
	}

	/**
	 * Parse the `post_parent` WP Query arg and transform it into an ES query clause.
	 *
	 * @since 4.4.0
	 * @param array $args WP_Query arguments
	 * @return array
	 */
	protected function parse_post_parent( $args ) {
		$has_post_parent = isset( $args['post_parent'] ) && ( in_array( $args['post_parent'], [ 0, '0' ], true ) || ! empty( $args['post_parent'] ) );
		if ( ! $has_post_parent || 'any' === strtolower( $args['post_parent'] ) ) {
			return [];
		}

		return [
			'bool' => [
				'must' => [
					'term' => [
						'post_parent' => (int) $args['post_parent'],
					],
				],
			],
		];
	}

	/**
	 * Parse the `post_parent__in` WP Query arg and transform it into an ES query clause.
	 *
	 * @since 4.5.0
	 * @param array $args WP_Query arguments
	 * @return array
	 */
	protected function parse_post_parent__in( $args ) {
		if ( empty( $args['post_parent__in'] ) ) {
			return [];
		}

		return [
			'bool' => [
				'must' => [
					'terms' => [
						'post_parent' => array_values( (array) $args['post_parent__in'] ),
					],
				],
			],
		];
	}

	/**
	 * Parse the `post_parent__not_in` WP Query arg and transform it into an ES query clause.
	 *
	 * @since 4.5.0
	 * @param array $args WP_Query arguments
	 * @return array
	 */
	protected function parse_post_parent__not_in( $args ) {
		if ( empty( $args['post_parent__not_in'] ) ) {
			return [];
		}

		return [
			'bool' => [
				'must_not' => [
					'terms' => [
						'post_parent' => array_values( (array) $args['post_parent__not_in'] ),
					],
				],
			],
		];
	}

	/**
	 * Parse the `post__in` WP Query arg and transform it into an ES query clause.
	 *
	 * @since 4.4.0
	 * @param array $args WP_Query arguments
	 * @return array
	 */
	protected function parse_post__in( $args ) {
		if ( empty( $args['post__in'] ) ) {
			return [];
		}

		return [
			'bool' => [
				'must' => [
					'terms' => [
						'post_id' => array_values( (array) $args['post__in'] ),
					],
				],
			],
		];
	}

	/**
	 * Parse the `post_name__in` WP Query arg and transform it into an ES query clause.
	 *
	 * @since 4.4.0
	 * @param array $args WP_Query arguments
	 * @return array
	 */
	protected function parse_post_name__in( $args ) {
		if ( empty( $args['post_name__in'] ) ) {
			return [];
		}

		return [
			'bool' => [
				'must' => [
					'terms' => [
						'post_name.raw' => array_values( (array) $args['post_name__in'] ),
					],
				],
			],
		];
	}

	/**
	 * Parse the `post__not_in` WP Query arg and transform it into an ES query clause.
	 *
	 * @since 4.4.0
	 * @param array $args WP_Query arguments
	 * @return array
	 */
	protected function parse_post__not_in( $args ) {
		if ( empty( $args['post__not_in'] ) ) {
			return [];
		}

		return [
			'bool' => [
				'must_not' => [
					'terms' => [
						'post_id' => array_values( (array) $args['post__not_in'] ),
					],
				],
			],
		];
	}

	/**
	 * Parse the `category__not_in` WP Query arg and transform it into an ES query clause.
	 *
	 * @since 4.4.0
	 * @param array $args WP_Query arguments
	 * @return array
	 */
	protected function parse_category__not_in( $args ) {
		if ( empty( $args['category__not_in'] ) ) {
			return [];
		}

		return [
			'bool' => [
				'must_not' => [
					'terms' => [
						'terms.category.term_id' => array_values( (array) $args['category__not_in'] ),
					],
				],
			],
		];
	}

	/**
	 * Parse the `tag__not_in` WP Query arg and transform it into an ES query clause.
	 *
	 * @since 4.4.0
	 * @param array $args WP_Query arguments
	 * @return array
	 */
	protected function parse_tag__not_in( $args ) {
		if ( empty( $args['tag__not_in'] ) ) {
			return [];
		}

		return [
			'bool' => [
				'must_not' => [
					'terms' => [
						'terms.post_tag.term_id' => array_values( (array) $args['tag__not_in'] ),
					],
				],
			],
		];
	}

	/**
	 * Parse the various author-related WP Query args and transform them into ES query clauses.
	 *
	 * @since 4.4.0
	 * @param array $args WP_Query arguments
	 * @return array
	 */
	protected function parse_author( $args ) {
		if ( ! empty( $args['author'] ) ) {
			return [
				'term' => [
					'post_author.id' => $args['author'],
				],
			];
		}

		if ( ! empty( $args['author_name'] ) ) {
			// Since this was set to use the display name initially, there might be some code that used this feature.
			// Let's ensure that any query vars coming in using author_name are in fact slugs.
			// This was changed back in ticket #1622 to use the display name, so we removed the sanitize_user() call.
			return [
				'term' => [
					'post_author.display_name' => $args['author_name'],
				],
			];
		}

		if ( ! empty( $args['author__in'] ) ) {
			return [
				'bool' => [
					'must' => [
						'terms' => [
							'post_author.id' => array_values( (array) $args['author__in'] ),
						],
					],
				],
			];
		}

		if ( ! empty( $args['author__not_in'] ) ) {
			return [
				'bool' => [
					'must_not' => [
						'terms' => [
							'post_author.id' => array_values( (array) $args['author__not_in'] ),
						],
					],
				],
			];
		}

		return [];
	}

	/**
	 * Parse the `post_mime_type` WP Query arg and transform it into an ES query clause.
	 *
	 * If we have array, it will be fool text search filter.
	 * If we have string(like filter images in media screen), we will have mime type "image" so need to check it as
	 * regexp filter.
	 *
	 * @since 4.4.0
	 * @param array $args WP_Query arguments
	 * @return array
	 */
	protected function parse_post_mime_type( $args ) {
		if ( empty( $args['post_mime_type'] ) ) {
			return [];
		}

		if ( is_array( $args['post_mime_type'] ) ) {

			$args_post_mime_type = [];

			foreach ( $args['post_mime_type'] as $mime_type ) {
				/**
				 * check if matches the MIME type pattern: type/subtype and
				 * leave an empty string as posts, pages and CPTs don't have a MIME type
				 */
				if ( preg_match( '/^[-._a-z0-9]+\/[-._a-z0-9]+$/i', $mime_type ) || empty( $mime_type ) ) {
					$args_post_mime_type[] = $mime_type;
				} else {
					$filtered_mime_type_by_type = wp_match_mime_types( $mime_type, wp_get_mime_types() );

					$args_post_mime_type = array_merge( $args_post_mime_type, $filtered_mime_type_by_type[ $mime_type ] );
				}
			}

			return [
				'terms' => [
					'post_mime_type' => $args_post_mime_type,
				],
			];
		}

		if ( is_string( $args['post_mime_type'] ) ) {
			return [
				'regexp' => array(
					'post_mime_type' => $args['post_mime_type'] . '.*',
				),
			];
		}

		return [];
	}

	/**
	 * Parse the various date-related WP Query args and transform them into ES query clauses.
	 *
	 * @since 4.4.0
	 * @param array $args WP_Query arguments
	 * @return array
	 */
	protected function parse_date( $args ) {
		$date_filter = DateQuery::simple_es_date_filter( $args );

		if ( ! empty( $date_filter ) ) {
			return $date_filter;
		}

		if ( ! empty( $args['date_query'] ) ) {

			$date_query = new DateQuery( $args['date_query'] );

			$date_filter = $date_query->get_es_filter();

			if ( array_key_exists( 'and', $date_filter ) ) {
				return $date_filter['and'];
			}
		}
	}

	/**
	 * Parse all meta queries.
	 *
	 * Although the name may be misleading, it handles the `meta_query` argument. There is a `build_meta_query` that handles each "small" query.
	 *
	 * @since 4.4.0
	 * @param array $args WP_Query arguments
	 * @return array
	 */
	protected function parse_meta_queries( $args ) {
		/**
		 * 'meta_query' arg support.
		 *
		 * Relation supports 'AND' and 'OR'. 'AND' is the default. For each individual query, the
		 * following 'compare' values are supported: =, !=, EXISTS, NOT EXISTS. '=' is the default.
		 *
		 * @since 1.3
		 */
		$meta_queries = ( ! empty( $args['meta_query'] ) ) ? $args['meta_query'] : [];
		$meta_queries = ( new \WP_Meta_Query() )->sanitize_query( $meta_queries );

		/**
		 * Todo: Support meta_type
		 */

		/**
		 * Support `meta_key`, `meta_value`, `meta_value_num`, and `meta_compare` query args
		 */
		if ( ! empty( $args['meta_key'] ) ) {
			$meta_query_array = [
				'key' => $args['meta_key'],
			];

			if ( isset( $args['meta_value'] ) && '' !== $args['meta_value'] ) {
				$meta_query_array['value'] = $args['meta_value'];
			} elseif ( isset( $args['meta_value_num'] ) && '' !== $args['meta_value_num'] ) {
				$meta_query_array['value'] = $args['meta_value_num'];
			}

			if ( isset( $args['meta_compare'] ) ) {
				$meta_query_array['compare'] = $args['meta_compare'];
			}

			if ( ! empty( $meta_queries ) ) {
				$meta_queries = [
					'relation' => 'AND',
					$meta_query_array,
					$meta_queries,
				];
			} else {
				$meta_queries = [ $meta_query_array ];
			}
		}

		if ( ! empty( $meta_queries ) ) {
			// get meta query filter
			$meta_filter = $this->build_meta_query( $meta_queries );

			if ( ! empty( $meta_filter ) ) {
				return $meta_filter;
			}
		}

		return [];
	}

	/**
	 * Parse the `post_type` WP Query arg and transform it into an ES query clause.
	 *
	 * @since 4.4.0
	 * @param array $args WP_Query arguments
	 * @return array
	 */
	protected function parse_post_type( $args ) {
		/**
		 * If not set default to post. If search and not set, default to "any".
		 */
		if ( ! empty( $args['post_type'] ) ) {
			// should NEVER be "any" but just in case
			if ( 'any' !== $args['post_type'] ) {
				$post_types     = (array) $args['post_type'];
				$terms_map_name = 'terms';

				return [
					$terms_map_name => [
						'post_type.raw' => array_values( $post_types ),
					],
				];
			}
		} elseif ( empty( $args['s'] ) ) {
			return [
				'term' => [
					'post_type.raw' => 'post',
				],
			];
		}

		return [];
	}

	/**
	 * Parse the `post_status` WP Query arg and transform it into an ES query clause.
	 *
	 * @since 4.4.0
	 * @param array $args WP_Query arguments
	 * @return array
	 */
	protected function parse_post_status( $args ) {
		/**
		 * Like WP_Query in search context, if no post_status is specified we default to "any". To
		 * be safe you should ALWAYS specify the post_status parameter UNLIKE with WP_Query.
		 *
		 * @since 2.1
		 */
		if ( ! empty( $args['post_status'] ) ) {
			// should NEVER be "any" but just in case
			if ( 'any' !== $args['post_status'] ) {
				$post_status    = (array) ( is_string( $args['post_status'] ) ? explode( ',', $args['post_status'] ) : $args['post_status'] );
				$post_status    = array_map( 'trim', $post_status );
				$terms_map_name = 'terms';
				if ( count( $post_status ) < 2 ) {
					$terms_map_name = 'term';
					$post_status    = $post_status[0];
				}

				return [
					$terms_map_name => [
						'post_status' => is_array( $post_status ) ? array_values( $post_status ) : $post_status,
					],
				];
			}
		} else {
			$statuses = get_post_stati( array( 'public' => true ) );

			if ( is_admin() ) {
				/**
				 * In the admin we will add protected and private post statuses to the default query
				 * per WP default behavior.
				 */
				$statuses = array_merge(
					$statuses,
					get_post_stati(
						array(
							'protected'              => true,
							'show_in_admin_all_list' => true,
						)
					)
				);

				if ( is_user_logged_in() ) {
					$statuses = array_merge( $statuses, get_post_stati( array( 'private' => true ) ) );
				}
			}

			$statuses = array_values( $statuses );

			$post_status_filter_type = 'terms';

			return [
				$post_status_filter_type => [
					'post_status' => $statuses,
				],
			];
		}

		return [];
	}

	/**
	 * If in a search context set search fields, otherwise query everything.
	 *
	 * @since 4.4.0
	 * @param array $formatted_args Formatted Elasticsearch query
	 * @param array $args           WP_Query arguments
	 * @return array
	 */
	protected function maybe_set_search_fields( $formatted_args, $args ) {
		/**
		 * Allow for search field specification
		 *
		 * @since 1.0
		 */
		if ( ! empty( $args['search_fields'] ) ) {
			$search_field_args = $args['search_fields'];
			$search_fields     = [];

			if ( ! empty( $search_field_args['taxonomies'] ) ) {
				$taxes = (array) $search_field_args['taxonomies'];

				foreach ( $taxes as $tax ) {
					$search_fields[] = 'terms.' . $tax . '.name';
				}

				unset( $search_field_args['taxonomies'] );
			}

			if ( ! empty( $search_field_args['meta'] ) ) {
				$metas = (array) $search_field_args['meta'];

				foreach ( $metas as $meta ) {
					$search_fields[] = 'meta.' . $meta . '.value';
				}

				unset( $search_field_args['meta'] );
			}

			if ( in_array( 'author_name', $search_field_args, true ) ) {
				$search_fields[] = 'post_author.login';

				$author_name_index = array_search( 'author_name', $search_field_args, true );
				unset( $search_field_args[ $author_name_index ] );
			}

			$search_fields = array_merge( $search_field_args, $search_fields );
		} else {
			$search_fields = array(
				'post_title',
				'post_excerpt',
				'post_content',
			);
		}

		/**
		 * Filter default post search fields
		 *
		 * If you are using the weighting engine, this filter should not be used.
		 * Instead, you should use the ep_weighting_configuration_for_search filter.
		 *
		 * @hook ep_search_fields
		 * @param  {array} $search_fields Default search fields
		 * @param  {array} $args WP Query arguments
		 * @return  {array} New defaults
		 */
		$search_fields = apply_filters( 'ep_search_fields', $search_fields, $args );

		$search_text = ( ! empty( $args['s'] ) ) ? $args['s'] : '';

		/**
		 * We are using ep_integrate instead of ep_match_all. ep_match_all will be
		 * supported for legacy code but may be deprecated and removed eventually.
		 *
		 * @since 1.3
		 */

		if ( ! empty( $search_text ) ) {
			add_filter( 'ep_post_formatted_args_query', [ $this, 'adjust_query_fuzziness' ], 100, 4 );

			$search_algorithm        = $this->get_search_algorithm( $search_text, $search_fields, $args );
			$formatted_args['query'] = $search_algorithm->get_query( 'post', $search_text, $search_fields, $args );
		} elseif ( ! empty( $args['ep_match_all'] ) || ! empty( $args['ep_integrate'] ) ) {
			$formatted_args['query']['match_all'] = array(
				'boost' => 1,
			);
		}

		return $formatted_args;
	}

	/**
	 * If needed bring sticky posts and order them.
	 *
	 * @since 4.4.0
	 * @param array $formatted_args Formatted Elasticsearch query
	 * @param array $args           WP_Query arguments
	 * @return array
	 */
	protected function maybe_add_sticky_posts( $formatted_args, $args ) {
		/**
		 * Sticky posts support
		 */

		// Check first if there's sticky posts and show them only in the front page
		$sticky_posts = get_option( 'sticky_posts' );
		$sticky_posts = ( is_array( $sticky_posts ) && empty( $sticky_posts ) ) ? false : $sticky_posts;

		/**
		 * Filter whether to enable sticky posts for this request
		 *
		 * @hook ep_enable_sticky_posts
		 *
		 * @param {bool}  $allow          Allow sticky posts for this request
		 * @param {array} $args           Query variables
		 * @param {array} $formatted_args EP formatted args
		 *
		 * @return  {bool} $allow
		 */
		$enable_sticky_posts = apply_filters( 'ep_enable_sticky_posts', is_home(), $args, $formatted_args );

		if ( false !== $sticky_posts
			&& $enable_sticky_posts
			&& empty( $args['s'] )
			&& in_array( $args['ignore_sticky_posts'], array( 'false', 0, false ), true ) ) {
			$new_sort = [
				[
					'_score' => [
						'order' => 'desc',
					],
				],
			];

			$formatted_args['sort'] = array_merge( $new_sort, $formatted_args['sort'] );

			$formatted_args_query                                   = $formatted_args['query'];
			$formatted_args['query']                                = array();
			$formatted_args['query']['function_score']['query']     = $formatted_args_query;
			$formatted_args['query']['function_score']['functions'] = array(
				// add extra weight to sticky posts to show them on top
					(object) array(
						'filter' => array(
							'terms' => array( '_id' => $sticky_posts ),
						),
						'weight' => 20,
					),
			);
		}

		return $formatted_args;
	}

	/**
	 * If needed set the `fields` ES query clause.
	 *
	 * @since 4.4.0
	 * @param array $formatted_args Formatted Elasticsearch query
	 * @param array $args           WP_Query arguments
	 * @return array
	 */
	protected function maybe_set_fields( $formatted_args, $args ) {
		/**
		 * Support fields.
		 */
		if ( isset( $args['fields'] ) ) {
			switch ( $args['fields'] ) {
				case 'ids':
					$formatted_args['_source'] = array(
						'includes' => array(
							'post_id',
						),
					);
					break;

				case 'id=>parent':
					$formatted_args['_source'] = array(
						'includes' => array(
							'post_id',
							'post_parent',
						),
					);
					break;
			}
		}

		return $formatted_args;
	}

	/**
	 * If needed set the `aggs` ES query clause.
	 *
	 * @since 4.4.0
	 * @param array $formatted_args Formatted Elasticsearch query.
	 * @param array $args           WP_Query arguments
	 * @param array $filters        Filters to be applied to the ES query
	 * @return array
	 */
	protected function maybe_set_aggs( $formatted_args, $args, $filters ) {
		/**
		 * Aggregations
		 */
		if ( ! empty( $args['aggs'] ) && is_array( $args['aggs'] ) ) {
			// Check if the array indexes are all numeric.
			$agg_keys          = array_keys( $args['aggs'] );
			$agg_num_keys      = array_filter( $agg_keys, 'is_int' );
			$has_only_num_keys = count( $agg_num_keys ) === count( $args['aggs'] );

			if ( $has_only_num_keys ) {
				foreach ( $args['aggs'] as $agg ) {
					$formatted_args = $this->apply_aggregations( $formatted_args, $agg, ! empty( $filters ), $filters );
				}
			} else {
				// Single aggregation.
				$formatted_args = $this->apply_aggregations( $formatted_args, $args['aggs'], ! empty( $filters ), $filters );
			}
		}

		return $formatted_args;
	}

	/**
	 * Parse tax query field value.
	 *
	 * @since 4.4.0
	 * @param string $field Field name
	 * @return string
	 */
	protected function parse_tax_query_field( string $field ) : string {

		$from_to = [
			'name'             => 'name.raw',
			'slug'             => 'slug',
			'term_taxonomy_id' => 'term_taxonomy_id',
		];

		return $from_to[ $field ] ?? 'term_id';
	}

	/**
	 * Return all distinct meta fields in the database.
	 *
	 * @since 4.4.0
	 * @param bool $force_refresh Whether to use or not a cached value. Default false, use cached.
	 * @return array
	 */
	public function get_distinct_meta_field_keys_db( bool $force_refresh = false ) : array {
		global $wpdb;

		/**
		 * Short-circuits the process of getting distinct meta keys from the database.
		 *
		 * Returning a non-null value will effectively short-circuit the function.
		 *
		 * @since 4.4.0
		 * @hook ep_post_pre_meta_keys_db
		 * @param {null} $meta_keys Distinct meta keys array
		 * @return {null|array} Distinct meta keys array or `null` to keep default behavior
		 */
		$pre_meta_keys = apply_filters( 'ep_post_pre_meta_keys_db', null );
		if ( null !== $pre_meta_keys ) {
			return $pre_meta_keys;
		}

		$cache_key = 'ep_meta_field_keys';

		if ( ! $force_refresh ) {
			$cached = get_transient( $cache_key );
			if ( false !== $cached ) {
				$cached = (array) json_decode( (string) $cached );
				/* this filter is documented below */
				return (array) apply_filters( 'ep_post_meta_keys_db', $cached );
			}
		}

		/**
		 * To avoid running a too expensive SQL query, we run a query getting all public keys
		 * and only the private keys allowed by the `ep_prepare_meta_allowed_protected_keys` filter.
		 * This query does not order by on purpose, as that also brings a performance penalty.
		 */
		$allowed_protected_keys     = apply_filters( 'ep_prepare_meta_allowed_protected_keys', [], new \WP_Post( (object) [] ) );
		$allowed_protected_keys_sql = '';
		if ( ! empty( $allowed_protected_keys ) ) {
			$placeholders               = implode( ',', array_fill( 0, count( $allowed_protected_keys ), '%s' ) );
			$allowed_protected_keys_sql = " OR meta_key IN ( {$placeholders} ) ";
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$meta_keys = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT meta_key
					FROM {$wpdb->postmeta}
					WHERE meta_key NOT LIKE %s {$allowed_protected_keys_sql}
					LIMIT 800",
				'\_%',
				...$allowed_protected_keys
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		sort( $meta_keys );

		// Make sure the size of the transient will not be bigger than 1MB
		do {
			$transient_size = strlen( wp_json_encode( $meta_keys ) );
			if ( $transient_size >= MB_IN_BYTES ) {
				array_pop( $meta_keys );
			} else {
				break;
			}
		} while ( true );
		set_transient( $cache_key, wp_json_encode( $meta_keys ), DAY_IN_SECONDS );

		/**
		 * Filter the distinct meta keys fetched from the database.
		 *
		 * @since 4.4.0
		 * @hook ep_post_meta_keys_db
		 * @param {array} $meta_keys Distinct meta keys array
		 * @return {array} New distinct meta keys array
		 */
		return (array) apply_filters( 'ep_post_meta_keys_db', $meta_keys );
	}

	/**
	 * Return all distinct meta fields in the database per post type.
	 *
	 * @since 4.4.0
	 * @param string $post_type     Post type slug
	 * @param bool   $force_refresh Whether to use or not a cached value. Default false, use cached.
	 * @return array
	 */
	public function get_distinct_meta_field_keys_db_per_post_type( string $post_type, bool $force_refresh = false ) : array {
		$allowed_screen = 'status-report' === \ElasticPress\Screen::factory()->get_current_screen();

		/**
		 * Filter if the current screen is allowed or not to use the function.
		 *
		 * This method can be too resource intensive, use it with caution.
		 *
		 * @since 4.4.0
		 * @hook ep_post_meta_keys_db_per_post_type_allowed_screen
		 * @param {bool} $allowed_screen Whether this is an allowed screen or not.
		 * @return {bool} New value of $allowed_screen
		 */
		if ( ! apply_filters( 'ep_post_meta_keys_db_per_post_type_allowed_screen', $allowed_screen ) ) {
			_doing_it_wrong(
				__METHOD__,
				esc_html__( 'This method should not be called outside specific pages. Use the `ep_post_meta_keys_db_per_post_type_allowed_screen` filter if you need to use it in your custom screen.' ),
				'ElasticPress 4.4.0'
			);
			return [];
		}

		/**
		 * Short-circuits the process of getting distinct meta keys from the database per post type.
		 *
		 * Returning a non-null value will effectively short-circuit the function.
		 *
		 * @since 4.4.0
		 * @hook ep_post_pre_meta_keys_db_per_post_type
		 * @param {null}   $meta_keys Distinct meta keys array
		 * @param {string} $post_type Post type slug
		 * @return {null|array} Distinct meta keys array or `null` to keep default behavior
		 */
		$pre_meta_keys = apply_filters( 'ep_post_pre_meta_keys_db_per_post_type', null, $post_type );
		if ( null !== $pre_meta_keys ) {
			return $pre_meta_keys;
		}

		$cache_key = 'ep_meta_field_keys_' . $post_type;

		if ( ! $force_refresh ) {
			$cached = get_transient( $cache_key );
			if ( false !== $cached ) {
				$cached = (array) json_decode( (string) $cached );
				/* this filter is documented below */
				return (array) apply_filters( 'ep_post_meta_keys_db_per_post_type', $cached, $post_type );
			}
		}

		$meta_keys        = [];
		$post_ids_batches = $this->get_lazy_post_type_ids( $post_type );
		foreach ( $post_ids_batches as $post_ids ) {
			$new_meta_keys = $this->get_meta_keys_from_post_ids( $post_ids );

			$meta_keys = array_unique( array_merge( $meta_keys, $new_meta_keys ) );
		}

		// Make sure the size of the transient will not be bigger than 1MB
		do {
			$transient_size = strlen( wp_json_encode( $meta_keys ) );
			if ( $transient_size >= MB_IN_BYTES ) {
				array_pop( $meta_keys );
			} else {
				break;
			}
		} while ( true );
		set_transient( $cache_key, wp_json_encode( $meta_keys ), DAY_IN_SECONDS );

		/**
		 * Filter the distinct meta keys fetched from the database per post type.
		 *
		 * @since 4.4.0
		 * @hook ep_post_meta_keys_db_per_post_type
		 * @param {array}  $meta_keys Distinct meta keys array
		 * @param {string} $post_type Post type slug
		 * @return {array} New distinct meta keys array
		 */
		return (array) apply_filters( 'ep_post_meta_keys_db_per_post_type', $meta_keys, $post_type );
	}

	/**
	 * Return all distinct meta fields in the database per post type.
	 *
	 * @since 4.4.0
	 * @param string $post_type Post type slug
	 * @param bool   $force_refresh Whether to use or not a cached value. Default false, use cached.
	 * @return array
	 */
	public function get_indexable_meta_keys_per_post_type( string $post_type, bool $force_refresh = false ) : array {
		$mock_post = new \WP_Post( (object) [ 'post_type' => $post_type ] );
		$meta_keys = $this->get_distinct_meta_field_keys_db_per_post_type( $post_type, $force_refresh );

		$fake_meta_values = array_combine( $meta_keys, array_fill( 0, count( $meta_keys ), 'test-value' ) );
		$filtered_meta    = apply_filters( 'ep_prepare_meta_data', $fake_meta_values, $mock_post );

		return array_filter(
			array_keys( $filtered_meta ),
			function ( $meta_key ) use ( $mock_post ) {
				return $this->is_meta_allowed( $meta_key, $mock_post );
			}
		);
	}

	/**
	 * Return the meta keys that will (possibly) be indexed.
	 *
	 * This function gets all the meta keys in the database, creates a fake post without a type and with all the meta fields,
	 * runs the `ep_prepare_meta_data` filter against it and checks if meta keys are allowed or not.
	 * Although it provides a good indicator, it is not 100% correct as developers could create code using the
	 * `ep_prepare_meta_data` filter that would depend on "real" data.
	 *
	 * @since 4.4.0
	 * @param bool $force_refresh Whether to use or not a cached value. Default false, use cached.
	 * @return array
	 */
	public function get_predicted_indexable_meta_keys( bool $force_refresh = false ) : array {
		$empty_post = new \WP_Post( (object) [] );
		$meta_keys  = $this->get_distinct_meta_field_keys_db( $force_refresh );

		$fake_meta_values = array_combine( $meta_keys, array_fill( 0, count( $meta_keys ), 'test-value' ) );
		$filtered_meta    = apply_filters( 'ep_prepare_meta_data', $fake_meta_values, $empty_post );

		$all_keys = array_filter(
			array_keys( $filtered_meta ),
			function( $meta_key ) use ( $empty_post ) {
				return $this->is_meta_allowed( $meta_key, $empty_post );
			}
		);

		sort( $all_keys );

		return $all_keys;
	}

	/**
	 * Given a post type, *yields* their Post IDs.
	 *
	 * If post IDs are found, this function will return a PHP Generator. To avoid timeout, it will yield 8 groups or 11,000 IDs.
	 *
	 * @since 4.4.0
	 * @see https://www.php.net/manual/en/language.generators.overview.php
	 * @param string $post_type The post type slug
	 * @return iterator
	 */
	protected function get_lazy_post_type_ids( string $post_type ) {
		global $wpdb;

		$total = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT count(*) FROM {$wpdb->posts} WHERE post_type = %s",
				$post_type
			)
		);

		if ( ! $total ) {
			return [];
		}

		/**
		 * Filter the number of IDs to be fetched per page to discover distinct meta fields per post type.
		 *
		 * @hook ep_post_meta_by_type_ids_per_page
		 * @since 4.4.0
		 * @param {int}    $per_page  Number of IDs
		 * @param {string} $post_type The post type slug
		 * @return  {string} New number of IDs
		 */
		$per_page = apply_filters( 'ep_post_meta_by_type_ids_per_page', 11000, $post_type );

		$pages = min( ceil( $total / $per_page ), 8 );

		/**
		 * Filter the number of times EP will fetch IDs from the database
		 *
		 * @hook ep_post_meta_by_type_number_of_pages
		 * @since 4.4.0
		 * @param {int}    $pages     Number of "pages" (not WP post type)
		 * @param {int}    $per_page  Number of IDs per page
		 * @param {string} $post_type The post type slug
		 * @return  {string} New number of pages
		 */
		$pages = apply_filters( 'ep_post_meta_by_type_number_of_pages', $pages, $per_page, $post_type );

		for ( $page = 0; $page < $pages; $page++ ) {
			$start = $per_page * $page;
			$ids   = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s LIMIT %d, %d",
					$post_type,
					$start,
					$per_page
				)
			);
			yield $ids;
		}
	}

	/**
	 * Given a set of post IDs, return distinct meta keys associated with them.
	 *
	 * @since 4.4.0
	 * @param array $post_ids Set of post IDs
	 * @return array
	 */
	protected function get_meta_keys_from_post_ids( array $post_ids ) : array {
		global $wpdb;

		if ( empty( $post_ids ) ) {
			return [];
		}

		$placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );
		$meta_keys    = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				"SELECT DISTINCT meta_key FROM {$wpdb->postmeta} WHERE post_id IN ( {$placeholders} )",
				$post_ids
			)
		);

		return $meta_keys;
	}

	/**
	 * Add a `term_suggest` field to the mapping.
	 *
	 * This method assumes the `edge_ngram_analyzer` analyzer was already added to the mapping.
	 *
	 * @since 4.5.0
	 * @param array $mapping The mapping array
	 * @return array
	 */
	public function add_term_suggest_field( array $mapping ) : array {
		if ( version_compare( (string) Elasticsearch::factory()->get_elasticsearch_version(), '7.0', '<' ) ) {
			$mapping_properties = &$mapping['mappings']['post']['properties'];
		} else {
			$mapping_properties = &$mapping['mappings']['properties'];
		}

		$text_type = $mapping_properties['post_content']['type'];

		$mapping_properties['term_suggest'] = array(
			'type'            => $text_type,
			'analyzer'        => 'edge_ngram_analyzer',
			'search_analyzer' => 'standard',
		);

		return $mapping;
	}
}
