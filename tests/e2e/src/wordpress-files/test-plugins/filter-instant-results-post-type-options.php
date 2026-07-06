<?php
/**
 * Plugin Name: Filter Instant Results Post Type Options
 * Description: Filters the Instant Results post type options for test purposes.
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
				const filterPostTypeOptions = (options) => {
					return options.map(function(option) {
						return Object.assign({}, option, {
							label: option.label + ' (filtered)',
						});
					});
				};

				wp.hooks.addFilter('ep.InstantResults.filter.postType.options', 'ep-test', filterPostTypeOptions);
			});
		</script>
		<?php
	}
);
