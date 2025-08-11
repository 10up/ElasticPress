<?php
/**
 * Plugin Name: Filter Instant Results Taxonomy Terms
 * Description: Filters the Instant Results taxonomy terms for test purposes.
 * Version:     1.0.0
 * Author:      10up Inc.
 * License:     GPLv2 or later
 *
 * @package ElasticPress_Tests_E2e
 */

/**
 * Limit the Instant Results terms to only the "Markup" term.
 */
add_action(
	'wp_footer',
	function (): void {
		?>
		<script>
			document.addEventListener('DOMContentLoaded', function() {
				const filterCategoryTerms = (terms, taxonomyName) => {

					if (taxonomyName !== 'tax-category') {
						return terms;
					}

					// keep only Term Markup
					const filteredTerms = terms.filter(term => term.label === 'Markup');
					return filteredTerms;
				}

				wp.hooks.addFilter('ep.InstantResults.filter.taxonomy.terms', 'ep-test', filterCategoryTerms);
			});
		</script>
		<?php
	}
);
