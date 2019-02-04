<?php
/**
 * ElasticPress backward compat functions
 *
 * @since  3.0
 * @package elasticpress
 */

/**
 * Registers a feature for use in ElasticPress
 *
 * @param  string $slug Unique slug for feature
 * @param  array  $args Feature arguments
 * @since  2.1
 */
function ep_register_feature( $slug, $args ) {
	_deprecated_function( __FUNCTION__, '3.0', esc_html__( 'Feature registration API', 'elasticpress' ) );

	$slug                     = preg_replace( '#[^a-zA-Z0-9\-\_]#is', '', $slug );
	$title                    = ( ! empty( $args['title'] ) ) ? addcslashes( $args['title'], "'" ) : false;
	$feature_box_summary_cb   = ( ! empty( $args['feature_box_summary_cb'] ) ) ? addcslashes( $args['feature_box_summary_cb'], "'" ) : false;
	$requires_install_reindex = ( ! empty( $requires_install_reindex ) ) ? 'true' : 'false';
	$setup_cb                 = ( ! empty( $args['setup_cb'] ) ) ? addcslashes( $args['setup_cb'], "'" ) : false;

	$code = "
class $slug extends ElasticPress\Feature {
	/**
	 * Initialize feature
	 *
	 * @since  3.0
	 */
	public function __construct() {
		\$this->slug = '$slug';
		\$this->requires_install_reindex = $requires_install_reindex;

		" . ( ( ! empty( $title ) ) ?
			"\$this->title = '$title';"
		:
			''
		) . '
	}

	/**
	 * Setup feature
	 *
	 * @since  3.0
	 */
	public function setup() {
		' . ( ( ! empty( $setup_cb ) ) ?
			"call_user_func( '$setup_cb' );"
		:
			''
		) . '
	}

	/**
	 * Output feature box summary
	 *
	 * @since 3.0
	 */
	public function output_feature_box_summary() {
		' . ( ( ! empty( $feature_box_summary_cb ) ) ?
			"call_user_func( '$feature_box_summary_cb' );"
		:
			''
		) . '
	}

	/**
	 * Output feature box long text
	 *
	 * @since 3.0
	 */
	public function output_feature_box_long() {
		' . ( ( ! empty( $feature_box_long_cb ) ) ?
			"call_user_func( '$feature_box_long_cb' );"
		:
			''
		) . '
	}
}
';
	// phpcs:disable
	eval( $code );
	// phpcs:enable

	ElasticPress\Features::factory()->register_feature(
		new $slug()
	);
}
