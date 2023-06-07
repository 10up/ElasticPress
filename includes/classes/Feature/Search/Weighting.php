<?php
/**
 * Weighting dashboard for ElasticPress
 *
 * @package elasticpress
 */

namespace ElasticPress\Feature\Search;

use ElasticPress\Features;
use ElasticPress\Feature;
use ElasticPress\Indexable\Post\Post;
use ElasticPress\Utils;

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
		/**
		 * Filter to disable loading of Search weighting engine.
		 *
		 * @hook ep_disable_search_weighting
		 * @since 4.0
		 * @param bool Whether to disable search weighting engine. Defaults to false.
		 * @return bool Whether to disable search weighting engine.
		 */
		if ( apply_filters( 'ep_disable_search_weighting', false ) ) {
			return;
		}

		add_action( 'admin_menu', [ $this, 'add_weighting_submenu_page' ], 15 );
		add_action( 'admin_post_ep-weighting', [ $this, 'handle_save' ] );
		add_filter( 'ep_formatted_args', [ $this, 'do_weighting' ], 20, 2 ); // After date decay, etc are injected
		add_filter( 'ep_query_weighting_fields', [ $this, 'adjust_weight_for_cross_fields' ], 10, 5 );
	}

	/**
	 * Returns a grouping of all the fields that support weighting for the post type
	 *
	 * @param string $post_type Post type
	 *
	 * @return array
	 */
	public function get_weightable_fields_for_post_type( $post_type ) {
		$fields = array(
			'attributes' => array(
				'label'    => __( 'Attributes', 'elasticpress' ),
				'children' => array(
					'post_title'   => array(
						'key'   => 'post_title',
						'label' => 'Title',
					),
					'post_content' => array(
						'key'   => 'post_content',
						'label' => 'Content',
					),
					'post_excerpt' => array(
						'key'   => 'post_excerpt',
						'label' => 'Excerpt',
					),
					'author_name'  => array(
						'key'   => 'author_name',
						'label' => 'Author',
					),
				),
			),
		);

		$public_taxonomies = get_taxonomies(
			[
				'public' => true,
			]
		);

		$post_type_taxonomies = get_object_taxonomies( $post_type );

		$taxonomies = array_intersect( $public_taxonomies, $post_type_taxonomies );

		if ( $taxonomies ) {
			$fields['taxonomies'] = [
				'label'    => __( 'Taxonomies', 'elasticpress' ),
				'children' => [],
			];

			foreach ( $taxonomies as $taxonomy ) {
				$key             = "terms.{$taxonomy}.name";
				$taxonomy_object = get_taxonomy( $taxonomy );

				$fields['taxonomies']['children'][ $key ] = [
					'key'   => $key,
					'label' => $taxonomy_object->labels->name,
				];
			}
		}

		/**
		 * Filter weighting fields for a post type
		 *
		 * @hook ep_weighting_fields_for_post_type
		 * @param  {array} $fields Current weighting fields
		 * @param  {string} $post_type Current post type
		 * @return  {array} New fields
		 */
		return apply_filters( 'ep_weighting_fields_for_post_type', $fields, $post_type );
	}

	/**
	 * Returns default settings for any post type
	 *
	 * Defaults to title, content, excerpt, and author name enabled with zero weight
	 *
	 * @param string $post_type Post Type we need settings for
	 *
	 * @return array Defaults for post type
	 */
	public function get_post_type_default_settings( $post_type ) {
		$post_type_defaults = [
			'post_title'   => [
				'enabled' => true,
				'weight'  => 1,
			],
			'post_content' => [
				'enabled' => true,
				'weight'  => 1,
			],
			'post_excerpt' => [
				'enabled' => true,
				'weight'  => 1,
			],
			'author_name'  => [
				'enabled' => true,
				'weight'  => 1,
			],
		];

		$post_type_taxonomies = get_object_taxonomies( $post_type );

		/**
		 * Filter install status
		 *
		 * Previous behavior had post_tag and category enabled by default, so if this is supported on the post type
		 * we add them as enabled by default
		 *
		 * @hook ep_weighting_default_enabled_taxonomies
		 * @param  {array}  $enabled_taxonomies Taxonomies that should be enabled by default
		 * @param  {string} $post_type          Post type slug
		 * @return {array}  New taxonomies
		 * @since  3.6.5
		 */
		$enabled_by_default = apply_filters( 'ep_weighting_default_enabled_taxonomies', [ 'post_tag', 'category' ], $post_type );

		foreach ( $enabled_by_default as $default_tax ) {
			if ( in_array( $default_tax, $post_type_taxonomies, true ) ) {
				$post_type_defaults[ 'terms.' . $default_tax . '.name' ] = [
					'enabled' => true,
					'weight'  => 1,
				];
			}
		}

		/**
		 * Filter weighting defaults for post type
		 *
		 * @hook ep_weighting_default_post_type_weights
		 * @param  {array} $post_type_defaults Current weighting defaults
		 * @param  {string} $post_type Current post type
		 * @return  {array} New defaults
		 */
		return apply_filters( 'ep_weighting_default_post_type_weights', $post_type_defaults, $post_type );
	}

	/**
	 * Returns the current weighting configuration
	 *
	 * @return array
	 */
	public function get_weighting_configuration() {
		/**
		 * Filter weighting configuration
		 *
		 * @hook ep_weighting_configuration
		 * @param  {array} $config Current configuration
		 * @return  {array} New configuration
		 */
		return apply_filters( 'ep_weighting_configuration', get_option( 'elasticpress_weighting', [] ) );
	}

	/**
	 * Adds the submenu page for controlling weighting
	 */
	public function add_weighting_submenu_page() {
		add_submenu_page(
			'elasticpress',
			esc_html__( 'ElasticPress Search Fields & Weighting', 'elasticpress' ),
			esc_html__( 'Search Fields & Weighting', 'elasticpress' ),
			Utils\get_capability(),
			'elasticpress-weighting',
			[ $this, 'render_settings_page' ]
		);
	}

	/**
	 * Renders the settings page that controls weighting
	 */
	public function render_settings_page() {
		include EP_PATH . '/includes/partials/header.php'; ?>
		<div class="wrap">

			<h1><?php esc_html_e( 'Manage Search Fields & Weighting', 'elasticpress' ); ?></h1>
			<p><?php esc_html_e( 'Adding more weight to an item will mean it will have more presence during searches. Add more weight to the items that are more important and need more prominence during searches.', 'elasticpress' ); ?></p>
			<p><?php esc_html_e( 'For example, adding more weight to the title attribute will cause search matches on the post title to appear more prominently.', 'elasticpress' ); ?></p>

			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" class="weighting-settings metabox-holder">
				<input type="hidden" name="action" value="ep-weighting">
				<?php wp_nonce_field( 'save-weighting', 'ep-weighting-nonce' ); ?>
				<?php
				if ( isset( $_GET['settings-updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
					if ( sanitize_key( $_GET['settings-updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification
						?>
						<div class="notice notice-success is-dismissible">
							<p><?php esc_html_e( 'Changes Saved!', 'elasticpress' ); ?></p>
						</div>
					<?php else : ?>
						<div class="notice notice-error is-dismissible">
							<p><?php esc_html_e( 'An error occurred when saving!', 'elasticpress' ); ?></p>
						</div>
						<?php
					endif;
				endif;

				/** Features Class @var Features $features */
				$features = Features::factory();

				/** Search Feature @var Feature\Search\Search $search */
				$search = $features->get_registered_feature( 'search' );

				$post_types = $search->get_searchable_post_types();

				$current_values = $this->get_weighting_configuration();

				foreach ( $post_types as $post_type ) :
					$fields           = $this->get_weightable_fields_for_post_type( $post_type );
					$post_type_object = get_post_type_object( $post_type );
					?>
					<div class="postbox">
						<h2 class="hndle"><?php echo esc_html( $post_type_object->labels->menu_name ); ?></h2>

						<?php
						foreach ( $fields as $field_group ) :
							$this->render_settings_section( $post_type, $field_group, $current_values );
						endforeach;
						?>
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
	 * Recursively renders each settings section and its children
	 *
	 * @param string $post_type      Current post type we're rendering
	 * @param array  $field          Current field to render
	 * @param array  $current_values Current stored weighting values
	 */
	public function render_settings_section( $post_type, $field, $current_values ) {
		if ( isset( $field['children'] ) && ! empty( $field['children'] ) ) :
			?>
			<div class="field-group">
				<h3><?php echo esc_html( $field['label'] ); ?></h3>
				<div class="fields">
					<?php
					foreach ( $field['children'] as $child ) {
						$this->render_settings_section( $post_type, $child, $current_values );
					}
					?>
				</div>
			</div>
			<?php
		elseif ( isset( $field['key'] ) ) :
			$key = $field['key'];

			$post_type_settings = isset( $current_values[ $post_type ] ) ? $current_values[ $post_type ] : $this->get_post_type_default_settings( $post_type );

			$weight = isset( $post_type_settings[ $key ] ) && isset( $post_type_settings[ $key ]['weight'] ) ? (int) $post_type_settings[ $key ]['weight'] : 0;

			$range_disabled = '';

			$enabled = (
				isset( $post_type_settings ) &&
				isset( $post_type_settings[ $key ] ) &&
				isset( $post_type_settings[ $key ]['enabled'] )
			)
				? boolval( $post_type_settings[ $key ]['enabled'] ) : false;

			if ( ! $enabled ) {
				$range_disabled = 'disabled="disabled" ';
				$weight         = 0;
			}
			?>
			<fieldset>
				<legend><?php echo esc_html( $field['label'] ); ?></legend>

				<p class="searchable">
					<input type="checkbox" value="on" <?php checked( $enabled ); ?> id="<?php echo esc_attr( "{$post_type}-{$key}-enabled" ); ?>" name="weighting[<?php echo esc_attr( $post_type ); ?>][<?php echo esc_attr( $key ); ?>][enabled]">
					<label for="<?php echo esc_attr( "{$post_type}-{$key}-enabled" ); ?>"><?php esc_html_e( 'Searchable', 'elasticpress' ); ?></label>
				</p>

				<p class="weighting">
					<label for="<?php echo esc_attr( "{$post_type}-{$key}-weight" ); ?>">
						<?php esc_html_e( 'Weight: ', 'elasticpress' ); ?>
						<span class="weighting-value">
							<?php echo esc_html( $weight ); ?>
						</span>
					</label>
					<input type="range" min="1" max="100" step="1" value="<?php echo esc_attr( $weight ); ?>" id="<?php echo esc_attr( "{$post_type}-{$key}-weight" ); ?>" name="weighting[<?php echo esc_attr( $post_type ); ?>][<?php echo esc_attr( $key ); ?>][weight]" <?php echo $range_disabled; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				</p>
			</fieldset>
			<?php
		endif;
	}

	/**
	 * Handles processing the new weighting values and saving them to the elasticpress.io service
	 */
	public function handle_save() {
		if ( ! isset( $_POST['ep-weighting-nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['ep-weighting-nonce'] ), 'save-weighting' ) ) {
			return;
		}

		if ( ! current_user_can( Utils\get_capability() ) ) {
			return;
		}

		$this->save_weighting_configuration( $_POST );

		$redirect_url = admin_url( 'admin.php?page=elasticpress-weighting' );
		$redirect_url = add_query_arg( 'settings-updated', true, $redirect_url );

		$this->redirect( $redirect_url );
	}

	/**
	 * We need this method to test handle_save properly.
	 *
	 * @param string $redirect_url Redirect URL.
	 */
	protected function redirect( $redirect_url ) {
		// @codeCoverageIgnoreStart
		wp_safe_redirect( $redirect_url );
		exit();
		// @codeCoverageIgnoreEnd
	}

	/**
	 * Save weighting configuration for each searchable post_type
	 *
	 * @param array $settings weighting settings
	 *
	 * @return array final settings
	 * @since 3.4.1
	 */
	public function save_weighting_configuration( $settings ) {
		$new_config                = array();
		$previous_config_formatted = array();
		$current_config            = $this->get_weighting_configuration();

		foreach ( $current_config as $post_type => $post_type_weighting ) {
			// This also ensures the string is safe, since this would return false otherwise
			if ( ! post_type_exists( $post_type ) ) {
				continue;
			}

			// We need a way to know if fields have been explicitly set before, let's compare a previous state against $_POST['weighting']
			foreach ( $post_type_weighting as $weighting_field => $weighting_values ) {
				$previous_config_formatted[ $post_type ][ sanitize_text_field( $weighting_field ) ] = [
					'weight'  => isset( $settings['weighting'][ $post_type ][ $weighting_field ]['weight'] ) ? intval( $settings['weighting'][ $post_type ][ $weighting_field ]['weight'] ) : 0,
					'enabled' => isset( $settings['weighting'][ $post_type ][ $weighting_field ]['enabled'] ) && 'on' === $settings['weighting'][ $post_type ][ $weighting_field ]['enabled'] ? true : false,
				];
			}
		}

		$search     = Features::factory()->get_registered_feature( 'search' );
		$post_types = $search->get_searchable_post_types();

		foreach ( $post_types as $post_type ) {
			// This also ensures the string is safe, since this would return false otherwise
			if ( ! post_type_exists( $post_type ) ) {
				continue;
			}

			/** override default post_type settings while saving */
			$new_config[ $post_type ] = array();

			if ( isset( $settings['weighting'][ $post_type ] ) ) {
				foreach ( $settings['weighting'][ $post_type ] as $weighting_field => $weighting_values ) {
					$new_config[ $post_type ][ sanitize_text_field( $weighting_field ) ] = [
						'weight'  => isset( $weighting_values['weight'] ) ? intval( $weighting_values['weight'] ) : 0,
						'enabled' => isset( $weighting_values['enabled'] ) && 'on' === $weighting_values['enabled'] ? true : false,
					];
				}
			}
		}

		$final_config = array_replace_recursive( $previous_config_formatted, $new_config );

		update_option( 'elasticpress_weighting', $final_config );

		/**
		 * Fires right after the weighting configuration is saved.
		 *
		 * @since  3.5.x
		 * @hook ep_saved_weighting_configuration
		 */
		do_action( 'ep_saved_weighting_configuration' );

		return $final_config;
	}

	/**
	 * Iterates through arrays in the formatted args to find "fields" and injects weighting values
	 *
	 * @param array $fieldset Current subset of formatted ES args
	 * @param array $weights  Weight configuration
	 */
	public function recursively_inject_weights_to_fields( &$fieldset, $weights ) {
		if ( ! is_array( $fieldset ) ) {
			return;
		}

		if ( is_array( $fieldset ) && isset( $fieldset['fields'] ) ) {
			// Add any fields to the search that aren't already in there (weighting handled in next step)
			foreach ( $weights as $field => $settings ) {
				if ( ! in_array( $field, $fieldset['fields'], true ) ) {
					$fieldset['fields'][] = $field;
				}
			}

			foreach ( $fieldset['fields'] as $key => $field ) {
				if ( isset( $weights[ $field ] ) && false !== $weights[ $field ]['enabled'] ) {
					$weight = (int) $weights[ $field ]['weight'];

					if ( 0 !== $weight ) {
						if ( 'author_name' === $field ) {
							$field = 'post_author.display_name';
						}

						/**
						 * Filter fields and their weitghting as used in the Elasticsearch query.
						 *
						 * @hook ep_query_weighting_fields
						 * @param  {string} $weighted_field The field and its weight as used in the ES query.
						 * @param  {string} $field          Field name
						 * @param  {string} $weight         Weight value
						 * @param  {array}  $fieldset       Current subset of formatted ES args
						 * @param  {array}  $weights        Weight configuration
						 * @return  {array} New weighted field string
						 *
						 * @since  3.5.5
						 */
						$fieldset['fields'][ $key ] = apply_filters(
							'ep_query_weighting_fields',
							"{$field}^{$weight}",
							$field,
							$weight,
							$fieldset,
							$weights
						);
					}
				} else {
					// this handles removing post_author.login field added in Post::format_args() if author search field has being disabled
					if ( 'author_name' === $field ) {
						$author_key = array_search( 'post_author.login', $fieldset['fields'], true );
						if ( false !== $author_key ) {
							unset( $fieldset['fields'][ $author_key ] );
						}
					}
					unset( $fieldset['fields'][ $key ] );
				}
				// else: Leave anything that isn't explicitly disabled alone. Could have been added by search_fields, and if it is not present in the UI, we shouldn't touch it here

				// If fieldset has fuzziness enabled and fuzziness is disabled for this field, unset the field
				if ( isset( $fieldset['fuzziness'] ) && $fieldset['fuzziness'] && isset( $weights[ $field ]['fuzziness'] ) && false === $weights[ $field ]['fuzziness'] ) {
					unset( $fieldset['fields'][ $key ] );
				}
			}

			// Reindex the array
			$fieldset['fields'] = array_values( $fieldset['fields'] );
		} else {
			foreach ( $fieldset as &$field ) {
				$this->recursively_inject_weights_to_fields( $field, $weights );
			}
		}

		// Most likely to occur with the ordering results not being allowed in fuzzy, and weighting turning off fields for this otherwise
		if ( isset( $fieldset['fields'] ) && empty( $fieldset['fields'] ) ) {
			$fieldset = null;
		}
	}

	/**
	 * Determine if a post type has any fields enabled for search
	 *
	 * @param string $post_type  Post Type
	 * @param array  $args       WP_Query args
	 * @return boolean true/false depending on any fields enabled == true
	 */
	public function post_type_has_fields( $post_type, $args = [] ) {
		$weight_config = $this->get_weighting_configuration();

		/**
		 * Filter weighting configuration for search
		 *
		 * @hook ep_weighting_configuration_for_search
		 * @param  {array} $weight_config Current weight config
		 * @param  {array} $args WP Query arguments
		 * @return  {array} New configutation
		 */
		$weight_config = apply_filters( 'ep_weighting_configuration_for_search', $weight_config, $args );

		if ( ! isset( $weight_config[ $post_type ] ) ) {
			$weights = $this->get_post_type_default_settings( $post_type );
		} else {
			$weights = $weight_config[ $post_type ];
		}

		/**
		 * Filter fields considered in weighting
		 *
		 * Define keys which are irrelevant for this consideration, like `terms.ep_custom_result.name`.
		 *
		 * @hook ep_weighting_ignore_fields_in_consideration
		 * @param  {array} $fields Current fields
		 * @return  {array} New fields
		 */
		$ignore_keys = apply_filters( 'ep_weighting_ignore_fields_in_consideration', [ 'terms.ep_custom_result.name' => true ] );
		$fields      = array_diff_key( $weights, $ignore_keys );

		$found_enabled = false;
		foreach ( $fields as $field ) {
			if ( filter_var( $field['enabled'], FILTER_VALIDATE_BOOLEAN ) ) {
				$found_enabled = true;
				break;
			}
		}

		return $found_enabled;
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

		/**
		 * If search fields is set on the query, we should use those instead of the weighting, since the query was
		 * overridden by some custom code
		 */
		if ( isset( $args['search_fields'] ) && ! empty( $args['search_fields'] ) ) {
			return $formatted_args;
		}

		$weight_config = $this->get_weighting_configuration();

		/**
		 * Filter weighting configuration for search
		 *
		 * @hook ep_weighting_configuration_for_search
		 * @param  {array} $weight_config Current weight config
		 * @param  {array} $args WP Query arguments
		 * @return  {array} New configuration
		 */
		$weight_config = apply_filters( 'ep_weighting_configuration_for_search', $weight_config, $args );

		$should_do_weighting = Utils\is_integrated_request( 'weighting', [ 'public', 'rest' ] ) && ! empty( $args['s'] );

		/**
		 * Filter whether to enable weighting configuration
		 *
		 * @hook ep_enable_do_weighting
		 * @since 4.2.2
		 * @param  {bool}  Whether to enable weight config, defaults to true for search requests that are public or REST
		 * @param  {array} $weight_config Current weight config
		 * @param  {array} $args WP Query arguments
		 * @param  {array} $formatted_args Formatted ES arguments
		 * @return  {bool} Whether to use weighting configuration
		 */
		if ( apply_filters( 'ep_enable_do_weighting', $should_do_weighting, $weight_config, $args, $formatted_args ) ) {
			$formatted_args = $this->apply_weighting( $formatted_args, $args, $weight_config );
		}

		return $formatted_args;
	}

	/**
	 * Applies weighting based on ES args
	 *
	 * @since 4.2.2
	 * @param array $formatted_args Formatted ES args
	 * @param array $args WP_Query args
	 * @param array $weight_config Weight configuration to apply
	 *
	 * @return array $formatted_args Formatted ES args with weightings applied
	 */
	protected function apply_weighting( $formatted_args, $args, $weight_config ) {
		/*
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
		$function_score = isset( $formatted_args['query']['function_score'] );
		$query          = $function_score ? $formatted_args['query']['function_score']['query'] : $formatted_args['query'];

		foreach ( (array) $args['post_type'] as $post_type ) {
			if ( false === $this->post_type_has_fields( $post_type, $args ) ) {
				continue;
			}
			// Copy the query, so we can set specific weight values
			$current_query = $query;

			if ( isset( $weight_config[ $post_type ] ) ) {
				// Find all "fields" values and inject weights for the current post type
				$this->recursively_inject_weights_to_fields( $current_query, $weight_config[ $post_type ] );
			} else {
				// Use the default values for the post type
				$this->recursively_inject_weights_to_fields( $current_query, $this->get_post_type_default_settings( $post_type ) );
			}

			// Check for any segments with null fields from recursively_inject function and remove them
			if ( isset( $current_query['bool'] ) && isset( $current_query['bool']['should'] ) ) {
				foreach ( $current_query['bool']['should'] as $index => $current_bool_should ) {
					if ( isset( $current_bool_should['multi_match'] ) && null === $current_bool_should['multi_match'] ) {
						unset( $current_query['bool']['should'][ $index ] );
					}
				}
			}

			/**
			 * Filter weighting query for a post type
			 *
			 * @hook ep_weighted_query_for_post_type
			 * @param  {array} $query Weighting query
			 * @param  {string} $post_type Post type
			 * @param  {array} $args WP Query arguments
			 * @return  {array} New query
			 */
			$new_query['bool']['should'][] = apply_filters(
				'ep_weighted_query_for_post_type',
				[
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
				],
				$post_type,
				$args
			);
		}

		// put the new query back in the correct location
		if ( $function_score ) {
			$formatted_args['query']['function_score']['query'] = $new_query;
		} else {
			$formatted_args['query'] = $new_query;
		}

		/**
		 * Hook after weighting is added to Elasticsearch query
		 *
		 * @hook ep_weighting_added
		 * @param  {array} $formatted_args Elasticsearch query
		 * @param  {array} $args WP Query arguments
		 */
		do_action( 'ep_weighting_added', $formatted_args, $args );

		return $formatted_args;
	}

	/**
	 * Adjust weighting when the type is cross_fields, as it just works with weight = 1.
	 *
	 * @since 4.0.0
	 *
	 * @param string $weighted_field The field and its weight as used in the ES query.
	 * @param string $field          Field name
	 * @param string $weight         Weight value
	 * @param array  $fieldset       Current subset of formatted ES args
	 * @return array New weighted field string
	 */
	public function adjust_weight_for_cross_fields( $weighted_field, $field, $weight, $fieldset ) {
		if ( ! empty( $fieldset['type'] ) && 'cross_fields' === $fieldset['type'] ) {
			$weighted_field = "{$field}^1";
		}
		return $weighted_field;
	}
}
