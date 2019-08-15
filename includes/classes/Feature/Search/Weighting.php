<?php
/**
 * Weighting dashboard for ElasticPress
 *
 * @package elasticpress
 */

namespace ElasticPress\Feature\Search;

use ElasticPress\Features;
use ElasticPress\Indexable\Post\Post;
use ElasticPress\Screen;

/**
 * Controls search weighting and search fields dashboard
 *
 * @package ElasticPress\Feature\Search
 */
class Weighting {

	/**
	 * Sets up the weighting module
	 */
	public function setup() {
		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			add_action( 'network_admin_menu', [ $this, 'add_weighting_submenu_page' ] );
		} else {
			add_action( 'admin_menu', [ $this, 'add_weighting_submenu_page' ] );
		}

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts_styles' ] );
		add_action( 'admin_init', [ $this, 'handle_settings' ] );
		add_filter( 'ep_formatted_args_query', [ $this, 'do_weighting' ], 20, 2 ); // After date decay, etc are injected
	}

	/**
	 * Enqueue styles and scripts
	 *
	 * @since 3.1.2
	 */
	public function enqueue_scripts_styles() {
		if ( in_array( Screen::factory()->get_current_screen(), [ 'weighting' ], true ) ) {
			wp_enqueue_script( 'ep_weighting_scripts', EP_URL . 'dist/js/weighting.min.js', [ 'jquery' ], EP_VERSION, true );
			wp_enqueue_style( 'ep_weighting_styles', EP_URL . 'dist/css/weighting.min.css', [], EP_VERSION );
		}
	}

	/**
	 * Force autosuggest hash update
	 *
	 * @since 3.1.2
	 */
	public function force_autosuggest_hash_update() {
		// Do a non-blocking search query to force the autosuggest hash to update
		$url = add_query_arg( [ 's' => 'search test' ], home_url( '/' ) );

		wp_remote_get(
			$url,
			[
				'blocking' => false,
			]
		);
	}

	/**
	 * Dont weight by these taxonomies
	 *
	 * @since  3.1.2
	 * @return array
	 */
	public function get_blacklist_taxonomies() {
		return [
			'ep_custom_result',
			'post_format',
		];
	}

	/**
	 * Admin-init actions
	 *
	 * Sets up Settings API.
	 *
	 * @since 1.9
	 * @return void
	 */
	public function handle_settings() {

		// Save options for multisite.
		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK && isset( $_POST['ep_weighting'] ) ) {
			check_admin_referer( 'elasticpress-weighting-options' );

			update_site_option( 'ep_weighting', $this->prepare_weighting( $_POST['ep_weighting'] ) );
		} else {
			register_setting( 'elasticpress-weighting', 'ep_weighting', [ $this, 'prepare_weighting' ] );
		}

		if ( isset( $_POST['ep_weighting'] ) ) {
			add_action( 'shutdown', [ $this, 'force_autosuggest_hash_update' ] );
		}
	}

	/**
	 * Get default weighting
	 *
	 * @since  3.1.2
	 * @return array
	 */
	public function get_default_weighting() {
		/** Features Class @var Features $features */
		$features = Features::factory();

		/** Search Feature @var Feature\Search\Search $search */
		$search = $features->get_registered_feature( 'search' );

		$post_types = $search->get_searchable_post_types();

		$weighting = [];

		foreach ( $post_types as $post_type ) {
			$post_type_weighting = [
				'post_title'        => 1,
				'post_content'      => 1,
				'post_excerpt'      => 1,
				'post_author.login' => 1,
			];

			$public_taxonomies = get_taxonomies(
				[
					'public' => true,
				]
			);

			$post_type_taxonomies = get_object_taxonomies( $post_type );

			$taxonomies = array_intersect( $public_taxonomies, $post_type_taxonomies );

			if ( $taxonomies ) {
				$fields['taxonomies'] = [];

				foreach ( $taxonomies as $taxonomy ) {
					if ( in_array( $taxonomy, $this->get_blacklist_taxonomies(), true ) ) {
						continue;
					}

					$key             = "terms.{$taxonomy}.name";
					$taxonomy_object = get_taxonomy( $taxonomy );

					$post_type_weighting[ $key ] = 1;
				}
			}

			$weighting[ $post_type ] = $post_type_weighting;
		}

		return $weighting;
	}

	/**
	 * Returns a grouping of all the fields that support weighting for the post type
	 *
	 * @param string $post_type Post type
	 *
	 * @return array
	 */
	public function get_weightable_fields_for_post_type( $post_type ) {
		$fields = [
			'attributes' => [
				'post_title'        => esc_html__( 'Title', 'elasticpress' ),
				'post_content'      => esc_html__( 'Content', 'elasticpress' ),
				'post_excerpt'      => esc_html__( 'Excerpt', 'elasticpress' ),
				'post_author.login' => esc_html__( 'Author', 'elasticpress' ),
			],
		];

		$public_taxonomies = get_taxonomies(
			[
				'public' => true,
			]
		);

		$post_type_taxonomies = get_object_taxonomies( $post_type );

		$taxonomies = array_intersect( $public_taxonomies, $post_type_taxonomies );

		if ( $taxonomies ) {
			$fields['taxonomies'] = [];

			foreach ( $taxonomies as $taxonomy ) {
				if ( in_array( $taxonomy, $this->get_blacklist_taxonomies(), true ) ) {
					continue;
				}

				$key             = "terms.{$taxonomy}.name";
				$taxonomy_object = get_taxonomy( $taxonomy );

				$fields['taxonomies'][ $key ] = $taxonomy_object->labels->name;
			}
		}

		return apply_filters( 'ep_weighting_fields_for_post_type', $fields, $post_type );
	}

	/**
	 * Adds the submenu page for controlling weighting
	 */
	public function add_weighting_submenu_page() {
		add_submenu_page( 'elasticpress', esc_html__( 'Search Fields & Weighting', 'elasticpress' ), esc_html__( 'Search Fields & Weighting', 'elasticpress' ), 'manage_options', 'elasticpress-weighting', [ $this, 'render_settings_page' ] );
	}

	/**
	 * Renders the settings page that controls weighting
	 */
	public function render_settings_page() {
		$action = 'options.php';

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$action = '';
		}

		include EP_PATH . '/includes/partials/header.php'; ?>
		<div class="wrap">

			<h1><?php esc_html_e( 'Manage Search Fields & Weighting', 'elasticpress' ); ?></h1>
			<p><?php esc_html_e( 'Adding more weight to an item will mean it will have more presence during searches. Add more weight to the items that are more important and need more prominence during searches.', 'elasticpress' ); ?></p>
			<p><?php esc_html_e( 'For example, adding more weight to the title attribute will cause search matches on the post title to appear more prominently.', 'elasticpress' ); ?></p>

			<form action="<?php echo esc_attr( $action ); ?>" method="post" class="weighting-settings metabox-holder">
				<?php settings_fields( 'elasticpress-weighting' ); ?>
				<?php settings_errors(); ?>

				<?php
				/** Features Class @var Features $features */
				$features = Features::factory();

				/** Search Feature @var Feature\Search\Search $search */
				$search = $features->get_registered_feature( 'search' );

				$post_types = $search->get_searchable_post_types();

				if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
					$weighting = get_site_option( 'ep_weighting', false );
				} else {
					$weighting = get_option( 'ep_weighting', false );
				}

				$default_weighting = $this->get_default_weighting();

				foreach ( $post_types as $post_type ) :
					$fields           = $this->get_weightable_fields_for_post_type( $post_type );
					$post_type_object = get_post_type_object( $post_type );
					?>
					<div class="postbox">
						<h2 class="hndle"><?php echo esc_html( $post_type_object->labels->menu_name ); ?></h2>

						<?php if ( ! empty( $fields['attributes'] ) ) : ?>
							<div class="field-group">
								<h3><?php esc_html_e( 'Attributes', 'elasticpress' ); ?></h3>
								<div class="fields">
									<?php
									foreach ( $fields['attributes'] as $field_key => $field_label ) {
										$weight = $default_weighting[ $post_type ][ $field_key ];

										if ( false !== $weighting ) {
											$weight = 0;

											if ( isset( $weighting[ $post_type ][ $field_key ] ) ) {
												$weight = $weighting[ $post_type ][ $field_key ];
											}
										}

										$this->render_weighting_setting( $post_type, $field_key, $field_label, $weight );
									}
									?>
								</div>
							</div>
						<?php endif; ?>

						<?php if ( ! empty( $fields['taxonomies'] ) ) : ?>
							<div class="field-group">
								<h3><?php esc_html_e( 'Taxonomies', 'elasticpress' ); ?></h3>
								<div class="fields">
									<?php
									foreach ( $fields['taxonomies'] as $field_key => $field_label ) {
										$weight = $default_weighting[ $post_type ][ $field_key ];

										if ( false !== $weighting ) {
											$weight = 0;

											if ( isset( $weighting[ $post_type ][ $field_key ] ) ) {
												$weight = $weighting[ $post_type ][ $field_key ];
											}
										}

										$this->render_weighting_setting( $post_type, $field_key, $field_label, $weight );
									}
									?>
								</div>
							</div>
						<?php endif; ?>
					</div>
					<?php
				endforeach;

				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render weighting setting
	 *
	 * @param  string $post_type   Post type
	 * @param  string $field_key   Field key
	 * @param  string $field_label Field label
	 * @param  int    $weight      Weight value
	 * @since  3.1.2
	 */
	public function render_weighting_setting( $post_type, $field_key, $field_label, $weight ) {
		$range_disabled = '';
		?>
		<fieldset>
			<legend><?php echo esc_html( $field_label ); ?></legend>

			<p class="searchable">
				<input type="checkbox" <?php checked( ( $weight > 0 ) ); ?> id="<?php echo esc_attr( "{$post_type}-{$field_key}-enabled" ); ?>">
				<label for="<?php echo esc_attr( "{$post_type}-{$field_key}-enabled" ); ?>"><?php esc_html_e( 'Searchable', 'elasticpress' ); ?></label>
			</p>

			<p class="weighting">
				<label for="<?php echo esc_attr( "{$post_type}-{$field_key}-weight" ); ?>">
					<?php esc_html_e( 'Weight: ', 'elasticpress' ); ?>
					<span class="weighting-value">
						<?php echo esc_html( $weight ); ?>
					</span>
				</label>
				<input type="range" <?php disabled( empty( $weight ) ); ?> min="1" max="100" step="1" value="<?php echo esc_attr( $weight ); ?>" id="<?php echo esc_attr( "{$post_type}-{$field_key}-weight" ); ?>" name="ep_weighting[<?php echo esc_attr( $post_type ); ?>][<?php echo esc_attr( $field_key ); ?>]" <?php echo $range_disabled; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			</p>
		</fieldset>
		<?php
	}

	/**
	 * Prepare weighting for saving
	 *
	 * @param  int $weighting Array of weightings
	 * @since  3.1.2
	 * @return array
	 */
	public function prepare_weighting( $weighting ) {
		$prepared_weighting = [];

		if ( ! empty( $weighting ) ) {
			foreach ( $weighting as $post_type => $post_type_weighting ) {
				// This also ensures the string is safe, since this would return false otherwise
				if ( ! post_type_exists( $post_type ) ) {
					continue;
				}

				$prepared_weighting[ $post_type ] = [];

				foreach ( $post_type_weighting as $field => $weight ) {
					$prepared_weighting[ $post_type ][ sanitize_text_field( $field ) ] = (int) $weight;
				}
			}
		}

		return $prepared_weighting;
	}

	/**
	 * Iterates through arrays in the formatted args to find "fields" and injects weighting values
	 *
	 * @param array  $fieldset Current subset of formatted ES args
	 * @param array  $weights  Weight configuration
	 * @param string $post_type Post type
	 */
	public function recursively_inject_weights_to_fields( &$fieldset, $weights, $post_type ) {
		if ( ! is_array( $fieldset ) ) {
			return;
		}

		$weightable_fields = $this->get_weightable_fields_for_post_type( $post_type );

		if ( empty( $weightable_fields ) ) {
			return;
		}

		if ( is_array( $fieldset ) && isset( $fieldset['fields'] ) ) {
			foreach ( $fieldset['fields'] as $key => $field ) {
				if ( isset( $weightable_fields['attributes'][ $field ] ) || isset( $weightable_fields['taxonomies'][ $field ] ) ) {
					if ( empty( $weights[ $field ] ) || 0 === (int) $weights[ $field ] ) {
						unset( $fieldset['fields'][ $key ] );
					} else {
						$weights[ $field ] = (int) $weights[ $field ];

						$fieldset['fields'][ $key ] = $field;

						if ( $weights[ $field ] > 1 ) {
							$fieldset['fields'][ $key ] .= '^' . ( $weights[ $field ] - 1 );
						}
					}
				}
			}

			// Reindex the array
			$fieldset['fields'] = array_values( $fieldset['fields'] );
		} else {
			foreach ( $fieldset as &$field ) {
				$this->recursively_inject_weights_to_fields( $field, $weights, $post_type );
			}
		}
	}

	/**
	 * Adjusts the query for configured weighting values
	 *
	 * @param array $formatted_args Formatted ES args
	 * @param array $args           WP_Query args
	 *
	 * @return array Formatted ES args
	 */
	public function do_weighting( $formatted_args, $args ) {
		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$weighting = get_site_option( 'ep_weighting', false );
		} else {
			$weighting = get_option( 'ep_weighting', false );
		}

		if ( false === $weighting ) {
			$weighting = $this->get_default_weighting();
		}

		$weighting = apply_filters( 'ep_weighting_configuration_for_search', $weighting, $args );

		if ( ! is_admin() && ! empty( $args['s'] ) ) {
			/**
			 * This section splits up the single query clause for all post types into separate nested clauses (one for each post type)
			 * which then get combined into one result set. By having separate clauses for each post type, we can then
			 * weight fields such as post_title per post type so that we can have fine grained control over weights by post
			 * type, rather than globally on the query
			 */
			$new_query = [
				'bool' => [
					'should' => [],
				],
			];

			// grab the query and keep track of whether or not it is nested in a function score
			$function_score = isset( $formatted_args['function_score'] );
			$query          = $function_score ? $formatted_args['function_score']['query'] : $formatted_args;

			foreach ( (array) $args['post_type'] as $post_type ) {
				// Copy the query, so we can set specific weight values
				$current_query = $query;

				if ( empty( $weighting[ $post_type ] ) ) {
					$weighting[ $post_type ] = [];
				}

				$this->recursively_inject_weights_to_fields( $current_query, $weighting[ $post_type ], $post_type );

				$new_query['bool']['should'][] = [
					'bool' => [
						'must'   => [
							$current_query,
						],
						'filter' => [
							[
								'match' => [
									'post_type.raw' => $post_type,
								],
							],
						],
					],
				];
			}

			// put the new query back in the correct location
			if ( $function_score ) {
				$formatted_args['function_score']['query'] = $new_query;
			} else {
				$formatted_args = $new_query;
			}
		}

		return $formatted_args;
	}
}
