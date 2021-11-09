import { debounce } from './utils/helpers';

/**
 * Filters the facets to match the input search term when
 * the number of terms exceeds the threshold determined
 * by the ep_facet_search_threshold filter
 *
 * @param {event} event - keyup
 * @param {Node} facetTerms - terms node
 */
const handleFacetSearch = (event, facetTerms) => {
	const { target } = event;
	const searchTerm = target.value.toLowerCase();
	const terms = facetTerms.querySelectorAll('.term');

	terms.forEach((term) => {
		const slug = term.getAttribute('data-term-slug');
		const name = term.getAttribute('data-term-name');

		if (name.includes(searchTerm) || slug.includes(searchTerm)) {
			term.classList.remove('hide');
		} else {
			term.classList.add('hide');
		}
	});
};

/**
 * Filter facet choices to match the search field term
 */
const facets = document.querySelectorAll('.widget_ep-facet');

facets.forEach((facet) => {
	const facetSearchInput = facet.querySelector('.facet-search');

	if (!facetSearchInput) {
		return;
	}

	const facetTerms = facet.querySelector('.terms');

	facet.querySelector('.facet-search').addEventListener(
		'keyup',
		debounce((event) => {
			if (event.keyCode === 13) {
				return;
			}

			handleFacetSearch(event, facetTerms);
		}, 200),
	);
});
