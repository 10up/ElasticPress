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
use ElasticPress\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * ACF Repeater Field Compatibility feature class
 */
class AcfRepeater extends Feature {
	/**
	 * List of ACF functions we use
	 *
	 * @var array
	 */
	protected $acf_functions = [
		'acf_get_field_groups',
		'acf_render_field_setting',
		'acf_get_fields',
		'acf_get_field',
		'get_field',
	];

	/**
	 * Initialize feature setting it's config
	 */
	public function __construct() {
		$this->slug = 'acf_repeater';

		$this->group = 'third-party-plugins';

		parent::__construct();
	}

	/**
	 * Sets i18n strings.
	 */
	public function set_i18n_strings(): void {
		$this->title = esc_html__( 'ACF Repeater Field Compatibility', 'elasticpress' );

		$this->short_title = esc_html__( 'ACF Repeater Field', 'elasticpress' );

		$this->summary = '<p>' . __( 'Index your ACF Repeater fields as a JSON object and, optionally, make it searchable in the Search Fields & Weighting dashboard.', 'elasticpress' ) . '</p>';

		$this->docs_url = __( 'https://www.elasticpress.io/documentation/article/acf-repeater-field-compatibility-feature/', 'elasticpress' );
	}

	/**
	 * Determine WC feature reqs status
	 *
	 * @return FeatureRequirementsStatus
	 */
	public function requirements_status() {
		$status = new FeatureRequirementsStatus( 0 );

		foreach ( $this->acf_functions as $function ) {
			if ( ! function_exists( $function ) ) {
				$status->code    = 2;
				$status->message = esc_html__( 'ACF Pro not installed.', 'elasticpress' );
				break;
			}
		}

		return $status;
	}

	/**
	 * Setup feature functionality
	 */
	public function setup() {
		add_action( 'acf/render_field_settings', [ $this, 'render_field_settings' ] );
		add_filter( 'ep_prepare_meta_allowed_protected_keys', [ $this, 'allow_meta_keys' ], 10, 2 );
		add_filter( 'ep_prepare_meta_data', [ $this, 'add_meta_keys' ], 10, 2 );
	}

	/**
	 * Render field in the ACF group admin screen
	 *
	 * @param array $field ACF Field array.
	 * @return void
	 */
	public function render_field_settings( $field ): void {
		// We only want repeaters and fields that are not children of repeaters.
		if ( 'repeater' !== $field['type'] || ! empty( $field['parent_repeater'] ) ) {
			return;
		}

		// Root level fields are children of the post object.
		$post_parent = ! empty( $field['parent'] ) ? get_post( $field['parent'] ) : false;
		if ( ! $post_parent ) {
			return;
		}

		/**
		 * Filter whether EP should or not display the field setting in ACF
		 *
		 * @hook ep_acf_repeater_should_display_field_setting
		 * @since 5.3.0
		 * @param {bool}  $should_display Whether should or not display the field setting in ACF
		 * @param {array} $field          The ACF Field array
		 * @return {bool} New value of $should_display
		 */
		if ( ! apply_filters( 'ep_acf_repeater_should_display_field_setting', true, $field ) ) {
			return;
		}

		$instructions = wp_kses_post(
			sprintf(
				/* translators: %s: post type name */
				__( 'Index this field as a JSON object. If you want to make it searchable, do not forget to enable it under the related post types in the <a href="%1$s">Search Fields & Weighting dashboard</a>. To index existent content you can either manually save posts with this field or <a href="%2$s">run a sync</a>.', 'elasticpress' ),
				esc_url( admin_url( 'admin.php?page=elasticpress-weighting' ) ),
				Utils\get_sync_url()
			)
		);

		\acf_render_field_setting(
			$field,
			[
				'label'        => esc_html__( 'Index in ElasticPress', 'elasticpress' ),
				'instructions' => $instructions,
				'name'         => 'ep_acf_repeater_index_field',
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
	public function allow_meta_keys( $meta, $post ) {
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
				if ( empty( $field['ep_acf_repeater_index_field'] ) ) {
					continue;
				}

				$ep_fields[] = $field['name'];
			}
		}

		$meta = array_unique( array_merge( $meta, $ep_fields ) );

		return $meta;
	}

	/**
	 * Add the ACF Repeater fields to the ES document meta data.
	 *
	 * @param array    $meta All post meta data
	 * @param \WP_Post $post The post object
	 * @return array
	 */
	public function add_meta_keys( $meta, $post ) {
		$meta_keys = array_keys( $meta );
		foreach ( $meta_keys as $key ) {
			$field = acf_get_field( $key );

			if ( ! $field || empty( $field['ep_acf_repeater_index_field'] ) || 'repeater' !== $field['type'] ) {
				continue;
			}

			$value_field   = get_field( $key, $post->ID );
			$value_encoded = wp_json_encode( $value_field );

			/**
			 * Filter the ACF Repeater field value before it is indexed
			 *
			 * @hook ep_acf_repeater_meta_value
			 * @since 5.3.0
			 * @param {string}  $value_encoded Repeater field value encoded
			 * @param {array}   $value_field   Original field value
			 * @param {string}  $key           The meta field key
			 * @param {WP_Post} $post          The Post object
			 * @return {mixed} New value of $value_encoded
			 */
			$meta[ $key ] = apply_filters( 'ep_acf_repeater_meta_value', $value_encoded, $value_field, $key, $post );
		}

		return $meta;
	}
}
