<?php
/**
 * Synonyms Feature
 *
 * @package elasticpress
 */

namespace ElasticPress\Feature\Synonyms;

use ElasticPress\Feature;
use ElasticPress\Features;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Synonyms Feature
 *
 * @since 3.4
 * @package ElasticPress\Feature\Synonyms
 */
class Synonyms extends Feature {

	/**
	 * Internal name of the post type
	 */
	const POST_TYPE_NAME = 'ep-synonym';

	/**
	 * Synonym post id.
	 *
	 * @var int
	 */
	protected $synonym_post_id;

	/**
	 * Initialize feature setting it's config
	 *
	 * @since  3.4
	 */
	public function __construct() {
		$this->slug                     = 'synonyms';
		$this->title                    = esc_html__( 'Synonyms', 'elasticpress' );
		$this->requires_install_reindex = false;
		$this->default_settings         = [];

		parent::__construct();
	}

	/**
	 * Setup Feature Functionality
	 *
	 * @return bool
	 */
	public function setup() {
		/** Features Class @var Features $features */
		$features = Features::factory();

		$features->register_feature( $this );

		/** Search Feature @var Feature\Search\Search $search */
		$search = $features->get_registered_feature( 'search' );

		if ( ! $search->is_active() && $this->is_active() ) {
			$features->deactivate_feature( $this->slug );
			return false;
		}

		// Register a post type to hold the synonyms post.
		add_action( 'init', [ $this, 'register_post_type' ] );

		// Setup the UI.
		add_action( 'admin_menu', [ $this, 'admin_menu' ], 50 );

		// Handle the update synonyms action.
		$action = $this->get_action();
		add_action( "admin_post_$action", [ $this, 'handle_update_synonyms' ] );

		// Handle the admin notices.
		add_action( 'admin_notices', [ $this, 'admin_notices' ] );

		// Add the synonyms to the elasticsearch query.
		add_filter( 'ep_config_mapping', [ $this, 'add_search_synonyms' ] );

		return true;
	}

	/**
	 * Output feature box summary.
	 *
	 * @return void
	 */
	public function output_feature_box_summary() {
		?>
		<p><?php esc_html_e( 'Add synonyms to your searches.', 'elasticpress' ); ?></p>
		<?php
	}

	/**
	 * Output feature box long.
	 *
	 * @return void
	 */
	public function output_feature_box_long() {
		?>
		<p><?php esc_html_e( 'Create a custom synonym filter to allow ElasticPress to match alternative spellings or synonyms of your most popular search terms.', 'elasticpress' ); ?></p>
		<?php
	}

	/**
	 * Add search synonyms.
	 *
	 * @param array $mapping Elasticsearch mapping
	 *
	 * @return array
	 */
	public function add_search_synonyms( $mapping ) {
		/**
		 * Filter array of synonyms to add to a custom synonym filter.
		 *
		 * @hook ep_search_synonyms
		 * @param  {array} $mapping The elasticsearch mapping.
		 * @return  {array} The new array of search synonyms.
		 */
		$synonyms = apply_filters( 'ep_search_synonyms', $this->get_synonyms(), $mapping );

		// Ensure we have filters and that it is an array.
		if ( ! isset( $mapping['settings']['analysis']['filter'] )
			|| ! is_array( $mapping['settings']['analysis']['filter'] )
		) {
			return $mapping;
		}

		// Ensure we have analyzers and that it is an array.
		if ( ! isset( $mapping['settings']['analysis']['analyzer']['default']['filter'] )
			|| ! is_array( $mapping['settings']['analysis']['analyzer']['default']['filter'] )
		) {
			return $mapping;
		}

		// Create a custom synonym filter for EP.
		$mapping['settings']['analysis']['filter']['ep_synonyms_filter'] = array(
			'type'     => 'synonym',
			'lenient'  => true,
			'synonyms' => $synonyms,
		);

		// Tell the analyzer to use our newly created filter.
		$mapping['settings']['analysis']['analyzer']['default']['filter'] = array_merge(
			[ 'ep_synonyms_filter' ],
			$mapping['settings']['analysis']['analyzer']['default']['filter']
		);

		return $mapping;
	}

	/**
	 * Handles updating the synonym list.
	 *
	 * @return void
	 */
	public function handle_update_synonyms() {
		$nonce   = filter_input( INPUT_POST, $this->get_nonce_field(), FILTER_SANITIZE_STRING );
		$referer = filter_input( INPUT_POST, '_wp_http_referer', FILTER_SANITIZE_STRING );
		$post_id = false;

		if ( wp_verify_nonce( $nonce, $this->get_nonce_action() ) ) {
			$synonyms = filter_input( INPUT_POST, $this->get_synonym_field(), FILTER_SANITIZE_STRING );
			$post_id  = ! ! wp_insert_post(
				[
					'ID'           => $this->get_synonym_post_id(),
					'post_content' => trim( sanitize_textarea_field( $synonyms ) ),
				]
			);
		}

		wp_safe_redirect(
			add_query_arg(
				[
					'ep_synonym_update' => $post_id ? 'success' : 'error',
				],
				esc_url_raw( $referer )
			)
		);
		exit;
	}

	/**
	 * Adds the synonyms settings page to the admin menu.
	 *
	 * @return void
	 */
	public function admin_menu() {
		add_submenu_page(
			'elasticpress',
			esc_html__( 'Synonyms', 'elasticpress' ),
			esc_html__( 'Synonyms', 'elasticpress' ),
			'manage_options',
			'elasticpress-synonyms',
			[ $this, 'admin_page' ]
		);
	}

	/**
	 * Renders the synonyms settings page.
	 *
	 * @return void
	 */
	public function admin_page() {
		include EP_PATH . '/includes/partials/header.php';
		$synonym_post_id = $this->get_synonym_post_id();
		$post            = get_post( $synonym_post_id );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Synonyms', 'elasticpress' ); ?></h1>
			<form action="<?php echo esc_url( $this->get_form_action() ); ?>" method="POST">
				<?php $this->form_hidden_fields(); ?>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="ep-synonym-input">
								<?php esc_html_e( 'Synonyms', 'elasticpress' ); ?>
							</label>
						</th>
						<td>
							<textarea
								class="large-text"
								id="ep-synonym-input"
								name="<?php echo esc_attr( $this->get_synonym_field() ); ?>"
								rows="20"
							><?php echo esc_html( $post->post_content ); ?></textarea>
							<legend class="description">
								<?php esc_html_e( 'For instructions on how to use this file, see the ', 'elasticpress' ); ?>
								<a href="https://www.elastic.co/guide/en/elasticsearch/reference/current/analysis-synonym-tokenfilter.html">
									<?php esc_html_e( 'Elasticsearch synonym filter documentation', 'elasticpress' ); ?>
								</a>
								<?php esc_html_e( 'or ', 'elasticpress' ); ?>
								<a href="https://lucene.apache.org/core/6_6_2/analyzers-common/org/apache/lucene/analysis/synonym/SolrSynonymParser.html">
									<?php esc_html_e( 'SolrSynonymParser documentation.' ); ?>
								</a>
							</legend>
						</td>
					</tr>
				</table>
				<?php
				submit_button(
					( empty( $post->post_content ) )
						? __( 'Add Synonyms', 'elasticpress' )
						: __( 'Update Synonyms', 'elasticpress' )
				);
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Admin notices.
	 *
	 * @return void
	 */
	public function admin_notices() {
		if ( ! $this->is_synonym_page() ) {
			return;
		}

		$update = filter_input( INPUT_GET, 'ep_synonym_update', FILTER_SANITIZE_STRING );

		if ( ! in_array( $update, [ 'success', 'error' ], true ) ) {
			return;
		}

		$class   = ( 'success' === $update ? 'notice-success' : 'notice-error' ) . ' notice';
		$message = ( 'success' === $update )
			? __( 'Successfully stored updated synonym list. Re-sync ElasticPress to have your changes take affect.', 'elasticpress' )
			: __( 'There was an error updating the synonym list.', 'elasticpress' );

		printf(
			'<div class="%1$s"><p>%2$s</p></div>',
			esc_attr( $class ),
			esc_html( $message )
		);
	}

	/**
	 * Registers a post type for our synonyms post storage.
	 *
	 * @return void
	 */
	public function register_post_type() {
		$args = [
			'description'        => esc_html__( 'Elasticsearch Synonyms', 'elasticpress' ),
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => false,
			'show_in_menu'       => false,
			'query_var'          => true,
			'capability_type'    => 'post',
			'has_archive'        => false,
			'hierarchical'       => false,
			'menu_position'      => 100,
			'supports'           => [ 'title' ],
		];

		register_post_type( self::POST_TYPE_NAME, $args );
	}

	/**
	 * Get the post id of the post holding our synonyms.
	 *
	 * @return int The synonym post ID.
	 */
	public function get_synonym_post_id() {
		if ( ! $this->synonym_post_id ) {

			if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
				$this->synonym_post_id = get_site_option( 'elasticpress_synonyms_post_id', false );
			} else {
				$this->synonym_post_id = get_option( 'elasticpress_synonyms_post_id', false );
			}

			if ( false === $this->synonym_post_id ) {
				$post_id = wp_insert_post(
					[
						'post_title'   => __( 'Elasticpress Synonyms', 'elasticpress' ),
						'post_content' => $this->example_synonym_list(),
						'post_type'    => self::POST_TYPE_NAME,
					]
				);

				if ( $post_id ) {
					update_option( 'elasticpress_synonyms_post_id', $post_id );
					$this->synonym_post_id = $post_id;
				}
			}
		}

		return $this->synonym_post_id;
	}

	/**
	 * Get synonyms in their raw format.
	 *
	 * @return string
	 */
	public function get_synonyms_raw() {
		$post = get_post( $this->get_synonym_post_id() );

		if ( ! $post ) {
			return '';
		}

		return $post->post_content;
	}

	/**
	 * Get an array of user defined synonyms.
	 *
	 * @return array
	 */
	public function get_synonyms() {
		$synonyms_raw = $this->get_synonyms_raw();
		$synonyms     = explode( PHP_EOL, $synonyms_raw );

		return array_values(
			array_filter(
				array_map( [ $this, 'validate_synonym' ], $synonyms )
			)
		);
	}

	/**
	 * Validate a synonym.
	 *
	 * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/analysis-synonym-tokenfilter.html#_solr_synonyms
	 * @param  string $synonym The synonym.
	 * @return string|boolean  String synonym if valid, boolean false if validation failed.
	 */
	public function validate_synonym( $synonym ) {
		// Don't use empty lines.
		if ( empty( trim( $synonym ) ) ) {
			return false;
		}

		// Don't use lines that start with "#", those are comments.
		if ( 0 === strpos( $synonym, '#' ) ) {
			return false;
		}

		// Don't use lines that start with "//" though not in Solr spec.
		if ( 0 === strpos( $synonym, '//' ) ) {
			return false;
		}

		return filter_var( $synonym, FILTER_SANITIZE_STRING );
	}

	/**
	 * Get form action for admin page.
	 *
	 * @access protected
	 * @return string The admin post form action url.
	 */
	protected function get_form_action() {
		return esc_url_raw( admin_url( 'admin-post.php' ) );
	}

	/**
	 * Render admin page form hidden fields.
	 *
	 * @access protected
	 * @return void
	 */
	protected function form_hidden_fields() {
		wp_nonce_field( $this->get_nonce_action(), $this->get_nonce_field() );
		?>
		<input type="hidden" name="action" value="<?php echo esc_attr( $this->get_action() ); ?>" />
		<?php
	}

	/**
	 * Get nonce action for admin page form.
	 *
	 * @access protected
	 * @return string
	 */
	protected function get_nonce_action() {
		return $this->get_action();
	}

	/**
	 * Get nonce field for admin page form.
	 *
	 * @access protected
	 * @return string
	 */
	protected function get_nonce_field() {
		return 'ep_synonyms_nonce';
	}

	/**
	 * Get synonym field name for admin page form.
	 *
	 * @access protected
	 * @return string
	 */
	protected function get_synonym_field() {
		return 'ep_synonyms';
	}

	/**
	 * Get the action slug for admin page form.
	 *
	 * @access protected
	 * @return string
	 */
	protected function get_action() {
		return 'ep_synonyms_update';
	}

	/**
	 * Is this our synonym page.
	 *
	 * @return boolean
	 */
	protected function is_synonym_page() {
		if ( ! function_exists( '\get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();

		return (
			'elasticpress' === $screen->parent_base &&
			'elasticpress_page_elasticpress-synonyms' === $screen->base
		);
	}

	/**
	 * An example synonym that we initialize new synonyms lists with.
	 *
	 * @return string
	 */
	protected function example_synonym_list() {
		return implode(
			PHP_EOL,
			[
				__( '# Blank lines and lines starting with pound are comments.', 'elasticpress' ),
				'',
				__( '# Explicit mappings match any token sequence on the LHS of "=>"', 'elasticpress' ),
				__( '# and replace with all alternatives on the RHS.  These types of mappings', 'elasticpress' ),
				__( '# ignore the expand parameter in the schema.', 'elasticpress' ),
				__( '# Examples:', 'elasticpress' ),
				'i-pod, i pod => ipod',
				'sea biscuit, sea biscit => seabiscuit',
				'',
				__( '# Equivalent synonyms may be separated with commas and give', 'elasticpress' ),
				__( '# no explicit mapping.  In this case the mapping behavior will', 'elasticpress' ),
				__( '# be taken from the expand parameter in the schema.  This allows', 'elasticpress' ),
				__( '# the same synonym file to be used in different synonym handling strategies.', 'elasticpress' ),
				__( '# Examples:', 'elasticpress' ),
				'ipod, i-pod, i pod',
				'foozball , foosball',
				'universe , cosmos',
				'lol, laughing out loud',
				'',
				__( '# If expand==true, "ipod, i-pod, i pod" is equivalent', 'elasticpress' ),
				__( '# to the explicit mapping:', 'elasticpress' ),
				'ipod, i-pod, i pod => ipod, i-pod, i pod',
				__( '# If expand==false, "ipod, i-pod, i pod" is equivalent', 'elasticpress' ),
				__( '# to the explicit mapping:', 'elasticpress' ),
				'ipod, i-pod, i pod => ipod',
				'',
				__( '# Multiple synonym mapping entries are merged.', 'elasticpress' ),
				'foo => foo bar',
				'foo => baz',
				__( '# is equivalent to', 'elasticpress' ),
				'foo => foo bar, baz',
			]
		);
	}
}
