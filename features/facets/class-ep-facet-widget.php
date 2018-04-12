<?php

class EP_Facet_Widget extends WP_Widget {
	/**
	 * Create widget
	 */
	public function __construct() {
		$options = array( 'description' => esc_html__( 'Add a facet to an archive or search results page.', 'elasticpress' ) );
		parent::__construct( 'ep-facet', esc_html__( 'ElasticPress - Facet', 'elasticpress' ), $options );
	}

	/**
	 * Output widget
	 *
	 * @param  array $args
	 * @param  array $instance
	 * @since 2.5
	 */
	public function widget( $args, $instance ) {
		global $wp_query;

		if ( $wp_query->get( 'ep_facet', false ) ) {
			if ( ! ( is_archive() || is_search() || ( is_home() && empty( $wp_query->get( 'page_id' ) ) ) ) ) {
				return;
			}
		}

		$es_success = ( ! empty( $wp_query->elasticsearch_success ) ) ? true : false;

		if ( ! $es_success ) {
			return;
		}

		if ( empty( $instance['facet'] ) ) {
			return;
		}

		$taxonomy = $instance['facet'];

		$selected_filters = ep_facets_get_selected();

		$match_type = ( ! empty( $instance['match_type'] ) ) ? $instance['match_type'] : 'all';

		/**
		 * Get all the terms so we know if we should output the widget
		 */
		$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => true, ) );

		/**
		 * No terms!
		 */
		if ( 0 === $terms ) {
			return;
		}

		$terms_by_slug = array();

		foreach ( $terms as $term ) {
			$terms_by_slug[ $term->slug ] = $term;

			if ( ! empty( $GLOBALS['ep_facet_aggs'][ $taxonomy ][ $term->slug ] ) ) {
				$term->count = $GLOBALS['ep_facet_aggs'][ $taxonomy ][ $term->slug ];
			} else {
				$term->count = 0;
			}
		}

		$terms = ep_get_term_tree( $terms, 'count', 'desc', true );

		echo $args['before_widget'];

		if ( ! empty( $instance['title'] ) ) {
			echo $args['before_title'] . esc_html( apply_filters( 'widget_title', $instance['title'] ) ) . $args['after_title'];
		}

		$taxonomy_object = get_taxonomy( $taxonomy );

		$search_threshold = apply_filters( 'ep_facet_search_threshold', 15, $taxonomy );
		?>

		<div class="terms <?php if ( count( $terms_by_slug ) > $search_threshold ) : ?>searchable<?php endif; ?>">
			<?php if ( count( $terms_by_slug ) > $search_threshold ) : ?>
				<input class="facet-search" type="search" placeholder="<?php printf( esc_html__( 'Search %s', 'elasticpress' ), esc_attr( $taxonomy_object->labels->name ) ); ?>">
			<?php endif; ?>

			<div class="inner">
				<?php if ( ! empty( $selected_filters['taxonomies'][ $taxonomy ] ) ) : ?>
					<?php foreach ( $selected_filters['taxonomies'][ $taxonomy ]['terms'] as $term_slug => $value ) :
						$term = $terms_by_slug[ $term_slug ];

						$new_filters = $selected_filters;

						if ( ! empty( $new_filters['taxonomies'][ $taxonomy ] ) && ! empty( $new_filters['taxonomies'][ $taxonomy ]['terms'][ $term_slug ] ) ) {
							unset( $new_filters['taxonomies'][ $taxonomy ]['terms'][ $term_slug ] );
						}
						?>
						<div class="term selected" data-term-name="<?php echo esc_attr( strtolower( $term->name ) ); ?>" data-term-slug="<?php echo esc_attr( strtolower( $term_slug ) ); ?>">
							<a href="<?php echo esc_attr( ep_facets_build_query_url( $new_filters ) ); ?>">
								<input type="checkbox" checked>
								<?php echo esc_html( $term->name ); ?>
							</a>
						</div>
					<?php endforeach ; ?>
				<?php endif; ?>

				<?php foreach ( $terms as $term ) :
					if ( ! empty( $selected_filters['taxonomies'][ $taxonomy ] ) && ! empty( $selected_filters['taxonomies'][ $taxonomy ]['terms'][ $term->slug ] ) ) {
						continue;
					}

					$new_filters = $selected_filters;

					if ( empty( $new_filters['taxonomies'][ $taxonomy ] ) ) {
						$new_filters['taxonomies'][ $taxonomy ] = array(
							'terms'      => array(),
						);
					}

					$new_filters['taxonomies'][ $taxonomy ]['terms'][ $term->slug ] = true;
					?>
					<div class="term <?php if ( empty( $term->count ) ) : ?>empty-term<?php endif; ?> level-<?php echo (int) $term->level; ?>" data-term-name="<?php echo esc_attr( strtolower( $term->name ) ); ?>" data-term-slug="<?php echo esc_attr( strtolower( $term->slug ) ); ?>">
						<a <?php if ( ! empty( $term->count ) ) : ?>href="<?php echo esc_attr( ep_facets_build_query_url( $new_filters ) ); ?>"<?php endif; ?>>
							<input type="checkbox">
							<?php echo esc_html( $term->name ); ?>
						</a>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php

		echo $args['after_widget'];
	}

	/**
	 * Output widget form
	 *
	 * @param  array $instance
	 * @since 2.5
	 */
	public function form( $instance ) {
		$dashboard_url = admin_url( 'admin.php?page=elasticpress' );

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$dashboard_url = network_admin_url( 'network/admin.php?page=elasticpress' );
		}

		$feature  = ep_get_registered_feature( 'facets' );
		$settings = array();

		if ( $feature ) {
			$settings = $feature->get_settings();
		}

		$settings = wp_parse_args( $settings, array(
			'match_type' => 'all',
		) );

		$set = esc_html__( 'all', 'elasticpress' );
		$not_set = esc_html__( 'any', 'elasticpress' );

		if ( 'any' === $settings['match_type'] ) {
			$set = esc_html__( 'any', 'elasticpress' );
			$not_set = esc_html__( 'all', 'elasticpress' );
		}

		$title = ( ! empty( $instance['title'] ) ) ? $instance['title'] : '';
		$facet = ( ! empty( $instance['facet'] ) ) ? $instance['facet'] : '';

		$taxonomies = get_taxonomies( array( 'public' => true ), 'object' );
		?>
		<div class="widget-ep-facet">
			<p>
				<label for="<?php echo $this->get_field_id( 'title' ); ?>">
					<?php esc_html_e( 'Title:', 'elasticpress' ); ?>
				</label>
				<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
			</p>

			<p>
				<label for="<?php echo $this->get_field_id( 'facet' ); ?>">
					<?php esc_html_e( 'Taxonomy:', 'elasticpress' ); ?>
				</label><br>

				<select id="<?php echo $this->get_field_id( 'facet' ); ?>" name="<?php echo $this->get_field_name( 'facet' ); ?>">
					<?php foreach ( $taxonomies as $slug => $taxonomy_object ) : ?>
						<option <?php selected( $facet, $taxonomy_object->name ); ?> value="<?php echo esc_attr( $taxonomy_object->name ); ?>"><?php echo esc_html( $taxonomy_object->labels->name ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>

			<p><?php echo wp_kses_post( sprintf( __( 'Faceting is set to match %s terms; you change to match %s terms in <a href="%s">ElasticPress settings</a>.', 'elasticpress' ), $set, $not_set, esc_url( $dashboard_url ) ) ); ?></p>
		</div>

		<?php
	}

	/**
	 * Sanitize fields
	 *
	 * @param  array $new_instance
	 * @param  array $old_instance
	 * @since 2.5
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = array();

		$instance['title'] = sanitize_text_field( $new_instance['title'] );
		$instance['facet'] = sanitize_text_field( $new_instance['facet'] );

		return $instance;
	}
}
