import { debounce } from './utils/helpers';

/**
 * Filters the facets to match the input search term when
 * the number of terms exceeds the threshold determined
 * by the ep_facet_search_threshold filter
 *
 * @param {event} event - keyup
<<<<<<< HEAD
<<<<<<< HEAD
 * @param {Node} facetTerms - terms node
=======
 * @param facetTerms
>>>>>>> d7acb690 (Update facets.js)
=======
 * @param {Node} facetTerms - terms node
>>>>>>> c12f3d42 (Add facetTerms type and description)
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
const facets = document.querySelectorAll('.widget_ep-facet');
<<<<<<< HEAD

facets.forEach((facet) => {
	const facetSearchInput = facet.querySelector('.facet-search');
	const facetTerms = facet.querySelector('.terms');

	if (!facetSearchInput) {
		return;
	}

=======

facets.forEach((facet) => {
	const facetSearchInput = facet.querySelector('.facet-search');
	const facetTerms = facet.querySelector('.terms');
<<<<<<< HEAD
	
<<<<<<< HEAD
>>>>>>> 36143e7b (Support multiple Facets)
=======
	if(!facetSearchInput) {
		return;
	}
	
>>>>>>> 61d3fb08 (Update facets.js)
=======

	if (!facetSearchInput) {
		return;
	}

>>>>>>> d7acb690 (Update facets.js)
	facet.querySelector('.facet-search').addEventListener(
		'keyup',
		debounce((event) => {
			if (event.keyCode === 13) {
				return;
			}

			handleFacetSearch(event, facetTerms);
<<<<<<< HEAD
<<<<<<< HEAD
		}, 200),
	);
=======
		}, 200)
>>>>>>> 36143e7b (Support multiple Facets)
=======
		}, 200),
	);
>>>>>>> d7acb690 (Update facets.js)
});
