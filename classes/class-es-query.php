<?php

/**
 * Unfortunately we cannot filter into WP_Query since we need to pass around a construct that
 * contains a site ID. Therefore we pass around an array and convert it to a WP_Post object at
 * the last second. WP_Query cannot be filtered or hooked into to do this. The goal of this class
 * is to mimic WP_Query behavior but simply hit the ES index before the posts table.
 */
class ES_Query {

	public $posts = array();

	public $post;

	public $cross_site = false;

	public $post_count = 0;

	public $found_posts = 0;

	public $in_the_loop = false;

	public $current_post = -1;

	public $max_num_pages = 0;

	/**
	 * Setup new ES query
	 *
	 * @param array $args
	 * @since 0.1.0
	 */
	public function __construct( $args ) {

		$config = es_get_option( 0 );
		if ( ! empty( $config['cross_site_search_active'] ) ) {
			$this->cross_site = true;
		}

		$this->query( $args );
	}

	/**
	 * Query our Elasticsearch instance
	 *
	 * @param array $args
	 * @since 0.1.0
	 * @return array
	 */
	public function query( $args ) {

		$site_id = null;
		if ( $this->cross_site ) {
			$site_id = 0;
		}

		if ( ! es_is_setup( $site_id ) ) {
			return array();
		}

		$formatted_args = $this->format_args( $args );

		$posts_per_page = ( isset( $args['posts_per_page'] ) ) ? $args['posts_per_page'] : get_option( 'posts_per_page' );

		$offset = 0;

		if ( isset( $args['paged'] ) ) {
			$paged = ( $args['paged'] <= 1 ) ? 0 : $args['paged'] - 1;
			$offset = $posts_per_page * $paged;
		} else {
			if ( isset( $args['offset'] ) ) {
				$offset = $args['offset'];
			}
		}

		$es_posts = es_search( $formatted_args, $site_id );

		$this->found_posts = count( $es_posts );

		$es_posts = array_slice( $es_posts, $offset, $posts_per_page );

		$this->max_num_pages = ceil( $this->found_posts / $posts_per_page );

		$this->post_count = count( $es_posts );

		$this->posts = $es_posts;

		return $this->posts;
	}

	/**
	 * Format query args for ES. The intention of this class is to accept args that look
	 * like WP_Query's
	 *
	 * @param array $args
	 * @return array
	 */
	private function format_args( $args ) {
		$formatted_args = array(
			'filter' => array(
				'and' => array(
					0 => array(
						'term' => array(
							'post_type' => array(
								'post',
								'page',
								'attachment',
							),
						),
					),
				),
			),
			'sort' => array(
				array(
					'_score' => array(
						'order' => 'desc',
					),
				),
			),
		);

		$query = array(
			'bool' => array(
				'must' => array(
					'fuzzy_like_this' => array(
						'fields' => array(
							'post_title',
							'post_excerpt',
							'post_content',
						),
						'like_text' => '',
						'min_similarity' => 0.5,
					),
				),
			),
		);

		if ( ! $this->cross_site ) {
			$formatted_args['filter']['and'][1] = array(
				'term' => array(
					'site_id' => get_current_blog_id()
				)
			);
		}

		if ( isset( $args['s'] ) ) {
			$query['bool']['must']['fuzzy_like_this']['like_text'] = $args['s'];
			$formatted_args['query'] = $query;
		}

		if ( isset( $args['post_type'] ) ) {
			$formatted_args['filter']['and'][0]['term']['post_type'] = (array) $args['post_type'];
		}

		return $formatted_args;
	}

	/**
	 * Check if the query has posts
	 *
	 * @since 0.1.0
	 * @return bool
	 */
	public function have_posts() {
		if ( is_multisite() && $this->cross_site ) {
			restore_current_blog();
		}

		if ( $this->current_post + 1 < $this->post_count ) {
			return true;
		} elseif ( $this->current_post + 1 == $this->post_count && $this->post_count > 0 ) {
			// loop has ended
		}

		$this->in_the_loop = false;
		return false;
	}

	/**
	 * Setup the current post in the $post global
	 *
	 * @since 0.1.0
	 */
	public function the_post() {
		global $post;

		$this->in_the_loop = true;

		$es_post = $this->next_post();

		if ( is_multisite() && $this->cross_site && $es_post['site_id'] != get_current_blog_id() ) {
			switch_to_blog( $es_post['site_id'] );
		}

		$post = get_post( $es_post['post_id'] );

		setup_postdata( $post );
	}

	/**
	 * Move to the next post in the query
	 *
	 * @since 0.1.0
	 * @return mixed
	 */
	function next_post() {
		$this->current_post++;

		$this->post = $this->posts[$this->current_post];
		return $this->post;
	}
}