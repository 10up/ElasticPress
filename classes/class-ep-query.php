<?php

class EP_Query implements IteratorAggregate, Countable{

    /**
     * Arguments used for searching
     *
     * @access public
     * @var array
     */
    public $args = array();

    /**
     * Scope to use for searching
     *
     * @access public
     * @var string
     */
    public $scope = 'current';

    /**
     * WP_Query object if used to create instance
     *
     * @access public
     * @var mixed
     */
    public $wp_query = false;

    /**
     * Result, after parsing
     *
     * @access private
     * @var mixed
     */
    private $result = false;

    /**
     * Posts, after parsing
     *
     * @access private
     * @var array
     */
    private $posts = array();

    /**
     * Number of posts, after parsing
     *
     * @access public
     * @var integer
     */
    public $found_posts = 0;

    /**
     * Constructor.
     *
     * Sets up the ElasticPress query
     *
     * @param array $args Query arguments.
     * @param string $scope Scope to use.
     * @return WP_Query
     */
    public function __construct($args = array(), $scope = 'current')
    {
        $this->args = $args;
        $this->scope = $scope;
    }

    /**
     * Create an instance of EP_Query using an instance of WP_Query
     *
     * @param WP_Query $query Wordpress query object.
     * @return EP_Query
     */
    public static function from_wp_query(&$query)
    {
        $query_vars = $query->query_vars;
        if ( 'any' === $query_vars['post_type'] ) {
            
            if ( $query->is_search() ) {

                /*
                 * This is a search query
                 * To follow WordPress conventions,
                 * make sure we only search 'searchable' post types
                 */
                $searchable_post_types = get_post_types( array( 'exclude_from_search' => false ) );

                // If we have no searchable post types, there's no point going any further
                if ( empty( $searchable_post_types ) ) {

                    // Have to return something or it improperly calculates the found_posts
                    return "WHERE 0 = 1";
                }

                // Conform the post types array to an acceptable format for ES
                $post_types = array();
                foreach( $searchable_post_types as $type ) {
                    $post_types[] = $type;
                }

                // These are now the only post types we will search
                $query_vars['post_type'] = $post_types;
            } else {

                /*
                 * This is not a search query
                 * so unset the post_type query var
                 */
                unset( $query_vars['post_type'] );
            }
        }

        $scope = 'current';
        if ( ! empty( $query_vars['sites'] ) ) {
            $scope = $query_vars['sites'];
        }

        $formatted_args = ep_format_args( $query_vars );

        $ep_query = new self($formatted_args, $scope);
        $ep_query->wp_query = $query;
        
        return $ep_query;
    }

    /**
     * Get the ES search result
     *
     * @return array
     */
    public function get_result()
    {
        if ($this->result) {
            return $this->result;
        }
        $this->result = ep_search( $this->args, $this->scope );
        $this->found_posts = !empty($this->result['found_posts']) ? $this->result['found_posts'] : 0;
        return $this->result;
    }

    /**
     * Reset search results
     *
     * @return array
     */
    public function reset_result()
    {
        $this->result = array();
        $this->posts = array();
        $this->found_posts = 0;
        return $this;
    }

    /**
     * Get an array of posts from ES search result
     *
     * @return array
     */
    public function get_posts()
    {
        if ($this->posts) {
            return $this->posts;
        }

        do_action( 'ep_before_get_posts', $this);

        $result = $this->get_result();

        if ($wp_query = $this->wp_query) {
            $wp_query->found_posts = $result['found_posts'];
            $wp_query->max_num_pages = ceil( $result['found_posts'] / $wp_query->get( 'posts_per_page' ) );
        }

        $new_posts = array();

        foreach ( $result['posts'] as $post_array ) {
            $post = new stdClass();

            $post->ID = $post_array['post_id'];
            $post->site_id = get_current_blog_id();

            if ( ! empty( $post_array['site_id'] ) ) {
                $post->site_id = $post_array['site_id'];
            }

            $post->post_type = $post_array['post_type'];
            $post->post_name = $post_array['post_name'];
            $post->post_status = $post_array['post_status'];
            $post->post_title = $post_array['post_title'];
            $post->post_parent = $post_array['post_parent'];
            $post->post_content = $post_array['post_content'];
            $post->post_date = $post_array['post_date'];
            $post->post_date_gmt = $post_array['post_date_gmt'];
            $post->post_modified = $post_array['post_modified'];
            $post->post_modified_gmt = $post_array['post_modified_gmt'];
            $post->elasticsearch = true; // Super useful for debugging

            // Run through get_post() to add all expected properties (even if they're empty)
            $post = get_post( $post );

            if ( $post ) {
                $new_posts[] = $post;
            }
        }

        $this->posts = $new_posts;

        do_action( 'ep_after_get_posts', $this);
        do_action( 'ep_wp_query_search', $new_posts, $result, $this->wp_query );

        return $this->posts;
    }

    /**
     * Returns an iterator with the posts.
     *
     * @return ArrayIterator
     */
    public function getIterator() {
        return new ArrayIterator($this->get_posts());
    }

    /**
     * Returns the number of posts found.
     *
     * @return integer
     */
    public function count() 
    { 
        return $this->found_posts; 
    } 

}
