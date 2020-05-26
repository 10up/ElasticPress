import { debounce } from './utils/helpers';

/**
 * Filters the facets to match the input search term when
 * the number of terms exceeds the threshold determined
 * by the ep_facet_search_threshold filter
 *
 * @param {event} event - keyup
 * @param {Node} facetTerms - DOM node to search for matching terms
 */
const handleFacetSearch = (event, facetTerms) => {
	const { target } = event;
	const searchTerm = target.value.replace(/\s/g, '').toLowerCase();
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
const initListener = () => {
	const facetTerms = document.querySelector('.widget_ep-facet .terms');
	const facetSearchInput = document.querySelector('.widget_ep-facet .facet-search');
	const debounceFacetFilter = debounce(handleFacetSearch, 200);

	if (facetSearchInput) {
		facetSearchInput.addEventListener('keyup', (event) => {
			if (event.keyCode === 13) {
				return;
			}
			debounceFacetFilter(event, facetTerms);
		});
	}
};

document.addEventListener('DOMContentLoaded', initListener);
