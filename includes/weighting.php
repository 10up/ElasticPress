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
	add_action( 'ep_search_fields', __NAMESPACE__ . '\inject_optional_search_fields' );
}

/**
 * Returns a grouping of all the fields that support weighting
 *
 * @return array
 */
function get_weightable_post_fields() {
	$fields = array(
		'post_attributes' => array(
			'label'    => 'Post Attributes',
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
		'taxonomies'      => array(
			'label'    => 'Taxonomies',
			'children' => array(
				'terms.post_tag.name' => array(
					'key'   => 'terms.post_tag.name',
					'label' => 'Tags',
				),
				'terms.category.name' => array(
					'key'   => 'terms.category.name',
					'label' => 'Categories',
				),
			),
		),
	);

	/** @var Features $features */
	$features = Features::factory();
	/** @var Feature $woo */
	$woo = $features->get_registered_feature( 'woocommerce' );

	if ( $woo->is_active() ) {
		// @todo add standard product category/tag taxonomies

		// Add attribute taxonomies
		if ( function_exists( 'wc_get_attribute_taxonomies' ) ) {
			$attribute_taxonomies = wc_get_attribute_taxonomies();

			if ( ! empty( $attribute_taxonomies ) ) {
				$fields['product_attributes'] = array(
					'label' => 'Product Attributes',
					'children' => array(),
				);

				foreach ( $attribute_taxonomies as $attribute_taxonomy ) {
					$tax_name = wc_attribute_taxonomy_name( $attribute_taxonomy->attribute_name );

					$fields['product_attributes']['children'][] = array(
						'key' => "terms.${tax_name}.name",
						'label' => $attribute_taxonomy->attribute_label,
						'optional' => true, // Indicates this field is optional for searching
					);
				}
			}
		}
	}


    // @todo get custom taxonomies

    return $fields;
}

/**
 * Adds the submenu page for controlling weighting
 */
function add_weighting_submenu_page() {
	add_submenu_page( 'elasticpress', __( 'Weighting', 'elasticpress' ), __( 'Weighting', 'elasticpress' ), 'manage_options', 'elasticpress-weighting', __NAMESPACE__ . '\render_settings_page' );
}

/**
 * Gets the current weighting values from the elasticpress.io service
 *
 * @return array
 */
function get_weighting_values() {
	/** @var \ElasticPress\Indexable\Post\Post $post */
	$post = Indexables::factory()->get( 'post' );
	$index = $post->get_index_name();

	/** @var \ElasticPress\Elasticsearch $es */
	$es = Elasticsearch::factory();

	$response = $es->remote_request( "${index}/weighting" );

	if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return new \WP_Error( "es-api-error", "Unable to retreive weighting values from ElasticPress.io service. Ensure the service is up and try again." );
	}

	$body = wp_remote_retrieve_body( $response );

	$json = json_decode( $body, true );

	if ( isset( $json['fields'] ) ) {
		return $json['fields'];
	}

	return array();
}

function get_optional_field_values() {
	return get_option( 'ep-optional-fields', array() );
}

function inject_optional_search_fields( $fields ) {
	$add_fields = get_optional_field_values();

	foreach ( $add_fields as $enabled_field ) {
		if ( ! in_array( $enabled_field, $fields ) ) {
			$fields[] = $enabled_field;
		}
	}

	return $fields;
}

/**
 * Renders the settings page that controls weighting
 */
function render_settings_page() {
	include EP_PATH . '/includes/partials/header.php'; ?>
    <div class="wrap">
		<h1><?php esc_html_e( 'Weighting Settings', 'elasticpress' ); ?></h1>
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

		$fields = get_weightable_post_fields();
		$current_values = get_weighting_values();
		$optional_values = get_optional_field_values();

		if ( is_wp_error( $current_values ) ) {
			?><p><?php echo esc_html( $current_values->get_error_message() ); ?></p><?php
			return;
		}
		?>
        <form action="<?php echo admin_url( 'admin-post.php' ); ?>" method="post" class="weighting-settings">
            <input type="hidden" name="action" value="ep-weighting">
			<?php wp_nonce_field( 'save-weighting', 'ep-weighting-nonce' ); ?>
			<?php foreach ( $fields as $field_group ) :
				render_settings_section( $field_group, $current_values, $optional_values );
			endforeach; ?>
            <input type="submit" class="button button-primary">
        </form>
    </div>
    <?php
}

/**
 * Recursively renders each settings section and its children
 *
 * @param array $field           Current field to render
 * @param array $current_values  Weighting values from elasticpress.io service
 * @param array $optional_fields Array of field_key => bool that indicates if a field is enabled
 */
function render_settings_section( $field, $current_values, $optional_fields ) {
	if ( isset( $field['children'] ) ) : ?>
        <div class="field-group">
            <strong><?php echo esc_html( $field['label'] ); ?></strong>
            <div class="fields">
                <?php foreach( $field['children'] as $child ) {
					render_settings_section( $child, $current_values, $optional_fields );
				} ?>
            </div>
        </div>
    <?php elseif ( isset( $field['key'] ) ) :
        $key = $field['key'];
		$value = isset( $current_values[ $key ] ) ? (int) $current_values[ $key ] : 0;
		$optional = isset( $field['optional'] ) && true === $field['optional'] ? true : false;
        ?>
        <div class="field">
            <label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
            <input type="range" min="0" max="10" step="1" value="<?php echo esc_attr( $value ); ?>" id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>">

			<?php if ( true === $optional ) : ?>
				<input type="checkbox" <?php checked( in_array( $key, $optional_fields ) ); ?> id="<?php echo esc_attr( $key ); ?>-optional" name="optional_fields[<?php echo esc_attr( $key ); ?>]">
				<label for="<?php echo esc_attr( $key ); ?>-optional">Make Searchable</label>
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

	$fields = get_weightable_post_fields();
	$final_fields = array();

	foreach( $fields as $field ) {
		recurse_fields_for_save( $field, $final_fields );
	}

	$to_submit = wp_json_encode( array( 'fields' => $final_fields ) );

	/** @var \ElasticPress\Indexable\Post\Post $post */
	$post = Indexables::factory()->get( 'post' );
	$index = $post->get_index_name();

	/** @var \ElasticPress\Elasticsearch $es */
	$es = Elasticsearch::factory();

	$response = $es->remote_request( "${index}/weighting", array( 'method' => 'POST', 'body' => $to_submit ) );
	$code = (int) wp_remote_retrieve_response_code( $response );


	// Save the optional field data
	if ( isset( $_POST['optional_fields'] ) && is_array( $_POST['optional_fields'] ) ) {
		$final_option = array();
		foreach ( $_POST['optional_fields'] as $optional_field => $field_enabled ) {
			if ( "on" === $field_enabled ) {
				$final_option[] =  sanitize_text_field( $optional_field );
			}
		}
		update_option( 'ep-optional-fields', $final_option );
	} else {
		delete_option( 'ep-optional-fields' );
	}

	$redirect_url = admin_url( 'admin.php?page=elasticpress-weighting' );

	// We should usually get back a 202, but any 2xx response code should be fine
	if ( 300 <= $code ) {
		// Error
		$redirect_url = add_query_arg( 'settings-updated', false, $redirect_url );
	} else {
		$redirect_url = add_query_arg( 'settings-updated', true, $redirect_url );
	}

	wp_safe_redirect( $redirect_url );
	exit();
}

/**
 * Recurses through the available weighting fields to fetch new values as part of the save routine
 *
 * @param array $field Current field to fetch values for
 * @param array $final_fields Final fields and their weights that will be sent to elasticpress.io
 */
function recurse_fields_for_save( $field, &$final_fields ) {
	if ( isset( $field['children'] ) ) {
		foreach ( $field['children'] as $child ) {
			recurse_fields_for_save( $child, $final_fields );
		}
	} else if ( isset( $field['key'] ) ) {
		$safe_field = str_replace( '.', '_', $field['key'] );
		if ( isset( $_POST[ $safe_field ] ) ) {
			$value = (int)$_POST[ $safe_field ];

			if ( $value > 0 ) {
				$final_fields[ $field['key'] ] = $value;
			}
		}
	}
}

