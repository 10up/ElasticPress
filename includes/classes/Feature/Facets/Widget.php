<?php
/**
 * Facets widget
 *
 * @package elasticpress
 */

namespace ElasticPress\Feature\Facets;

use \WP_Widget as WP_Widget;
use ElasticPress\Features as Features;
use ElasticPress\Utils as Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Facets widget class
 */
class Widget extends WP_Widget {
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
	 * @param  array $args Widget args
	 * @param  array $instance Instance settings
	 * @since 2.5
	 */
	public function widget( $args, $instance ) {
		global $wp_query;

		$feature = Features::factory()->get_registered_feature( 'facets' );

		if ( $wp_query->get( 'ep_facet', false ) ) {
			if ( ! $feature->is_facetable( $wp_query ) ) {
				return false;
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

		if ( ! is_search() ) {
			$post_type = $wp_query->get( 'post_type' );

			if ( empty( $post_type ) ) {
				$post_type = 'post';
			}

			if ( ! is_object_in_taxonomy( $post_type, $taxonomy ) ) {
				return;
			}
		}

		$selected_filters = $feature->get_selected();

		$match_type = ( ! empty( $instance['match_type'] ) ) ? $instance['match_type'] : 'all';

		/**
		 * Get all the terms so we know if we should output the widget
		 */
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
			)
		);

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

		/**
		 * Check to make sure all terms exist before proceeding
		 */
		if ( ! empty( $selected_filters['taxonomies'][ $taxonomy ] ) && ! empty( $selected_filters['taxonomies'][ $taxonomy ]['terms'] ) ) {
			foreach ( $selected_filters['taxonomies'][ $taxonomy ]['terms'] as $term_slug => $nothing ) {
				if ( empty( $terms_by_slug[ $term_slug ] ) ) {
					/**
					 * Term does not exist!
					 */
					return;
				}
			}
		}

		$orderby = isset( $instance['orderby'] ) ? $instance['orderby'] : 'count';
		$order   = isset( $instance['order'] ) ? $instance['order'] : 'count';

		$terms     = Utils\get_term_tree( $terms, $orderby, $order, true );
		$term_tree = Utils\get_term_tree( $terms, 'count', 'desc', false );

		$outputted_terms = array();

		echo wp_kses_post( $args['before_widget'] );

		if ( ! empty( $instance['title'] ) ) {
			echo wp_kses_post( $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'] );
		}

		$taxonomy_object = get_taxonomy( $taxonomy );

		/**
		 * Filter facet search threshold
		 *
		 * @hook ep_facet_search_threshold
		 * @param  {int} $search_threshold Search threshold
		 * @param  {string} $taxonomy Current taxonomy
		 * @return  {int} New threshold
		 */
		$search_threshold = apply_filters( 'ep_facet_search_threshold', 15, $taxonomy );
		?>

		<div class="terms <?php if ( count( $terms_by_slug ) > $search_threshold ) : ?>searchable<?php endif; ?>">
			<?php if ( count( $terms_by_slug ) > $search_threshold ) : ?>
				<?php // translators: Taxonomy Name ?>
				<input class="facet-search" type="search" placeholder="<?php printf( esc_html__( 'Search %s', 'elasticpress' ), esc_attr( $taxonomy_object->labels->name ) ); ?>">
				<?php
			endif;
			ob_start();
			?>

			<div class="inner">
				<?php if ( ! empty( $selected_filters['taxonomies'][ $taxonomy ] ) ) : ?>
					<?php
					foreach ( $selected_filters['taxonomies'][ $taxonomy ]['terms'] as $term_slug => $value ) :
						if ( ! empty( $outputted_terms[ $term_slug ] ) ) {
							continue;
						}

						$term = $terms_by_slug[ $term_slug ];

						if ( empty( $term->parent ) && empty( $term->children ) ) {
							$outputted_terms[ $term_slug ] = $term;
							$new_filters                   = $selected_filters;

							if ( ! empty( $new_filters['taxonomies'][ $taxonomy ] ) && ! empty( $new_filters['taxonomies'][ $taxonomy ]['terms'][ $term_slug ] ) ) {
								unset( $new_filters['taxonomies'][ $taxonomy ]['terms'][ $term_slug ] );
							}
							?>
							<div class="term selected level-<?php echo (int) $term->level; ?>" data-term-name="<?php echo esc_attr( strtolower( $term->name ) ); ?>" data-term-slug="<?php echo esc_attr( strtolower( $term_slug ) ); ?>">
								<a href="<?php echo esc_attr( $feature->build_query_url( $new_filters ) ); ?>" rel="nofollow">
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
							$i           = 0;

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

							$to_process = $this->order_by_selected( $top_of_tree->children, $selected_filters['taxonomies'][ $taxonomy ]['terms'] );

							while ( ! empty( $to_process ) ) {
								$term = array_shift( $to_process );

								$flat_ordered_terms[] = $term;

								if ( ! empty( $term->children ) ) {
									$to_process = array_merge( $this->order_by_selected( $term->children, $selected_filters['taxonomies'][ $taxonomy ]['terms'] ), $to_process );
								}
							}

							foreach ( $flat_ordered_terms as $term ) {
								$selected                       = ! empty( $selected_filters['taxonomies'][ $taxonomy ]['terms'][ $term->slug ] );
								$outputted_terms[ $term->slug ] = $term;
								$new_filters                    = $selected_filters;

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
									<a href="<?php echo esc_attr( $feature->build_query_url( $new_filters ) ); ?>" rel="nofollow">
										<input type="checkbox" <?php if ( $selected ) : ?>checked<?php endif; ?>>
										<?php echo esc_html( $term->name ); ?>
									</a>
								</div>
								<?php
							}
						}
					endforeach;
					?>
				<?php endif; ?>

				<?php
				foreach ( $terms as $term ) :
					if ( ! empty( $outputted_terms[ $term->slug ] ) ) {
						continue;
					}

					$new_filters = $selected_filters;

					if ( empty( $new_filters['taxonomies'][ $taxonomy ] ) ) {
						$new_filters['taxonomies'][ $taxonomy ] = array(
							'terms' => array(),
						);
					}

					$new_filters['taxonomies'][ $taxonomy ]['terms'][ $term->slug ] = true;
					?>
					<div class="term <?php if ( empty( $term->count ) ) : ?>empty-term<?php endif; ?> level-<?php echo (int) $term->level; ?>" data-term-name="<?php echo esc_attr( strtolower( $term->name ) ); ?>" data-term-slug="<?php echo esc_attr( strtolower( $term->slug ) ); ?>">
						<a <?php if ( ! empty( $term->count ) ) : ?>href="<?php echo esc_attr( $feature->build_query_url( $new_filters ) ); ?>"<?php endif; ?> rel="nofollow">
							<input type="checkbox">
							<?php echo esc_html( $term->name ); ?>
						</a>
					</div>
				<?php endforeach; ?>
			</div>
			<?php $facet_html = ob_get_clean(); ?>

			<?php
			// phpcs:disable
			/**
			 * Filter facet search widget HTML
			 *
			 * @hook ep_facet_search_widget
			 * @param  {string} $facet_html Widget HTML
			 * @param  {array} $selected_filters Selected filters
			 * @param  {array} $terms_by_slug Terms by slug
			 * @param  {array} $outputted_terms Outputted $terms
			 * @param  {string} $title Widget title
			 * @return  {string} New HTML
			 */
			echo apply_filters( 'ep_facet_search_widget', $facet_html, $selected_filters, $terms_by_slug, $outputted_terms, $instance['title'] );
			// phpcs:enable
			?>
		</div>
		<?php

		// Enqueue Script & Styles
		wp_enqueue_script( 'elasticpress-facets' );
		wp_enqueue_style( 'elasticpress-facets' );

		echo wp_kses_post( $args['after_widget'] );
	}

	/**
	 * Order terms putting selected at the top
	 *
	 * @param  array $terms Array of terms
	 * @param  array $selected_terms Selected terms
	 * @since  2.5
	 * @return array
	 */
	private function order_by_selected( $terms, $selected_terms ) {
		$ordered_terms = [];
		$terms_by_slug = [];

		foreach ( $terms as $term ) {
			$terms_by_slug[ $term->slug ] = $term;
		}

		ksort( $selected_terms );
		ksort( $terms_by_slug );

		foreach ( $selected_terms as $term_slug => $nothing ) {
			if ( ! empty( $terms_by_slug[ $term_slug ] ) ) {
				$ordered_terms[ $term_slug ] = $terms_by_slug[ $term_slug ];
			}
		}

		foreach ( $terms_by_slug as $term_slug => $term ) {
			if ( empty( $ordered_terms[ $term_slug ] ) ) {
				$ordered_terms[ $term_slug ] = $terms_by_slug[ $term_slug ];
			}
		}

		return array_values( $ordered_terms );
	}

	/**
	 * Output widget form
	 *
	 * @param  array $instance Instance settings
	 * @since 2.5
	 */
	public function form( $instance ) {
		$dashboard_url = admin_url( 'admin.php?page=elasticpress' );

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$dashboard_url = network_admin_url( 'admin.php?page=elasticpress' );
		}

		$feature  = Features::factory()->get_registered_feature( 'facets' );
		$settings = [];

		if ( $feature ) {
			$settings = $feature->get_settings();
		}

		$settings = wp_parse_args(
			$settings,
			array(
				'match_type' => 'all',
			)
		);

		$set     = esc_html__( 'all', 'elasticpress' );
		$not_set = esc_html__( 'any', 'elasticpress' );

		if ( 'any' === $settings['match_type'] ) {
			$set     = esc_html__( 'any', 'elasticpress' );
			$not_set = esc_html__( 'all', 'elasticpress' );
		}

		$title   = ( ! empty( $instance['title'] ) ) ? $instance['title'] : '';
		$facet   = ( ! empty( $instance['facet'] ) ) ? $instance['facet'] : '';
		$orderby = ( ! empty( $instance['orderby'] ) ) ? $instance['orderby'] : '';
		$order   = ( ! empty( $instance['order'] ) ) ? $instance['order'] : '';

		$taxonomies = get_taxonomies( array( 'public' => true ), 'object' );
		/**
		 * Filter taxonomies made available for faceting
		 *
		 * @hook ep_facet_include_taxonomies
		 * @param  {array} $taxonomies Taxonomies
		 * @return  {array} New taxonomies
		 */
		$taxonomies = apply_filters( 'ep_facet_include_taxonomies', $taxonomies );

		$orderby_options = [
			'count' => __( 'Count', 'elasticpress' ),
			'name'  => __( 'Term Name', 'elasticpress' ),
		];

		$order_options = [
			'desc' => __( 'Descending', 'elasticpress' ),
			'asc'  => __( 'Ascending', 'elasticpress' ),
		];

		?>
		<div class="widget-ep-facet">
			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">
					<?php esc_html_e( 'Title:', 'elasticpress' ); ?>
				</label>
				<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
			</p>

			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'facet' ) ); ?>">
					<?php esc_html_e( 'Taxonomy:', 'elasticpress' ); ?>
				</label><br>

				<select id="<?php echo esc_attr( $this->get_field_id( 'facet' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'facet' ) ); ?>">
					<?php foreach ( $taxonomies as $slug => $taxonomy_object ) : ?>
						<option <?php selected( $facet, $taxonomy_object->name ); ?> value="<?php echo esc_attr( $taxonomy_object->name ); ?>"><?php echo esc_html( $taxonomy_object->labels->name ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>

			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'orderby' ) ); ?>">
					<?php esc_html_e( 'Order Terms By:', 'elasticpress' ); ?>
				</label><br>

				<select id="<?php echo esc_attr( $this->get_field_id( 'orderby' ) ); ?>"
						name="<?php echo esc_attr( $this->get_field_name( 'orderby' ) ); ?>">
					<?php foreach ( $orderby_options as $name => $title ) : ?>
						<option <?php selected( $orderby, $name ); ?>
								value="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $title ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>

			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'order' ) ); ?>">
					<?php esc_html_e( 'Term Order:', 'elasticpress' ); ?>
				</label><br>

				<select id="<?php echo esc_attr( $this->get_field_id( 'order' ) ); ?>"
						name="<?php echo esc_attr( $this->get_field_name( 'order' ) ); ?>">
					<?php foreach ( $order_options as $name => $title ) : ?>
						<option <?php selected( $order, $name ); ?>
								value="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $title ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>

			<?php // translators: "all" or "any", depending on configuration values, 3: URL ?>
			<p><?php echo wp_kses_post( sprintf( __( 'Faceting will  filter out any content that is not tagged to all selected terms; change this to show <strong>%1$s</strong> content tagged to <strong>%2$s</strong> selected term in <a href="%3$s">ElasticPress settings</a>.', 'elasticpress' ), $set, $not_set, esc_url( $dashboard_url ) ) ); ?></p>
		</div>

		<?php
	}

	/**
	 * Sanitize fields
	 *
	 * @param  array $new_instance New instance settings
	 * @param  array $old_instance Old instance settings
	 * @since 2.5
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = [];

		$instance['title']   = sanitize_text_field( $new_instance['title'] );
		$instance['facet']   = sanitize_text_field( $new_instance['facet'] );
		$instance['orderby'] = sanitize_text_field( $new_instance['orderby'] );
		$instance['order']   = sanitize_text_field( $new_instance['order'] );

		return $instance;
	}
}
