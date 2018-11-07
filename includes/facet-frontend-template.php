<?php

?>
	<div class="terms <?php if ( count( $terms_by_slug ) > $search_threshold ) : ?>searchable<?php endif; ?>">
		<?php if ( count( $terms_by_slug ) > $search_threshold ) : ?>
			<input class="facet-search" type="search" placeholder="<?php printf( esc_html__( 'Search %s', 'elasticpress' ), esc_attr( $taxonomy_object->labels->name ) ); ?>">
		<?php endif; ?>

		<div class="inner">
			<?php if ( ! empty( $selected_filters['taxonomies'][ $taxonomy ] ) ) : ?>
				<?php foreach ( $selected_filters['taxonomies'][ $taxonomy ]['terms'] as $term_slug => $value ) :
					if ( ! empty( $outputted_terms[ $term_slug ] ) ) {
						continue;
					}

					$term = $terms_by_slug[ $term_slug ];

					if ( empty( $term->parent ) && empty( $term->children ) ) {
						$outputted_terms[ $term_slug ] = $term;
						$new_filters = $selected_filters;

						if ( ! empty( $new_filters['taxonomies'][ $taxonomy ] ) && ! empty( $new_filters['taxonomies'][ $taxonomy ]['terms'][ $term_slug ] ) ) {
							unset( $new_filters['taxonomies'][ $taxonomy ]['terms'][ $term_slug ] );
						}
						?>
						<div class="term selected level-<?php echo (int) $term->level; ?>" data-term-name="<?php echo esc_attr( strtolower( $term->name ) ); ?>" data-term-slug="<?php echo esc_attr( strtolower( $term_slug ) ); ?>">
							<a href="<?php echo esc_attr( ep_facets_build_query_url( $new_filters ) ); ?>">
								<input type="checkbox" checked>
								<?php echo esc_html( $term->name ); ?>
							</a>
						</div>
						<?php
					} else {
						/**
						 * This code is so that when we encounter a selected child/parent term, we push it's whole branch
						 * to the top. Very very painful.
						 */
						$top_of_tree = $term;
						$i = 0;

						/**
						 * Get top of tree
						 */
						while ( true && $i < 10 ) {
							if ( ! empty( $term->parent_slug ) ) {
								$top_of_tree = $terms_by_slug[ $term->parent_slug ];
							} else {
								break;
							}

							$i++;
						}

						$flat_ordered_terms = array();

						$flat_ordered_terms[] = $top_of_tree;

						$to_process = $this->_order_by_selected( $top_of_tree->children, $selected_filters['taxonomies'][ $taxonomy ]['terms'] );

						while ( ! empty( $to_process ) ) {
							$term = array_shift( $to_process );

							$flat_ordered_terms[] = $term;

							if ( ! empty( $term->children ) ) {
								$to_process = array_merge( $this->_order_by_selected( $term->children, $selected_filters['taxonomies'][ $taxonomy ]['terms'] ), $to_process );
							}
						}

						foreach ( $flat_ordered_terms as $term ) {
							$selected = ! empty( $selected_filters['taxonomies'][ $taxonomy ]['terms'][ $term->slug ] );
							$outputted_terms[ $term->slug ] = $term;
							$new_filters = $selected_filters;

							if ( $selected ) {
								if ( ! empty( $new_filters['taxonomies'][ $taxonomy ] ) && ! empty( $new_filters['taxonomies'][ $taxonomy ]['terms'][ $term->slug ] ) ) {
									unset( $new_filters['taxonomies'][ $taxonomy ]['terms'][ $term->slug ] );
								}
							} else {
								if ( empty( $new_filters['taxonomies'][ $taxonomy ] ) ) {
									$new_filters['taxonomies'][ $taxonomy ] = array(
										'terms' => array(),
									);
								}

								$new_filters['taxonomies'][ $taxonomy ]['terms'][ $term->slug ] = true;
							}
							?>
							<div class="term <?php if ( empty( $term->count ) ) : ?>empty-term<?php endif; ?> <?php if ( $selected ) : ?>selected<?php endif; ?> level-<?php echo (int) $term->level; ?>" data-term-name="<?php echo esc_attr( strtolower( $term->name ) ); ?>" data-term-slug="<?php echo esc_attr( strtolower( $term->slug ) ); ?>">
								<a href="<?php echo esc_attr( ep_facets_build_query_url( $new_filters ) ); ?>">
									<input type="checkbox" <?php if ( $selected ) : ?>checked<?php endif; ?>>
									<?php echo esc_html( $term->name ); ?>
								</a>
							</div>
							<?php
						}
					}
				endforeach ; ?>
			<?php endif; ?>

			<?php foreach ( $terms as $term ) :
				if ( ! empty( $outputted_terms[ $term->slug ] ) ) {
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
