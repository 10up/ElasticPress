<?php
/**
 * Plugin Name: Custom Search Form
 * Description: Custom search form for test purposes.
 * Version:     1.0.0
 * Author:      10up Inc.
 * License:     GPLv2 or later
 *
 * @package ElasticPress_Tests_E2e
 */

/**
 * Shortcode for custom search form with category dropdown and tag checkboxes.
 *
 * @return string HTML output for the search form.
 */
function ep_custom_search_form_shortcode() {
	ob_start();
	?>
	<form role="search" method="get" id="searchform" class="searchform" action="<?php echo esc_url( home_url( '/' ) ); ?>">
		<label class="screen-reader-text" for="s"><?php esc_html_e( 'Search for:', 'custom-search-form' ); ?></label>
		<input type="search" value="<?php echo esc_attr( get_search_query() ); ?>" name="s" id="s" placeholder="<?php esc_attr_e( 'Search…', 'custom-search-form' ); ?>" />
		<?php
		wp_dropdown_categories(
			[
				'show_option_all' => __( 'All Categories', 'custom-search-form' ),
				'name'            => 'cat',
			],
		);
		?>
		<?php
		$tag_terms = get_terms(
			[
				'taxonomy'   => 'post_tag',
				'hide_empty' => true,
			],
		);
		?>
		<fieldset class="search-tags-checkboxes">
			<legend><?php esc_html_e( 'Filter by Tags:', 'custom-search-form' ); ?></legend>
			<?php
			foreach ( $tag_terms as $term ) :
				?>
				<label>
					<input type="checkbox" name="custom_tag_id[]" value="<?php echo esc_attr( $term->term_id ); ?>" />
					<?php echo esc_html( $term->name ); ?>
				</label><br />
			<?php endforeach; ?>
		</fieldset>
		<input type="submit" id="searchsubmit" value="<?php esc_attr_e( 'Search', 'custom-search-form' ); ?>" />
	</form>
	<?php
	return ob_get_clean();
}
add_shortcode( 'ep_custom_search_form', 'ep_custom_search_form_shortcode' );

/**
 * Filter the Instant Results query variable map.
 */
add_action(
	'wp_footer',
	function (): void {
		?>
	<script>
		document.addEventListener('DOMContentLoaded', function() {
			wp.hooks.addFilter('ep.instantResults.queryVarMap', 'ep-test', (queryVarMap) => {
				queryVarMap['custom_tag_id'] = 'tax-post_tag';
				return queryVarMap;
			});
		});
	</script>
		<?php
	}
);
