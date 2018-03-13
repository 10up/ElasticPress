<?php

class EP_Facet_Widget extends WP_Widget {
	/**
	 * Create widget
	 */
	public function __construct() {
		$options = array( 'description' => esc_html__( 'Add a facet to an archive or search results page.', 'elasticpress' ) );
		parent::__construct( 'ep-facet', esc_html__( 'ElasticPress Facet', 'elasticpress' ), $options );
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

		if ( ! ( is_archive() || is_search() || ( is_home() && empty( $wp_query->get( 'page_id' ) ) ) ) ) {
			return;
		}

		if ( empty( $instance['facets'] ) ) {
			return;
		}

		if ( empty( $GLOBALS['ep_facet_aggs'] ) ) {
			return;
		}

		$selected_filters = ep_facets_get_selected();

		/**
		 * Get all the terms so we know if we should output the widget
		 */

		$tax_terms = array();
		$term_totals = array();
		$all_facets_total = 0;

		foreach ( $instance['facets'] as $taxonomy ) {
			if ( empty( $GLOBALS['ep_facet_aggs'][ $taxonomy ] ) ) {
				continue;
			}

			$tax_terms[ $taxonomy ] = get_terms( array( 'taxonomy' => $taxonomy ) );
			$term_total = 0;

			foreach ( $tax_terms[ $taxonomy ] as $term ) {
				if ( empty( $GLOBALS['ep_facet_aggs'][ $taxonomy ][ $term->slug ] ) ) {
					continue;
				}

				$term_total++;
				$all_facets_total++;
			}

			$term_totals[ $taxonomy ] = $term_total;
		}

		/**
		 * No terms!
		 */
		if ( 0 === $all_facets_total ) {
			return;
		}


		echo $args['before_widget'];

		if ( ! empty( $instance['title'] ) ) {
			echo $args['before_title'] . esc_html( apply_filters( 'widget_title', $instance['title'] ) ) . $args['after_title'];
		}

		?>
		<form method="get" class="ep-facet-form">
		<?php

		foreach ( $instance['facets'] as $taxonomy ) {
			if ( empty( $GLOBALS['ep_facet_aggs'][ $taxonomy ] ) ) {
				continue;
			}

			if ( empty( $tax_terms[ $taxonomy ] ) ) {
				continue;
			}

			$terms = get_terms( array( 'taxonomy' => $taxonomy ) );
			$taxonomy_object = get_taxonomy( $taxonomy );
			?>

			<div class="facet-title">
				<?php echo esc_html( $taxonomy_object->labels->name ); ?>
			</div>
			<div class="terms <?php if ( $term_totals[ $taxonomy ] > 2 ) : ?>searchable<?php endif; ?>">
				<?php if ( $term_totals[ $taxonomy ] > 2 ) : ?>
					<input class="facet-search" type="search" placeholder="<?php printf( esc_html__( 'Search %s', 'elasticpress' ), esc_attr( $taxonomy_object->labels->name ) ); ?>">
				<?php endif; ?>

				<div class="inner">
					<?php foreach ( $terms as $term ) :
						if ( empty( $GLOBALS['ep_facet_aggs'][ $taxonomy ][ $term->slug ] ) ) {
							continue;
						}

						$checked = false;

						if ( ! empty( $selected_filters['taxonomies'][ $taxonomy ] ) && ! empty( $selected_filters['taxonomies'][ $taxonomy ][ $term->slug ] ) ) {
							$checked = true;
						}
						?>
						<div class="term" data-term-name="<?php echo esc_attr( strtolower( $term->name ) ); ?>" data-term-slug="<?php echo esc_attr( strtolower( $term->slug ) ); ?>">
							<input class="facet-input" name="filter_taxonomy_<?php echo esc_attr( $taxonomy ); ?>" value="<?php echo esc_attr( $term->slug ); ?>" id="filter-<?php echo esc_attr( $taxonomy ); ?>-<?php echo esc_attr( $term->slug ); ?>" <?php checked( true, $checked ); ?> type="checkbox">

							<label for="filter-<?php echo esc_attr( $taxonomy ); ?>-<?php echo esc_attr( $term->slug ); ?>">
								<?php echo esc_html( $term->name ); ?> (<?php echo (int) $GLOBALS['ep_facet_aggs'][ $taxonomy ][ $term->slug ]; ?>)
							</label>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
			<?php
		}

		?>
		<input type="submit" value="<?php esc_html_e( 'Filter', 'elasticpress' ); ?>"> <input class="clear-facets" type="button" value="<?php esc_html_e( 'Clear', 'elasticpress' ); ?>">
		</form>
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
		$title = ( ! empty( $instance['title'] ) ) ? $instance['title'] : '';
		$facets = ( ! empty( $instance['facets'] ) ) ? $instance['facets'] : array();

		$taxonomies = get_taxonomies( array( 'public' => true ), 'object' );

		$id = microtime();
		?>

		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>">
				<?php esc_html_e( 'Title:', 'elasticpress' ); ?>
			</label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
		</p>

		<div class="facets facets-<?php echo (int) $id; ?>" data-field-name="<?php echo $this->get_field_name( 'facets' ); ?>[]">
			<?php if ( ! empty( $facets ) ) : ?>
				<?php foreach ( $facets as $i => $facet ) : ?>
					<p class="facet">
						<a class="order-facet" title="<?php esc_attr_e( 'Order Facets', 'elasticpress' ); ?>"></a>

						<label for="<?php echo $this->get_field_id( 'facets' ); ?>-<?php echo (int) $i; ?>">
							<?php esc_html_e( 'Taxonomy:', 'elasticpress' ); ?>
						</label>

						<select id="<?php echo $this->get_field_id( 'facets' ); ?>-<?php echo (int) $i; ?>" name="<?php echo $this->get_field_name( 'facets' ); ?>[]">
							<?php foreach ( $taxonomies as $slug => $taxonomy_object ) : ?>
								<option <?php selected( $facet, $taxonomy_object->name ); ?> value="<?php echo esc_attr( $taxonomy_object->name ); ?>"><?php echo esc_html( $taxonomy_object->labels->name ); ?></option>
							<?php endforeach; ?>
						</select>

						<a class="delete-facet" title="<?php esc_attr_e( 'Delete Facet', 'elasticpress' ); ?>"></a>
					</p>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>

		<script type="text/javascript">
			jQuery( '.facets-<?php echo (int) $id; ?>' ).sortable( {
				handle: '.order-facet',
				items: '> p'
			} );
		</script>

		<a class="add-facet"><?php esc_html_e( 'Add Facet', 'elasticpress' ); ?></a>

		<p><em><?php esc_html_e( 'ElasticPress does not yet support taxonomies with over 1000 terms.', 'elastipress' ); ?></em></p>
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
		$instance['taxonomy'] = sanitize_text_field( $new_instance['taxonomy'] );

		$instance['facets'] = $new_instance['facets'];

		if ( empty( $instance['facets'] ) ) {
			$instance['facets'] = array();
		}

		$instance['facets'] = array_map( 'sanitize_text_field', $instance['facets'] );

		return $instance;
	}
}
