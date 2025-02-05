<?php
/**
 * ACF Repeater Field Compatibility feature
 *
 * @since 5.2.0
 * @package elasticpress
 */

namespace ElasticPress\Feature\AcfRepeater;

use ElasticPress\Feature;
use ElasticPress\FeatureRequirementsStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * ACF Repeater Field Compatibility feature class
 */
class AcfRepeater extends Feature {
	/**
	 * Initialize feature setting it's config
	 */
	public function __construct() {
		$this->slug = 'acf_repeater';

		parent::__construct();
	}

	/**
	 * Sets i18n strings.
	 */
	public function set_i18n_strings(): void {
		$this->title = esc_html__( 'ACF Repeater Field Compatibility', 'elasticpress' );

		$this->short_title = esc_html__( 'ACF Repeater', 'elasticpress' );

		$this->summary = '<p>' . __( 'Index your ACF Repeater fields as a single text field.', 'elasticpress' ) . '</p>';

		$this->docs_url = __( 'https://www.elasticpress.io/documentation/article/configuring-elasticpress-via-the-plugin-dashboard/#autosuggest', 'elasticpress' );
	}

	/**
	 * Determine WC feature reqs status
	 *
	 * @return FeatureRequirementsStatus
	 */
	public function requirements_status() {
		$status = new FeatureRequirementsStatus( 0 );

		if ( ! function_exists( 'acf_get_field_groups' ) ) {
			$status->code    = 2;
			$status->message = esc_html__( 'ACF Pro not installed.', 'elasticpress' );
		}

		return $status;
	}

	/**
	 * Setup feature functionality
	 */
	public function setup() {
		add_action( 'acf/render_field_settings', [ $this, 'render_field_settings' ] );
		add_filter( 'ep_prepare_meta_allowed_protected_keys', [ $this, 'add_meta_keys' ], 10, 2 );
	}

	/**
	 * Render field in the ACF group admin screen
	 *
	 * @param array $field ACF Field array.
	 * @return void
	 */
	public function render_field_settings( $field ): void {
		if ( ! function_exists( 'acf_render_field_setting' ) ) {
			return;
		}

		// We only want repeaters and fields that are not children of repeaters.
		if ( 'repeater' !== $field['type'] || ! empty( $field['parent_repeater'] ) ) {
			return;
		}

		// Root level fields are children of the post object.
		$post_parent = ! empty( $field['parent'] ) ? get_post( $field['parent'] ) : false;
		if ( ! $post_parent ) {
			return;
		}

		\acf_render_field_setting(
			$field,
			[
				'label'        => esc_html__( 'Index in ElasticPress', 'elasticpress' ),
				'instructions' => esc_html__( 'Index this field as a single text field', 'elasticpress' ),
				'name'         => 'ep_index_repeater_field',
				'type'         => 'true_false',
				'ui'           => 1,
			]
		);
	}

	/**
	 * Add to the weighting dashboard all the ACF Repeater fields that were checked to be indexed.
	 *
	 * @param array    $meta List of allowed meta keys
	 * @param \WP_Post $post The post object.
	 * @return array
	 */
	public function add_meta_keys( $meta, $post ) {
		$field_groups = acf_get_field_groups(
			array(
				'post_id'   => $post->ID,
				'post_type' => $post->post_type,
			)
		);

		if ( empty( $field_groups ) ) {
			return $meta;
		}

		$ep_fields = [];

		foreach ( $field_groups as $field_group ) {
			$fields = acf_get_fields( $field_group );
			foreach ( $fields as $field ) {
				if ( empty( $field['ep_index_repeater_field'] ) ) {
					continue;
				}

				$ep_fields[] = $field['name'];
			}
		}

		$meta = array_unique( array_merge( $meta, $ep_fields ) );

		return $meta;
	}
}
