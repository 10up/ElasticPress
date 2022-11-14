<?php
/**
 * Post meta report class
 *
 * @since 4.4.0
 * @package elasticpress
 */

namespace ElasticPress\StatusReport;

defined( 'ABSPATH' ) || exit;

/**
 * Post meta report class
 *
 * @package ElasticPress
 */
class PostMeta extends Report {

	/**
	 * Return the report title
	 *
	 * @return string
	 */
	public function get_title() : string {
		return __( 'Meta Fields (Per Post Type)', 'elasticpress' );
	}

	/**
	 * Return the report fields
	 *
	 * @return array
	 */
	public function get_groups() : array {
		$post_indexable = \ElasticPress\Indexables::factory()->get( 'post' );
		$post_types     = $post_indexable->get_indexable_post_types();

		$meta_keys = [];
		$all_keys  = [];
		foreach ( $post_types as $post_type ) {
			$post_type_obj = get_post_type_object( $post_type );

			$meta_keys_post_type = $post_indexable->get_indexable_meta_keys_per_post_type( $post_type );
			$all_keys            = array_merge( $all_keys, $meta_keys_post_type );

			$meta_keys[ $post_type ] = [
				'label' => $post_type_obj ? $post_type_obj->labels->singular_name : $post_type,
				'value' => count( $meta_keys_post_type ),
			];
		}

		sort( $all_keys );

		$meta_keys['total-all-post-types'] = [
			'label' => __( 'Total distinct meta keys', 'elasticpress' ),
			'value' => count( array_unique( $all_keys ) ) . "<br>\n" . \wp_sprintf( '%l', $all_keys ),
		];

		return [
			[
				'title'  => __( 'Meta Keys', 'elasticpress' ),
				'fields' => $meta_keys,
			],
		];
	}
}
