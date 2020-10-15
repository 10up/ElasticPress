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
 * Search Elasticsearch for related content
 *
 * @param  int $post_id Post ID
 * @param  int $return Number of posts to get
 * @since  2.1
 * @return array|bool
 */
function ep_find_related( $post_id, $return = 5 ) {
	_deprecated_function( __FUNCTION__, '3.0', 'ElasticPress\Features::factory()->get_registered_feature' );

	$feature = \ElasticPress\Features::factory()->get_registered_feature( 'related_posts' );

	return ( ! empty( $feature ) ) ? $feature->find_related( $post_id, $return ) : false;
}

/**
 * Index a post given an ID
 *
 * @param  int $post_id  Post ID
 * @return boolean|array
 */
function ep_index_post( $post_id ) {
	_deprecated_function( __FUNCTION__, '3.0', "ElasticPress\Indexables::factory()->get( 'post' )->index" );

	return \ElasticPress\Indexables::factory()->get( 'post' )->index( $post_id, true );
}

/**
 * Get index name for the posts index
 *
 * @param  int $blog_id Blog ID
 * @since  3.4
 * @return string
 */
function ep_get_index_name( $blog_id = null ) {
	_deprecated_function( __FUNCTION__, '3.0', "ElasticPress\Indexables::factory()->get( 'post' )->get_index_name()" );

	return \ElasticPress\Indexables::factory()->get( 'post' )->get_index_name( $blog_id );
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

	$callbacks = [
		'feature_box_summary_cb',
		'feature_box_long_cb',
		'setup_cb',
		'requirements_status_cb',
	];

	$resolved_callbacks = [];

	if ( empty( $GLOBALS['ep_legacy_feature_refs'] ) ) {
		$GLOBALS['ep_legacy_feature_refs'] = [];
	}

	$GLOBALS['ep_legacy_feature_refs'][ $slug ] = [];

	foreach ( $callbacks as $callback_key ) {
		if ( empty( $args[ $callback_key ] ) ) {
			continue;
		}

		$callback = $args[ $callback_key ];

		if ( is_string( $callback ) ) {
			$resolved_callbacks[ $callback_key ] = "'" . $callback . "'";
		} elseif ( is_array( $callback ) ) {
			if ( is_string( $callback[0] ) ) {
				$resolved_callbacks[ $callback_key ] = 'array( "' . addcslashes( $callback[0], "'" ) . '", "' . addcslashes( $callback[1], "'" ) . '" )';
			} else {
				$GLOBALS['ep_legacy_feature_refs'][ $slug ][ $callback_key ] = $callback[0];

				$resolved_callbacks[ $callback_key ] = 'array( $GLOBALS["ep_legacy_feature_refs"]["' . $slug . '"]["' . $callback_key . '"], "' . addcslashes( $callback[1], "'" ) . '" )';
			}
		} else {
			$GLOBALS['ep_legacy_feature_refs'][ $slug ][ $callback_key ] = $callback;

			$resolved_callbacks[ $callback_key ] = '$GLOBALS["ep_legacy_feature_refs"]["' . $slug . '"]["' . $callback_key . '"]';
		}
	}

	$slug                     = preg_replace( '#[^a-zA-Z0-9\-\_]#is', '', $slug );
	$title                    = ( ! empty( $args['title'] ) ) ? addcslashes( $args['title'], "'" ) : false;
	$requires_install_reindex = ( ! empty( $requires_install_reindex ) ) ? 'true' : 'false';

	$class_name = 'EP' . $slug . 'Feature';

	if ( class_exists( $class_name ) ) {
		return;
	}

	// phpcs:disable
	$code = "
class $class_name extends ElasticPress\Feature {
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
		' . ( ( ! empty( $resolved_callbacks['setup_cb'] ) ) ?
			'call_user_func( ' . $resolved_callbacks['setup_cb'] . ' );'
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
		' . ( ( ! empty( $resolved_callbacks['feature_box_summary_cb'] ) ) ?
			'call_user_func( ' . $resolved_callbacks['feature_box_summary_cb'] . ' );'
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
		' . ( ( ! empty( $resolved_callbacks['feature_box_long_cb'] ) ) ?
			'call_user_func( ' . $resolved_callbacks['feature_box_long_cb'] . ' );'
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
		' . ( ( ! empty( $resolved_callbacks['requirements_status_cb'] ) ) ?
			"
			\$status = new \ElasticPress\FeatureRequirementsStatus( 0 );
			return call_user_func( " . $resolved_callbacks['requirements_status_cb'] . ", \$status );
			"
		:
			'return parent::requirements_status();'
		) . '
	}
}
';

	eval( $code );
	// phpcs:enable

	ElasticPress\Features::factory()->register_feature(
		new $class_name()
	);
}
