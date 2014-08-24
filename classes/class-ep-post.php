<?php

/**
 * Class EP_Post is how we fake WP_Post into having a site id
 */
class EP_Post {

	/**
	 * Post ID.
	 *
	 * @var int
	 */
	public $ID;

	/**
	 * ID of post author.
	 *
	 * A numeric string, for compatibility reasons.
	 *
	 * @var string
	 */
	public $post_author = 0;

	/**
	 * The post's local publication time.
	 *
	 * @var string
	 */
	public $post_date = '0000-00-00 00:00:00';

	/**
	 * The post's GMT publication time.
	 *
	 * @var string
	 */
	public $post_date_gmt = '0000-00-00 00:00:00';

	/**
	 * The post's content.
	 *
	 * @var string
	 */
	public $post_content = '';

	/**
	 * The post's title.
	 *
	 * @var string
	 */
	public $post_title = '';

	/**
	 * The post's excerpt.
	 *
	 * @var string
	 */
	public $post_excerpt = '';

	/**
	 * The post's status.
	 *
	 * @var string
	 */
	public $post_status = 'publish';

	/**
	 * Whether comments are allowed.
	 *
	 * @var string
	 */
	public $comment_status = 'open';

	/**
	 * Whether pings are allowed.
	 *
	 * @var string
	 */
	public $ping_status = 'open';

	/**
	 * The post's password in plain text.
	 *
	 * @var string
	 */
	public $post_password = '';

	/**
	 * The post's slug.
	 *
	 * @var string
	 */
	public $post_name = '';

	/**
	 * URLs queued to be pinged.
	 *
	 * @var string
	 */
	public $to_ping = '';

	/**
	 * URLs that have been pinged.
	 *
	 * @var string
	 */
	public $pinged = '';

	/**
	 * The post's local modified time.
	 *
	 * @var string
	 */
	public $post_modified = '0000-00-00 00:00:00';

	/**
	 * The post's GMT modified time.
	 *
	 * @var string
	 */
	public $post_modified_gmt = '0000-00-00 00:00:00';

	/**
	 * A utility DB field for post content.
	 *
	 *
	 * @var string
	 */
	public $post_content_filtered = '';

	/**
	 * ID of a post's parent post.
	 *
	 * @var int
	 */
	public $post_parent = 0;

	/**
	 * The unique identifier for a post, not necessarily a URL, used as the feed GUID.
	 *
	 * @var string
	 */
	public $guid = '';

	/**
	 * A field used for ordering posts.
	 *
	 * @var int
	 */
	public $menu_order = 0;

	/**
	 * The post's type, like post or page.
	 *
	 * @var string
	 */
	public $post_type = 'post';

	/**
	 * An attachment's mime type.
	 *
	 * @var string
	 */
	public $post_mime_type = '';

	/**
	 * Cached comment count.
	 *
	 * A numeric string, for compatibility reasons.
	 *
	 * @var string
	 */
	public $comment_count = 0;

	/**
	 * Stores the post object's sanitization level.
	 *
	 * Does not correspond to a DB field.
	 *
	 * @var string
	 */
	public $filter;

	/**
	 * Stores the site id of the post
	 *
	 * @var int
	 */
	public $site_id;

	/**
	 * Construct the post object
	 *
	 * @param array $post
	 */
	public function __construct( $post ) {
		$this->site_id = get_current_blog_id();

		if ( ! empty( $post['post_id'] ) ) {
			$this->ID = $post['post_id'];
		}

		if ( ! empty( $post['site_id'] ) ) {
			$this->site_id = $post['site_id'];
		}

		if ( ! empty( $post['post_name'] ) ) {
			$this->post_name = $post['post_name'];
		}

		if ( ! empty( $post['post_status'] ) ) {
			$this->post_status = $post['post_status'];
		}

		if ( ! empty( $post['post_title'] ) ) {
			$this->post_title = $post['post_title'];
		}

		if ( ! empty( $post['post_content'] ) ) {
			$this->post_content = $post['post_content'];
		}

		if ( ! empty( $post['post_date'] ) ) {
			$this->post_date = $post['post_date'];
		}

		if ( ! empty( $post['post_date_gmt'] ) ) {
			$this->post_date_gmt = $post['post_date_gmt'];
		}

		if ( ! empty( $post['post_modified'] ) ) {
			$this->post_modified = $post['post_modified'];
		}

		if ( ! empty( $post['post_modified_gmt'] ) ) {
			$this->post_modified_gmt = $post['post_modified_gmt'];
		}

	}

}