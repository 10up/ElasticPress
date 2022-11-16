<?php
/**
 * Elasticsearch Server report class
 *
 * @since 4.4.0
 * @package elasticpress
 */

namespace ElasticPress\StatusReport;

defined( 'ABSPATH' ) || exit;

/**
 * ElasticsearchServer report class
 *
 * @package ElasticPress
 */
class WordPress extends Report {

	/**
	 * Return the report title
	 *
	 * @return string
	 */
	public function get_title() : string {
		return __( 'WordPress', 'elasticpress' );
	}

	/**
	 * Return the report fields
	 *
	 * @return array
	 */
	public function get_groups() : array {
		return [
			$this->get_wp_basic_group(),
			$this->get_server_group(),
			$this->get_post_count_group(),
			$this->get_post_meta_group(),
		];
	}

	/**
	 * Process basic WordPress info
	 *
	 * @return array
	 */
	protected function get_wp_basic_group() : array {
		global $wp_version;

		$fields = [];

		$fields['wp_version'] = [
			'label' => __( 'WordPress Version', 'elasticpress' ),
			'value' => $wp_version,
		];

		$fields['home_url'] = [
			'label' => __( 'Home URL', 'elasticpress' ),
			'value' => get_home_url(),
		];

		$fields['site_url'] = [
			'label' => __( 'Site URL', 'elasticpress' ),
			'value' => get_site_url(),
		];

		$fields['is_multisite'] = [
			'label' => __( 'Multisite', 'elasticpress' ),
			'value' => is_multisite(),
		];

		$active_theme  = wp_get_theme();
		$theme_name    = wp_strip_all_tags( $active_theme->get( 'Name' ) );
		$theme_version = wp_strip_all_tags( $active_theme->get( 'Version' ) );

		$fields['theme'] = [
			'label' => __( 'Theme', 'elasticpress' ),
			'value' => sprintf( '%s (%s)', $theme_name, $theme_version ),
		];

		if ( is_child_theme() ) {
			$parent_theme   = wp_get_theme( $active_theme->get( 'Template' ) );
			$parent_name    = wp_strip_all_tags( $parent_theme->get( 'Name' ) );
			$parent_version = wp_strip_all_tags( $parent_theme->get( 'Version' ) );

			$fields['parent_theme'] = [
				'label' => __( 'Parent Theme', 'elasticpress' ),
				'value' => sprintf( '%s (%s)', $parent_name, $parent_version ),
			];
		}

		$plugins = [];
		foreach ( get_plugins() as $plugin_path => $plugin ) {
			// If plugin is not active, skip it.
			if ( ! is_plugin_active( $plugin_path ) ) {
				continue;
			}

			$plugins[] = sprintf( '%s (%s)', $plugin['Name'], $plugin['Version'] );
		}
		$fields['plugins'] = [
			'label' => __( 'Active Plugins', 'elasticpress' ),
			'value' => wp_sprintf( '%l', $plugins ),
		];

		$fields['revisions'] = [
			'label' => __( 'Revisions allowed', 'elasticpress' ),
			'value' => WP_POST_REVISIONS === true ? 'all' : (int) WP_POST_REVISIONS,
		];

		return [
			'title'  => __( 'WordPress Environment', 'elasticpress' ),
			'fields' => $fields,
		];
	}

	/**
	 * Process the info about the server
	 *
	 * @return array
	 */
	protected function get_server_group() : array {
		$fields = [];

		$fields['php_version'] = [
			'label' => __( 'PHP Version', 'elasticpress' ),
			'value' => phpversion(),
		];

		$fields['memory_limit'] = [
			'label' => __( 'Memory Limit', 'elasticpress' ),
			'value' => WP_MEMORY_LIMIT,
		];

		$fields['timeout'] = [
			'label' => __( 'Maximum Execution Time', 'elasticpress' ),
			'value' => (int) ini_get( 'max_execution_time' ),
		];

		return [
			'title'  => __( 'Server Environment', 'elasticpress' ),
			'fields' => $fields,
		];
	}

	/**
	 * Process the count of all indexable post types and status
	 *
	 * @return array
	 */
	protected function get_post_count_group() : array {
		$post_indexable = \ElasticPress\Indexables::factory()->get( 'post' );
		$post_types     = $post_indexable->get_indexable_post_types();

		$post_stati = $post_indexable->get_indexable_post_status();

		foreach ( $post_types as $post_type ) {
			$post_type_obj   = get_post_type_object( $post_type );
			$post_type_label = $post_type_obj ? $post_type_obj->labels->singular_name : $post_type;

			$post_count = (array) wp_count_posts( $post_type );

			$post_count = array_reduce(
				array_keys( $post_count ),
				function ( $count, $post_status ) use ( $post_count, $post_stati ) {
					if ( in_array( $post_status, $post_stati, true ) ) {
						$count += $post_count[ $post_status ];
					}
					return $count;
				},
				0
			);

			$fields[ $post_type ] = [
				'label' => sprintf( '%s (%s)', $post_type_label, $post_type ),
				'value' => $post_count,
			];
		}

		return [
			'title'  => __( 'Post Type Counts', 'elasticpress' ),
			'fields' => $fields,
		];
	}
	/**
	 * Process the count of meta keys.
	 *
	 * @return array
	 */
	protected function get_post_meta_group() : array {
		$post_indexable = \ElasticPress\Indexables::factory()->get( 'post' );
		$post_types     = $post_indexable->get_indexable_post_types();

		$limited       = false;
		$force_refresh = ! empty( $_GET['force_refresh'] ); // phpcs:ignore WordPress.Security.NonceVerification

		$meta_keys = [];
		$all_keys  = [];
		foreach ( $post_types as $post_type ) {
			$post_type_obj = get_post_type_object( $post_type );

			$meta_keys_post_type = $post_indexable->get_indexable_meta_keys_per_post_type( $post_type, $force_refresh );
			$all_keys            = array_merge( $all_keys, $meta_keys_post_type );

			$post_count = array_sum( (array) wp_count_posts( $post_type ) );
			$limited    = $limited || ( $post_count > 80000 );

			$post_type_label = $post_type_obj ?
				sprintf( '%s (%s)', $post_type_obj->labels->singular_name, $post_type ) :
				$post_type;

			$meta_keys[ $post_type ] = [
				'label' => $post_type_label,
				'value' => count( $meta_keys_post_type ),
			];
		}

		if ( $limited ) {
			$title       = __( 'Meta Key Counts (Limited)', 'elasticpress' );
			$description = __( 'Due to the number of posts in the site, the result set per post type is limited.', 'elasticpress' );

			$all_keys = $post_indexable->get_predicted_indexable_meta_keys();
		} else {
			$title       = __( 'Meta Key Counts', 'elasticpress' );
			$description = '';

			$all_keys = array_unique( $all_keys );
			sort( $all_keys );
		}

		$meta_keys['total-all-post-types'] = [
			'label' => __( 'Total Distinct Meta Keys', 'elasticpress' ),
			'value' => count( $all_keys ),
		];

		$meta_keys['distinct-meta-keys'] = [
			'label' => __( 'Distinct Meta Keys', 'elasticpress' ),
			'value' => wp_sprintf( '%l', $all_keys ),
		];

		return [
			'title'       => $title,
			'description' => $description,
			'fields'      => $meta_keys,
		];
	}
}
