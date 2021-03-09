import { debounce } from './utils/helpers';

const facetTerms = document.querySelector('.widget_ep-facet .terms');

/**
 * Filters the facets to match the input search term when
 * the number of terms exceeds the threshold determined
 * by the ep_facet_search_threshold filter
 *
 * @param {event} event - keyup
 */
const handleFacetSearch = (event) => {
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
const facetSearchInput = document.querySelector('.widget_ep-facet .facet-search');

if (facetSearchInput) {
	facetSearchInput.addEventListener(
		'keyup',
		debounce((event) => {
			if (event.keyCode === 13) {
				return;
			}

			handleFacetSearch(event);
		}, 200),
	);
}
