<?php

class EP_Config {

	/**
	 * Get a singleton instance of the class
	 *
	 * @since 0.1.0
	 * @return EP_Config
	 */
	public static function factory() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Generates the index name for the current site
	 *
	 * @param int $blog_id (optional) Blog ID. Defaults to current blog.
	 * @since 0.9
	 * @return string
	 */
	public function get_index_name( $blog_id = null ) {
		if ( ! $blog_id ) {
			$blog_id = get_current_blog_id();
		}

		$site_url = get_site_url( $blog_id );

		if ( ! empty( $site_url ) ) {
			$index_name = preg_replace( '#https?://(www\.)?#i', '', $site_url );
			$index_name = preg_replace( '#[^\w]#', '', $index_name ) . '-' . $blog_id;
		} else {
			$index_name = false;
		}

		return apply_filters( 'ep_index_name', $index_name );
	}

	/**
	 * Returns the index url given an index name. Defaults to current index
	 *
	 * @param string|array $index
	 * @since 0.9
	 * @return string
	 */
	public function get_index_url( $index = null ) {
		if ( null === $index ) {
			$index = $this->get_index_name();
		} elseif ( is_array( $index ) ) {
			$index = implode( ',', array_filter( $index ) );
		}

		return untrailingslashit( EP_HOST ) . '/' . $index;
	}

	/**
	 * Returns indexable post types for the current site
	 *
	 * @since 0.9
	 * @return mixed|void
	 */
	public function get_indexable_post_types() {
		$post_types = get_post_types( array( 'public' => true ) );

		return apply_filters( 'ep_indexable_post_types', $post_types );
	}

	/**
	 * Return indexable post_status for the current site
	 *
	 * @since 1.3
	 * @return array
	 */
	public function get_indexable_post_status() {
		return apply_filters( 'ep_indexable_post_status', array( 'publish' ) );
	}

	/**
	 * Generate network index name for alias
	 *
	 * @since 0.9
	 * @return string
	 */
	public function get_network_alias() {
		$url = network_site_url();
		$slug = preg_replace( '#https?://(www\.)?#i', '', $url );
		$slug = preg_replace( '#[^\w]#', '', $slug );

		$alias = $slug . '-global';

		return apply_filters( 'ep_global_alias', $alias );
	}
}

EP_Config::factory();

/**
 * Accessor functions for methods in above class. See doc blocks above for function details.
 */

function ep_get_index_url( $index = null ) {
	return EP_Config::factory()->get_index_url( $index );
}

function ep_get_index_name( $blog_id = null ) {
	return EP_Config::factory()->get_index_name( $blog_id );
}

function ep_get_indexable_post_types() {
	return EP_Config::factory()->get_indexable_post_types();
}

function ep_get_indexable_post_status() {
	return EP_Config::factory()->get_indexable_post_status();
}

function ep_get_network_alias() {
	return EP_Config::factory()->get_network_alias();
}
