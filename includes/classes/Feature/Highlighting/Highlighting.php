<?php
/**
 * Highlighting feature
 *
 * @package elasticpress
 */

namespace ElasticPress\Feature\Highlighting;

use ElasticPress\Feature as Feature;

/**
 * Documents feature class.
 */
class Highlighting extends Feature {

	/**
	 * Initialize feature setting it's config
	 *
	 * @since  VERSION
	 */
	public function __construct() {
		$this->slug = 'search-term-highlighting';

		$this->title = esc_html__( 'Search Term Highlighting', 'elasticpress' );

		$this->requires_install_reindex = false;

		parent::__construct();
    }

    /**
	 * Setup feature filters
	 *
	 * @since VERSION
	 */
	public function setup() {
        // TODO
        add_action( 'admin_menu', [ $this, 'add_term_highlighting_submenu_page' ], 15 );

        add_filter( 'ep_formatted_args', [ $this, 'add_search_highlight_tags' ], 10, 2 );
    }

    /**
	 * Output feature box summary
	 *
	 * @since  VERSION
	 */
	public function output_feature_box_summary() {
		?>
		<p><?php esc_html_e( 'Inserts tags to wrap search terms in results for custom styling.', 'elasticpress' ); ?></p>
		<?php
	}

	/**
	 * Output feature box long
     *
     * TODO: finish the description
	 *
	 * @since  VERSION
	 */
	public function output_feature_box_long() {
		?>
		<p><?php esc_html_e( 'Inserts tags to wrap search terms in results for custom styling. Plus some more config options....', 'elasticpress' ); ?></p>
		<?php
    }


    /**
	 * Adds the submenu page for controlling search term highlighting options
	 */
	public function add_term_highlighting_submenu_page() {
        // TODO: add settings page?
        // add_submenu_page( 'elasticpress', __( 'Search Term Highlighting', 'elasticpress' ), __( 'Search Term Highlighting', 'elasticpress' ), 'manage_options', 'search-term-highlighting', [ $this, 'render_settings_page' ] );
    }

    /**
	 * Renders the settings page that controls search term highlighting
	 */
	public function render_settings_page() {
		// TODO: render settings page?
	}


    /**
	 * Set default fields to highHilight
	 *
	 * @since  VERSION
     * @param array $formatted_args ep_formatted_args array
     * @param array $args WP_Query args
	 * @return array $formatted_args formatted args with search highlight tags
	 */
    public function add_search_highlight_tags( $formatted_args, $args ) {

		$fields_to_highlight = array();

		// this should inherit the already-defined search fields.
		// get the search fields as defined by weighting, etc.
		if ( !empty( $args['search_fields'] ) ) {
			$fields_to_highlight = $args['search_fields'];


		} else {
			// fallback to the fields pre-defined in the query
			$should_match = $formatted_args['query']['bool']['should'];

			// next, check for the the weighted fields, in case any are excluded.
			foreach( $should_match as $item ) {
				$fields = $item['multi_match']['fields'];
				foreach($fields as $field ) {
					array_push($fields_to_highlight, $field );
				}
			}

			$fields_to_highlight = array_unique( $fields_to_highlight );
		}

		// default tag
		$highlight_tag = 'mark';
		// default class
		$highlight_class = 'ep-highlight';

		// tags
		$opening_tag = '<'.$highlight_tag.' class="'. $highlight_class .'">';
		$closing_tag = '</'.$highlight_tag.'>';

        // only for search query
        if ( ! is_admin() && ! empty( $args['s'] ) ) {
            foreach ( $fields_to_highlight as $field ) {
                $formatted_args['highlight']['fields'][ $field ] = [
                    'pre_tags'  => [ $opening_tag ],
                    'post_tags' => [ $closing_tag ],
                    'type'      => 'plain',
                ];
            }
        }
        return $formatted_args;
    }
};
