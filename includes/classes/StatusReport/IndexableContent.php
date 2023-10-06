<?php
/**
 * Indexable Content report class
 *
 * @since 4.4.0
 * @package elasticpress
 */

namespace ElasticPress\StatusReport;

use ElasticPress\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * IndexableContent report class
 *
 * @package ElasticPress
 */
class IndexableContent extends Report {

	/**
	 * Return the report title
	 *
	 * @return string
	 */
	public function get_title() : string {
		return __( 'Indexable Content', 'elasticpress' );
	}

	/**
	 * Return the report fields
	 *
	 * @return array
	 */
	public function get_groups() : array {
		return $this->get_indexable_content_groups();
	}

	/**
	 * Process the indexable content for all sites.
	 *
	 * @return array
	 */
	protected function get_indexable_content_groups() : array {
		$groups = [];

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$sites = Utils\get_sites( 0, true );
			foreach ( $sites as $site ) {
				switch_to_blog( $site['blog_id'] );

				$groups[] = $this->get_indexable_content_group();

				restore_current_blog();
			}
		} else {
			$groups = [ $this->get_indexable_content_group() ];
		}

		return $groups;
	}

	/**
	 * Process the indexable content.
	 *
	 * @return array
	 */
	protected function get_indexable_content_group() : array {
		$post_counts = $this->get_post_count_group();
		$meta_counts = $this->get_post_meta_fields();

		$fields = array_merge( $post_counts, $meta_counts );

		return [
			'title'  => sprintf(
				/* translators: %1%s: Site name. %2$s: Site URL. */
				__( '%1$s &mdash; %2$s', 'ep' ),
				get_option( 'blogname' ),
				site_url()
			),
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
			$post_type_label = $post_type_obj ? $post_type_obj->labels->name : $post_type;

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

			$fields[ $post_type . '_count' ] = [
				'label' => sprintf( '%s (%s)', $post_type_label, $post_type ),
				'value' => number_format_i18n( $post_count ),
			];
		}

		return $fields;
	}

	/**
	 * Process the count of meta keys.
	 *
	 * @return array
	 */
	protected function get_post_meta_fields() : array {
		$post_indexable = \ElasticPress\Indexables::factory()->get( 'post' );
		$post_types     = $post_indexable->get_indexable_post_types();

		$force_refresh = ! empty( $_GET['force_refresh'] ); // phpcs:ignore WordPress.Security.NonceVerification

		$fields           = [];
		$all_keys         = [];
		$post_count_limit = 88000;

		foreach ( $post_types as $post_type ) {
			$post_type_obj = get_post_type_object( $post_type );

			$meta_keys_post_type = $post_indexable->get_indexable_meta_keys_per_post_type( $post_type, $force_refresh );
			$all_keys            = array_merge( $all_keys, $meta_keys_post_type );

			$post_count = array_sum( (array) wp_count_posts( $post_type ) );
			$limited    = $post_count > $post_count_limit;

			$post_type_label = $post_type_obj ?
				sprintf( '%s (%s) Meta Keys', $post_type_obj->labels->singular_name, $post_type ) :
				$post_type;

			$value = count( $meta_keys_post_type );

			$description = $limited
				? sprintf(
					/* translators: %1$s: Post count limit (defaults to 80,000). %2$s: Post type name. */
					_n(
						'For performance reasons the reported count is based on the first %1$s %2$s only. The actual number may be higher.',
						'For performance reasons the reported count is based on the first %1$s %2$s only. The actual number may be higher.',
						$post_count_limit,
						'elasticpress'
					),
					number_format_i18n( $post_count_limit ),
					// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralSingle,WordPress.WP.I18n.NonSingularStringLiteralPlural
					_n( $post_type_obj->labels->singular_name, $post_type_obj->labels->name, $post_count_limit )
				) : '';

			$fields[ $post_type . '_meta_keys' ] = [
				'label'       => $post_type_label,
				'description' => $description,
				'value'       => number_format_i18n( $value ),
			];
		}

		if ( $limited ) {
			$all_keys = $post_indexable->get_predicted_indexable_meta_keys( $force_refresh );
		} else {
			$all_keys = array_unique( $all_keys );
		}

		$fields['total-all-post-types'] = [
			'label' => __( 'Total Distinct Meta Keys', 'elasticpress' ),
			'value' => count( $all_keys ),
		];

		$fields['distinct-meta-keys'] = [
			'label' => __( 'Distinct Meta Keys', 'elasticpress' ),
			'value' => wp_sprintf( '%l', $all_keys ),
		];

		return $fields;
	}
}
