<?php
/**
 * Plugin Name: Filter Instant Results Price Range
 * Description: Filters the Instant Results price range bounds for test purposes.
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
				const filterPriceRange = (range) => {
					return { min: 0, max: 999 };
				};

				wp.hooks.addFilter('ep.InstantResults.filter.priceRange.options', 'ep-test', filterPriceRange);
			});
		</script>
		<?php
	}
);
