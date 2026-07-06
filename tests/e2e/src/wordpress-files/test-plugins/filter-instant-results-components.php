<?php
/**
 * Plugin Name: Filter Instant Results Components
 * Description: Filters the Instant Results sort and did-you-mean components for test purposes.
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
				const el = wp.element.createElement;

				const filterSort = (sort) => {
					return el('div', { className: 'my-custom-sort' }, sort);
				};

				const filterDidYouMean = (didYouMean) => {
					return el('div', { className: 'my-custom-did-you-mean' }, didYouMean);
				};

				wp.hooks.addFilter('ep.InstantResults.component.sort', 'ep-test', filterSort);
				wp.hooks.addFilter('ep.InstantResults.component.didYouMean', 'ep-test', filterDidYouMean);
			});
		</script>
		<?php
	}
);
