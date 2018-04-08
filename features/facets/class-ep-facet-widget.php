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

		if ( empty( $instance['facets'] ) ) {
			return;
		}

		$selected_filters = ep_facets_get_selected();

		/**
		 * Get all the terms so we know if we should output the widget
		 */
		$all_facets_total = 0;

		foreach ( $instance['facets'] as $taxonomy ) {
			$tax_terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => true, ) );

			foreach ( $tax_terms as $term ) {
				$all_facets_total++;
			}
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
			$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => true, ) );

			if ( empty( $terms ) ) {
				continue;
			}

			$terms_by_slug = array();
			$term_slugs_by_count = array();

			foreach ( $terms as $term ) {
				$terms_by_slug[ $term->slug ] = $term;
				$term_slugs_by_count[ $term->slug ] = 0;

				if ( ! empty( $GLOBALS['ep_facet_aggs'][ $taxonomy ][ $term->slug ] ) ) {
					$term_slugs_by_count[ $term->slug ] = $GLOBALS['ep_facet_aggs'][ $taxonomy ][ $term->slug ];
				}
			}

			$taxonomy_object = get_taxonomy( $taxonomy );

			$search_threshold = apply_filters( 'ep_facet_search_threshold', 15, $taxonomy );

			arsort( $term_slugs_by_count );
			?>

			<div class="facet-title">
				<?php echo esc_html( $taxonomy_object->labels->name ); ?>
			</div>
			<div class="terms <?php if ( count( $terms_by_slug ) > $search_threshold ) : ?>searchable<?php endif; ?>">
				<?php if ( count( $terms_by_slug ) > $search_threshold ) : ?>
					<input class="facet-search" type="search" placeholder="<?php printf( esc_html__( 'Search %s', 'elasticpress' ), esc_attr( $taxonomy_object->labels->name ) ); ?>">
				<?php endif; ?>

				<div class="inner">
					<?php if ( ! empty( $selected_filters['taxonomies'][ $taxonomy ] ) ) : ?>
						<?php foreach ( $selected_filters['taxonomies'][ $taxonomy ] as $term_slug => $value ) : ?>
							<div class="term" data-term-name="<?php echo esc_attr( strtolower( $terms_by_slug[ $term_slug ]->name ) ); ?>" data-term-slug="<?php echo esc_attr( strtolower( $term_slug ) ); ?>">
								<input class="facet-input" name="filter_taxonomy_<?php echo esc_attr( $taxonomy ); ?>" value="<?php echo esc_attr( $term_slug ); ?>" id="filter-<?php echo esc_attr( $taxonomy ); ?>-<?php echo esc_attr( $term_slug ); ?>" checked type="checkbox">

								<label for="filter-<?php echo esc_attr( $taxonomy ); ?>-<?php echo esc_attr( $term_slug ); ?>">
									<?php echo esc_html( $terms_by_slug[ $term_slug ]->name ); ?> (<?php echo (int) $term_slugs_by_count[ $term_slug ] ?>)
								</label>
							</div>
						<?php endforeach ; ?>
					<?php endif; ?>

					<?php foreach ( $term_slugs_by_count as $term_slug => $count ) :
						if ( ! empty( $selected_filters['taxonomies'][ $taxonomy ] ) && ! empty( $selected_filters['taxonomies'][ $taxonomy ][ $term_slug ] ) ) {
							continue;
						}
						?>
						<div class="term <?php if ( empty( $count ) ) : ?>empty-term<?php endif; ?>" data-term-name="<?php echo esc_attr( strtolower( $terms_by_slug[ $term_slug ]->name ) ); ?>" data-term-slug="<?php echo esc_attr( strtolower( $term_slug ) ); ?>">
							<input class="facet-input" name="filter_taxonomy_<?php echo esc_attr( $taxonomy ); ?>" value="<?php echo esc_attr( $term_slug ); ?>" id="filter-<?php echo esc_attr( $taxonomy ); ?>-<?php echo esc_attr( $term_slug ); ?>" type="checkbox">

							<label for="filter-<?php echo esc_attr( $taxonomy ); ?>-<?php echo esc_attr( $term_slug ); ?>">
								<?php echo esc_html( $terms_by_slug[ $term_slug ]->name ); ?> (<?php echo (int) $count; ?>)
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
						<select id="<?php echo $this->get_field_id( 'facets' ); ?>-<?php echo (int) $i; ?>" name="<?php echo $this->get_field_name( 'facets' ); ?>[]">
							<option value="0"><?php esc_html_e( 'Choose Taxonomy', 'elasticpress' ); ?>
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
				items: '> p'
			} );
		</script>

		<a class="add-facet"><?php esc_html_e( 'Add Facet', 'elasticpress' ); ?></a>

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

		$new_instance['facets'] = array_unique( $new_instance['facets'] );
		$instance['facets'] = array();

		foreach ( $new_instance['facets'] as $facet ) {
			if ( ! empty( $facet ) ) {
				$instance['facets'][] = sanitize_text_field( $facet );
			}
		}

		return $instance;
	}
}
