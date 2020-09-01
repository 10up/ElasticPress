import { debounce } from './utils/helpers';

const facetTerms = document.querySelector('.widget_ep-facet .terms');
const terms = facetTerms.querySelectorAll('.term');

/**
 * Filters the results when a facet is selected
 *
 * @param {event} event - click on term
 */
const filterResults = (event) => {
	if (event.target.nodeName.toLowerCase() === 'input') {
		const baseUrl = window.location.origin;
		const searchUrl = event.target.value;
		const queryUrl = `${baseUrl}${searchUrl}`;
		window.location.href = queryUrl;
	}
};

/**
 * Adds an event listener for each of the facets
 */
terms.forEach((term) => {
	term.addEventListener('click', filterResults);
});

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
