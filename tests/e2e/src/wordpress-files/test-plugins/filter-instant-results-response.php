<?php
/**
 * Plugin Name: Filter Instant Results Response
 * Description: Filters the Instant Results search response for test purposes.
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
				const filterResponse = (response) => {
					if (response && response.hits) {
						response.hits.hits = [];
						response.hits.total = typeof response.hits.total === 'number' ? 0 : { value: 0 };
					}
					return response;
				};

				wp.hooks.addFilter('ep.InstantResults.response', 'ep-test', filterResponse);
			});
		</script>
		<?php
	}
);
