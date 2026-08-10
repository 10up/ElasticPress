<?php
/**
 * Plugin Name: Filter Instant Results Facet Labels
 * Description: Filters the Instant Results facet labels for test purposes.
 * Version:     1.0.0
 * Author:      10up Inc.
 * License:     GPLv2 or later
 *
 * @package ElasticPress_Tests_E2e
 */

add_action(
	'wp_footer',
	function (): void {
		?>
		<script>
			document.addEventListener('DOMContentLoaded', function() {
				const filterFacetLabel = (label, name, type, postTypes) => {
					if (type === 'post_type') {
						return 'Filtered Type Label';
					}
					return label;
				};

				wp.hooks.addFilter('ep.InstantResults.filter.label', 'ep-test', filterFacetLabel);
			});
		</script>
		<?php
	}
);
