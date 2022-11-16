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

		$limited = false;

		$meta_keys = [];
		$all_keys  = [];
		foreach ( $post_types as $post_type ) {
			$post_type_obj = get_post_type_object( $post_type );

			$meta_keys_post_type = $post_indexable->get_indexable_meta_keys_per_post_type( $post_type );
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
			$title       = __( 'Meta Keys (Limited)', 'elasticpress' );
			$description = __( 'Due to the number of posts in the site, the result set per post type is limited.', 'elasticpress' );

			$all_keys = $post_indexable->get_predicted_indexable_meta_keys();
		} else {
			$title       = __( 'Meta Keys', 'elasticpress' );
			$description = '';

			$all_keys = array_unique( $all_keys );
			sort( $all_keys );
		}

		$meta_keys['total-all-post-types'] = [
			'label' => __( 'Total distinct meta keys', 'elasticpress' ),
			'value' => count( $all_keys ) . "<br>\n" . \wp_sprintf( '%l', $all_keys ),
		];

		return [
			[
				'title'       => $title,
				'description' => $description,
				'fields'      => $meta_keys,
			],
		];
	}
}
