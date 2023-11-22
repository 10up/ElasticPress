<?php
/**
 * Comments feature
 *
 * @since   3.6.0
 * @package elasticpress
 */

namespace ElasticPress\Feature\Comments;

use ElasticPress\Feature;
use ElasticPress\FeatureRequirementsStatus;
use ElasticPress\Features;
use ElasticPress\Indexable;
use ElasticPress\Indexables;
use ElasticPress\Utils;
use ElasticPress\REST;

/**
 * Comments feature class
 */
class Comments extends Feature {
	/**
	 * Whether the feature should be always visible in the dashboard
	 *
	 * @since 5.0.0
	 * @var boolean
	 */
	protected $is_visible = false;

	/**
	 * Initialize feature, setting it's config
	 *
	 * @since 3.6.0
	 */
	public function __construct() {
		$this->slug = 'comments';

		$this->title = esc_html__( 'Comments', 'elasticpress' );

		$this->summary = '<p>' . __( 'This feature will empower your website to overcome traditional WordPress comment search and query limitations that can present themselves at scale. This feature is only needed if you are using <code>WP_Comment_Query</code> directly.', 'elasticpress' ) . '</p>';

		$this->docs_url = __( 'https://elasticpress.zendesk.com/hc/en-us/articles/360050447492-Configuring-ElasticPress-via-the-Plugin-Dashboard#comments', 'elasticpress' );

		$this->requires_install_reindex = true;

		Indexables::factory()->register( new Indexable\Comment\Comment(), false );

		parent::__construct();
	}

	/**
	 * Setup search functionality
	 *
	 * @since 3.6.0
	 */
	public function setup() {
		Indexables::factory()->activate( 'comment' );

		add_action( 'init', [ $this, 'register_block' ] );
		add_action( 'init', [ $this, 'search_setup' ] );
		add_action( 'widgets_init', [ $this, 'register_widget' ] );
		add_action( 'rest_api_init', [ $this, 'rest_api_init' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'frontend_scripts' ] );
		add_filter( 'widget_types_to_hide_from_legacy_widget_block', [ $this, 'hide_legacy_widget' ] );
	}

	/**
	 * Setup search integration
	 *
	 * @since 3.6.0
	 */
	public function search_setup() {
		$admin_integration = apply_filters( 'ep_admin_wp_query_integration', false );

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			/**
			 * Filter to integrate with admin ajax queries
			 *
			 * @hook ep_ajax_wp_query_integration
			 * @param  {bool} $integrate True to integrate
			 * @return  {bool} New value
			 */
			if ( ! apply_filters( 'ep_ajax_wp_query_integration', false ) ) {
				return;
			} else {
				$admin_integration = true;
			}
		}

		if ( is_admin() && ! $admin_integration ) {
			return;
		}

		add_filter( 'ep_elasticpress_enabled', [ $this, 'integrate_search_queries' ], 10, 2 );
	}

	/**
	 * Output feature box long text
	 *
	 * @since 3.6.0
	 */
	public function output_feature_box_long() {
		?>
		<p><?php esc_html_e( 'This feature will empower your website to overcome traditional WordPress comment search and query limitations that can present themselves at scale.', 'elasticpress' ); ?></p>
		<?php
	}

	/**
	 * Enable integration on search queries
	 *
	 * @param  bool              $enabled Whether EP is enabled
	 * @param  \WP_Comment_Query $query Current query object.
	 * @since  3.6.0
	 * @return bool
	 */
	public function integrate_search_queries( $enabled, $query ) {
		if ( ! is_a( $query, 'WP_Comment_Query' ) ) {
			return $enabled;
		}

		if ( isset( $query->query_vars['ep_integrate'] ) && ! filter_var( $query->query_vars['ep_integrate'], FILTER_VALIDATE_BOOLEAN ) ) {
			$enabled = false;
		} elseif ( ! empty( $query->query_vars['search'] ) ) {
			$enabled = true;
		}

		return $enabled;
	}

	/**
	 * Determine feature reqs status
	 *
	 * @since  3.6.0
	 * @return FeatureRequirementsStatus
	 */
	public function requirements_status() {
		$status = new FeatureRequirementsStatus( 1 );

		return $status;
	}

	/**
	 * Register comments widget
	 *
	 * @since  3.6.0
	 */
	public function register_widget() {
		register_widget( __NAMESPACE__ . '\Widget' );
	}

	/**
	 * Registers the API endpoint to search for comments
	 *
	 * @since  3.6.0
	 */
	public function rest_api_init() {
		$controller = new REST\Comments();
		$controller->register_routes();
	}

	/**
	 * Get a list of searchable post types that support comments.
	 *
	 * @return array Array of post type labels keyed by post type.
	 * @since 4.4.0
	 */
	public static function get_searchable_post_types() {
		$searchable_post_types = array();

		$post_types = Features::factory()->get_registered_feature( 'search' )->get_searchable_post_types();
		$post_types = array_filter(
			$post_types,
			function( $post_type ) {
				return post_type_supports( $post_type, 'comments' );
			}
		);

		foreach ( $post_types as $post_type ) {
			$post_type_object = get_post_type_object( $post_type );
			$post_type_labels = get_post_type_labels( $post_type_object );

			$searchable_post_types[ $post_type ] = $post_type_labels->name;
		}

		return $searchable_post_types;
	}

	/**
	 * Enqueue frontend scripts.
	 *
	 * @since 4.4.0
	 */
	public function frontend_scripts() {
		wp_register_script(
			'elasticpress-comments',
			EP_URL . 'dist/js/comments-script.js',
			Utils\get_asset_info( 'comments-script', 'dependencies' ),
			Utils\get_asset_info( 'comments-script', 'version' ),
			true
		);

		wp_set_script_translations( 'elasticpress-comments', 'elasticpress' );

		wp_register_style(
			'elasticpress-comments',
			EP_URL . 'dist/css/comments-styles.css',
			Utils\get_asset_info( 'comments-styles', 'dependencies' ),
			Utils\get_asset_info( 'comments-styles', 'version' )
		);

		$default_script_data = [
			'noResultsFoundText'    => esc_html__( 'We could not find any results', 'elasticpress' ),
			'minimumLengthToSearch' => 2,
			'restApiEndpoint'       => get_rest_url( null, 'elasticpress/v1/comments' ),
		];

		/**
		 * Filter the l10n data attached to the Widget Search Comments script
		 *
		 * @since  3.6.0
		 * @hook ep_comment_search_widget_l10n_data_script
		 * @param  {array} $default_script_data Default data attached to the script
		 * @return  {array} New l10n data to be attached
		 */
		$script_data = apply_filters( 'ep_comment_search_widget_l10n_data_script', $default_script_data );

		wp_localize_script(
			'elasticpress-comments',
			'epc',
			$script_data
		);
	}

	/**
	 * Register block.
	 *
	 * @since 4.4.0
	 */
	public function register_block() {
		/**
		 * Registering it here so translation works
		 *
		 * @see https://core.trac.wordpress.org/ticket/54797#comment:20
		 */
		wp_register_script(
			'elasticpress-comments-editor-script',
			EP_URL . 'dist/js/comments-block-script.js',
			Utils\get_asset_info( 'comments-block-script', 'dependencies' ),
			Utils\get_asset_info( 'comments-block-script', 'version' ),
			true
		);

		wp_set_script_translations( 'elasticpress-comments-editor-script', 'elasticpress' );

		wp_localize_script(
			'elasticpress-comments-editor-script',
			'epComments',
			[
				'searchablePostTypes' => self::get_searchable_post_types(),
			]
		);

		register_block_type_from_metadata(
			EP_PATH . 'assets/js/blocks/comments',
			[
				'render_callback' => [ $this, 'render_block' ],
			]
		);
	}

	/**
	 * Render block.
	 *
	 * @param array $attributes Block attributes
	 * @since 4.4.0
	 * @return string
	 */
	public function render_block( $attributes ) {
		$wrapper_id         = 'ep-search-comments-' . uniqid();
		$wrapper_attributes = get_block_wrapper_attributes(
			array(
				'id'    => $wrapper_id,
				'class' => 'ep-widget-search-comments',
			)
		);

		$label = ! empty( $attributes['label'] )
			? sprintf(
				'<label for="%1$s-s">%2$s</label>',
				esc_attr( $wrapper_id ),
				wp_kses_post( $attributes['label'] )
			)
			: '';

		$post_types_input = ! empty( $attributes['postTypes'] )
			? sprintf(
				'<input class="ep-widget-search-comments-post-type" type="hidden" id="%1$s-post-type" value="%2$s">',
				esc_attr( $wrapper_id ),
				esc_attr( implode( ',', $attributes['postTypes'] ) )
			)
			: '';

		$block_html = sprintf(
			'<div %1$s>%2$s%3$s</div>',
			$wrapper_attributes, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$label,
			$post_types_input
		);

		return $block_html;
	}

	/**
	 * Hide the legacy widget.
	 *
	 * Hides the legacy widget in favor of the Block when the block editor
	 * is in use and the legacy widget has not been used.
	 *
	 * @since 4.4.0
	 * @param array $widgets An array of excluded widget-type IDs.
	 * @return array array of excluded widget-type IDs to hide.
	 */
	public function hide_legacy_widget( $widgets ) {
		$widgets[] = 'ep-comments';

		return $widgets;
	}
}
