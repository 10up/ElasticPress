<?php
/**
 * Synonyms Feature
 *
 * @package elasticpress
 */

namespace ElasticPress\Feature\Synonyms;

use ElasticPress\Feature;
use ElasticPress\Features;
use ElasticPress\Indexables;
use ElasticPress\Elasticsearch;

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
		$this->requires_install_reindex = true;

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
		add_action( 'admin_enqueue_scripts', [ $this, 'scripts' ] );

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

		// Ensure we have synonyms to add.
		if ( ! is_array( $synonyms ) ) {
			return $mapping;
		}

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

			// Construct the update synonym filter setting.
			$setting['index']['analysis']['filter']['ep_synonyms_filter'] = [
				'type'     => 'synonym',
				'lenient'  => true,
				'synonyms' => $this->get_synonyms(),
			];

			// Update the index with the new synonyms.
			$indexable  = Indexables::factory()->get( 'post' );
			$index_name = $indexable->get_index_name();
			$update     = Elasticsearch::factory()->update_index_settings( $index_name, $setting, true );
		}

		wp_safe_redirect(
			add_query_arg(
				[
					'ep_synonym_update' => ( $post_id && $update ) ? 'success' : 'error',
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
	 * Enqueues scripts and styles.
	 *
	 * @return void
	 */
	public function scripts() {
		if ( ! $this->is_synonym_page() ) {
			return;
		}

		wp_enqueue_script( 'ep_synonyms_scripts', EP_URL . 'dist/js/synonyms-script.min.js', [], EP_VERSION, true );
		wp_enqueue_style( 'ep_synonyms_styles', EP_URL . 'dist/css/synonyms-styles.min.css', [], EP_VERSION, 'all' );
		wp_localize_script(
			'ep_synonyms_scripts',
			'epSynonyms',
			array(
				'i18n' => $this->get_localized_strings(),
				'data' => $this->get_localized_data(),
			)
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
			<h1><?php esc_html_e( 'Manage Synonyms', 'elasticpress' ); ?></h1>
			<p><?php esc_html_e( 'Synonyms enable more flexible search results that show relevant results even without an exact match. Synonyms can be defined as a sets where all words are synonyms for each other, or as alternatives where searches for the primary word will also match the rest, but no vice versa.', 'elasticpress' ); ?></p>
			<form action="<?php echo esc_url( $this->get_form_action() ); ?>" method="POST">
				<?php $this->form_hidden_fields(); ?>
				<div id="synonym-root"></div>
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
			? __( 'Successfully updated synonym filter.', 'elasticpress' )
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
			$this->synonym_post_id = get_option( 'elasticpress_synonyms_post_id', false );

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

		return filter_var( trim( $synonym ), FILTER_SANITIZE_STRING );
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
		return ( 'elasticpress_page_elasticpress-synonyms' === $screen->base );
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
				__( '# Defined sets ( equivalent synonyms).', 'elasticpress' ),
				'sneakers, tennis shoes, trainers, runners',
				'',
				__( '# Defined alternatives (explicit mappings).', 'elasticpress' ),
				'shoes => sneaker, sandal, boots, high heels',
			]
		);
	}

	/**
	 * Gets localized strings for use on the front end.
	 *
	 * @return array
	 */
	protected function get_localized_strings() {
		return array(
			'setsTitle'                  => __( 'Sets', 'elasticpress' ),
			'setsDescription'            => __( 'Sets are terms that will all match each other for search results. This is useful where all words are considered equivalent, such as product renaming or regional variations like sneakers, tennis shoes, trainers, and runners.', 'elasticpress' ),
			'setsInputHeading'           => __( 'Comma separated list of terms', 'elasticpress' ),
			'setsAddButtonText'          => __( 'Add Set', 'elasticpress' ),

			'alternativesTitle'          => __( 'Alternatives', 'elasticpress' ),
			'alternativesDescription'    => __( 'Alternatives are terms that will also be matched when you search for the primary term. For instance, a search for shoes can also include results for sneaker, sandals, boots, and high heels.', 'elasticpress' ),
			'alternativesPrimaryHeading' => __( 'Primary term', 'elasticpress' ),
			'alternativesInputHeading'   => __( 'Comma separated list of alternatives', 'elasticpress' ),
			'alternativesAddButtonText'  => __( 'Add Alternative', 'elasticpress' ),

			'solrTitle'                  => __( 'Advanced Synonym Editor', 'elasticpress' ),
			'solrDescription'            => __( 'When you add Sets and Alternatives above, we reduce them to SolrSynonyms which Elasticsearch can understand. If you are an advanced user, you can edit synonyms directly using Solr synonym formatting. This is beneficial if you want to import a large dictionary of synonyms, or want to export this site\'s synonyms for use on another site.', 'elasticpress' ),
			'solrInputHeading'           => __( 'SolrSynonym Text', 'elasticpress' ),
			'solrEditButtonText'         => __( 'Edit File (Advanced)', 'elasticpress' ),
			'solrApplyButtonText'        => __( 'Apply Changes', 'elasticpress' ),

			'synonymsTextareaInputName'  => $this->get_synonym_field(),
		);
	}

	/**
	 * Get data to export to the frontend with localization strings.
	 *
	 * @return array
	 */
	protected function get_localized_data() {
		$data     = array(
			'sets'         => array(),
			'alternatives' => array(),
			'solrVisible'  => ( defined( 'WP_EP_DEBUG' ) && WP_EP_DEBUG ),
		);
		$synonyms = $this->get_synonyms();

		foreach ( $synonyms as $line ) {
			$synonym = array();
			if ( strpos( $line, '=>' ) ) {
				$tokens = explode( '=>', $line );
				array_push( $synonym, self::prepare_localized_token( $tokens[0], true ) );
				array_push(
					$synonym,
					...array_map(
						array( __CLASS__, 'prepare_localized_token' ),
						explode( ',', $tokens[1] )
					)
				);
				array_push( $data['alternatives'], $synonym );
			} else {
				array_push(
					$synonym,
					...array_map(
						array( __CLASS__, 'prepare_localized_token' ),
						explode( ',', $line )
					)
				);
				array_push( $data['sets'], $synonym );
			}
		}

		return $data;
	}

	/**
	 * Prepare localized token.
	 *
	 * @param string  $token    The synonym token to prepare.
	 * @param boolean $primary Whether this string is the primary term of an alternative.
	 * @return array
	 */
	protected static function prepare_localized_token( $token, $primary = false ) {
		return array(
			'label'   => trim( sanitize_text_field( $token ) ),
			'value'   => trim( sanitize_text_field( $token ) ),
			'primary' => $primary,
		);
	}
}
