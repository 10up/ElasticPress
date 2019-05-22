<?php
/**
 * Weighting dashboard for ElasticPress
 *
 * @package elasticpress
 */

namespace ElasticPress\Weighting;

use ElasticPress\Elasticsearch as Elasticsearch;
use ElasticPress\Feature;
use ElasticPress\Features;
use ElasticPress\Indexables;
use function ElasticPress\Utils\is_epio;

/**
 * Sets up the weighting module
 */
function setup() {
	if ( ! is_epio() ) {
		return;
	}

	add_action( 'admin_menu', __NAMESPACE__ . '\add_weighting_submenu_page', 15 );
	add_action( 'admin_post_ep-weighting', __NAMESPACE__ . '\handle_save' );
	add_filter( 'ep_formatted_args', __NAMESPACE__ . '\do_weighting', 20, 2 ); // After date decay, etc are injected
}

/**
 * Returns a grouping of all the fields that support weighting for the post type
 *
 * @param string $post_type Post type
 *
 * @return array
 */
function get_weightable_fields_for_post_type( $post_type ) {
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

	$taxonomies = get_taxonomies(
		array(
			'public' => true,
			'object_type' => array( $post_type )
		)
	);

	if ( $taxonomies ) {
		$fields['taxonomies'] = array(
			'label'    => __( 'Taxonomies', 'elasticpress' ),
			'children' => array()
		);

		foreach ( $taxonomies as $taxonomy ) {
			$key = "terms.{$taxonomy}.name";
			$taxonomy_object = get_taxonomy( $taxonomy );

			$fields['taxonomies']['children'][ $key ] = array(
				'key' => $key,
				'label' => $taxonomy_object->labels->name,
				'optional' => true,
			);
		}
	}

    return $fields;
}

/**
 * Returns the current weighting configuration
 *
 * @return array
 */
function get_weighting_configuration() {
	return get_option( 'elasticpress_weighting', array() );
}

/**
 * Adds the submenu page for controlling weighting
 */
function add_weighting_submenu_page() {
	add_submenu_page( 'elasticpress', __( 'Search Fields & Weighting', 'elasticpress' ), __( 'Search Fields & Weighting', 'elasticpress' ), 'manage_options', 'elasticpress-weighting', __NAMESPACE__ . '\render_settings_page' );
}

/**
 * Renders the settings page that controls weighting
 */
function render_settings_page() {
	include EP_PATH . '/includes/partials/header.php'; ?>
    <div class="wrap">

		<h1><?php esc_html_e( 'Manage Search Fields & Weighting', 'elasticpress' ); ?></h1>
		<p>Adding more weight to an item will mean it will have more presence during searches. Add more weight to the items that are more important and need more prominence during searches.</p>

		<form action="<?php echo admin_url( 'admin-post.php' ); ?>" method="post" class="weighting-settings">
			<input type="hidden" name="action" value="ep-weighting">
			<?php wp_nonce_field( 'save-weighting', 'ep-weighting-nonce' ); ?>
			<?php
			if ( isset( $_GET['settings-updated'] ) ) :
				if ( $_GET['settings-updated'] ) : ?>
					<div class="notice notice-success is-dismissible">
						<p><?php _e( 'Changes Saved!', 'elasticpress' ); ?></p>
					</div>
				<?php else : ?>
					<div class="notice notice-error is-dismissible">
						<p><?php _e( 'An error occurred when saving!', 'elasticpress' ); ?></p>
					</div>
				<?php endif;
			endif;

			/** @var Features $features */
			$features = Features::factory();

			/** @var Feature\Search\Search $search */
			$search = $features->get_registered_feature( 'search' );

			$post_types = $search->get_searchable_post_types();

			$current_values = get_weighting_configuration();

			foreach ( $post_types as $post_type ) :
				$fields = get_weightable_fields_for_post_type( $post_type );
				$post_type_object = get_post_type_object( $post_type );
				?>
				<h2><?php echo esc_html( $post_type_object->labels->menu_name ); ?></h2>

				<?php foreach ( $fields as $field_group ) :
					render_settings_section( $post_type, $field_group, $current_values );
				endforeach;

			endforeach; ?>
			<input type="submit" class="button button-primary">
        </form>
    </div>
    <?php
}

/**
 * Recursively renders each settings section and its children
 *
 * @param string $post_type      Current post type we're rendering
 * @param array $field           Current field to render
 * @param array $current_values  Current stored weighting values
 */
function render_settings_section( $post_type, $field, $current_values ) {
	if ( isset( $field['children'] ) ) : ?>
        <div class="field-group">
            <strong><?php echo esc_html( $field['label'] ); ?></strong>
            <div class="fields">
                <?php foreach( $field['children'] as $child ) {
					render_settings_section( $post_type, $child, $current_values );
				} ?>
            </div>
        </div>
    <?php elseif ( isset( $field['key'] ) ) :
        $key = $field['key'];
		$weight = isset( $current_values[$post_type] ) && isset( $current_values[$post_type][ $key ] ) && isset( $current_values[$post_type][ $key ]['weight'] ) ? (int) $current_values[ $post_type ][ $key ]['weight'] : 0;
		$optional = isset( $field['optional'] ) && true === $field['optional'] ? true : false;
        ?>
        <div class="field">
            <label for="<?php echo esc_attr( "{$post_type}-{$key}-weight" ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
            <input type="range" min="0" max="10" step="1" value="<?php echo esc_attr( $weight ); ?>" id="<?php echo esc_attr( "{$post_type}-{$key}-weight" ); ?>" name="weighting[<?php echo esc_attr( $post_type ); ?>][<?php echo esc_attr( $key ); ?>][weight]">

			<?php if ( true === $optional ) :
				$enabled = (
					isset( $current_values[$post_type] ) &&
					isset( $current_values[$post_type][ $key ] ) &&
					isset( $current_values[$post_type][ $key ]['enabled'] )
				)
					? boolval( $current_values[ $post_type ][ $key ]['enabled'] ) : false;
				?>
				<input type="checkbox" value="on" <?php checked( $enabled ); ?> id="<?php echo esc_attr( "{$post_type}-{$key}-enabled" ); ?>" name="weighting[<?php echo esc_attr( $post_type ); ?>][<?php echo esc_attr( $key ); ?>][enabled]">
				<label for="<?php echo esc_attr( "{$post_type}-{$key}-enabled" ); ?>">Make Searchable</label>
			<?php else : ?>
				<input type="hidden" value="on" name="weighting[<?php echo esc_attr( $post_type ); ?>][<?php echo esc_attr( $key ); ?>][enabled]">
			<?php endif; ?>
        </div>
	<?php endif;
}

/**
 * Handles processing the new weighting values and saving them to the elasticpress.io service
 */
function handle_save() {
	if ( ! isset( $_POST['ep-weighting-nonce'] ) || ! wp_verify_nonce( $_POST['ep-weighting-nonce'], 'save-weighting' ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( ! isset( $_POST['weighting'] ) || empty( $_POST['weighting'] ) ) {
		// It should always be set unless something is wrong, so just move on
		return;
	}

	$final_config = array();

	foreach ( $_POST['weighting'] as $post_type => $post_type_weighting ) {
		// This also ensures the string is safe, since this would return false otherwise
		if ( ! post_type_exists( $post_type ) ) {
			continue;
		}

		$final_config[ $post_type ] = array();

		foreach ( $post_type_weighting as $weighting_field => $weighting_values ) {
			$final_config[ $post_type ][ sanitize_text_field( $weighting_field ) ] = array(
				'weight' => isset( $weighting_values['weight'] ) ? intval( $weighting_values['weight'] ) : 0,
				'enabled' => isset( $weighting_values['enabled'] ) && $weighting_values['enabled'] === "on" ? true : false,
			);
		}
	}

	update_option( 'elasticpress_weighting', $final_config );

	$redirect_url = admin_url( 'admin.php?page=elasticpress-weighting' );
	$redirect_url = add_query_arg( 'settings-updated', true, $redirect_url );

	wp_safe_redirect( $redirect_url );
	exit();
}

/**
 * Iterates through arrays in the formatted args to find "fields" and injects weighting values
 *
 * @param array $fieldset Current subset of formatted ES args
 * @param array $weights Weight configuration
 */
function recursively_inject_weights_to_fields( &$fieldset, $weights ) {
	if ( ! is_array( $fieldset ) ) {
		return;
	}

	// @todo handle enabled/disabled fields
	if ( is_array( $fieldset ) && isset( $fieldset['fields'] ) ) {
		foreach ( $fieldset['fields'] as $key => $field ) {
			if ( isset( $weights[ $field ] ) ) {
				$weight = $weights[ $field ]['weight'];
				$fieldset['fields'][ $key ] = "{$field}^{$weight}";
			}
		}
	} else {
		foreach ( $fieldset as &$field ) {
			recursively_inject_weights_to_fields( $field, $weights );
		}
	}
}

/**
 * Adjusts the query for configured weighting values
 *
 * @param array $formatted_args Formatted ES args
 * @param array $args WP_Query args
 *
 * @return array Formatted ES args
 */
function do_weighting( $formatted_args, $args ) {
	$weight_config = get_weighting_configuration();

	if ( ! empty( $args['s'] ) ) {
		/*
		 * This section splits up the single query clause for all post types into separate nested clauses (one for each post type)
		 * which then get combined into one result set. By having separate clauses for each post type, we can then
		 * weight fields such as post_title per post type so that we can have fine grained control over weights by post
		 * type, rather than globally on the query
		 */
		$new_query = array(
			'bool' => array(
				'should' => array()
			)
		);

		// grab the query and keep track of whether or not it is nested in a function score
		$function_score = isset( $formatted_args['query']['function_score'] );
		$query          = $function_score ? $formatted_args['query']['function_score']['query'] : $formatted_args['query'];

		foreach ( $args['post_type'] as $post_type ) {
			// Copy the query, so we can set specific weight values
			$current_query = $query;

			if ( isset( $weight_config[ $post_type ] ) ) {
				// Find all "fields" values and inject weights for the current post type
				recursively_inject_weights_to_fields( $current_query, $weight_config[ $post_type ] );
			}

			$new_query['bool']['should'][] = array(
				"bool" => array(
					"must" => array(
						$current_query
					),
					"filter" => array(
						array(
							"match" => array(
								"post_type.raw" => $post_type,
							)
						)
					),
				),
			);
		}

		// put the new query back in the correct location
		if ( $function_score ) {
			$formatted_args['query']['function_score']['query'] = $new_query;
		} else {
			$formatted_args['query'] = $new_query;
		}
	}

	return $formatted_args;
}
