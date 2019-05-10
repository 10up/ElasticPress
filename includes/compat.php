<?php
/**
 * ElasticPress backward compat functions
 *
 * @since  3.0
 * @package elasticpress
 */

/**
 * This class was replaced with \ElasticPress\FeatureRequirementsStatus
 */
class EP_Feature_Requirements_Status {
	/**
	 * Initialize class
	 *
	 * @param int          $code Status code.
	 * @param string|array $message Message describing status.
	 * @since  2.2
	 */
	public function __construct( $code, $message = null ) {
		_deprecated_function( __CLASS__, '3.0', '\ElasticPress\FeatureRequirementsStatus' );

		$this->code    = $code;
		$this->message = $message;
	}

	/**
	 * Returns the status of a feature
	 *
	 * 0 is no issues
	 * 1 is usable but there are warnngs
	 * 2 is not usable
	 *
	 * @var    int
	 * @since  2.2
	 */
	public $code;

	/**
	 * Optional message to describe status code
	 *
	 * @var    string|array
	 * @since  2.2
	 */
	public $message;
}

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
	$requirements_status_cb   = ( ! empty( $args['requirements_status_cb'] ) ) ? addcslashes( $args['requirements_status_cb'], "'" ) : false;

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

	/**
	 * Returns requirements status of feature
	 *
	 * @since  3.0
	 */
	public function requirements_status() {
		' . ( ( ! empty( $requirements_status_cb ) ) ?
			"
			\$status = new \ElasticPress\FeatureRequirementsStatus( 0 );
			return call_user_func( '$requirements_status_cb', \$status );
			"
		:
			'return parent::requirements_status();'
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
