/**
 * WordPress dependencies.
 */
import { createPortal, render } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { ApiSearchProvider } from '../api-search';
import { apiEndpoint, apiHost, argsSchema, paramPrefix } from './config';
import Modal from './apps/modal';
import PostTypeFacet from './components/facets/post-type-facet';
import SearchTermFacet from './components/facets/search-term-facet';
import TaxonomyTermsFacet from './components/facets/taxonomy-terms-facet';
import Results from './components/layout/results';

/**
 * Initialize Instant Results.
 */
const init = () => {
	const useBlocks = true;
	const el = document.getElementById('ep-instant-results');

	if (useBlocks) {
		const facetEl = document.getElementById('ep-facet-block');
		const resultsEl = document.getElementById('ep-results-block');
		const postTypeEl = document.getElementById('ep-post-type-block');
		const searchEl = document.getElementById('ep-search-block');

		render(
			<ApiSearchProvider
				apiEndpoint={apiEndpoint}
				apiHost={apiHost}
				argsSchema={argsSchema}
				defaultIsOn
				paramPrefix={paramPrefix}
			>
				{facetEl
					? createPortal(
							<TaxonomyTermsFacet
								defaultIsOpen
								label="Category"
								name="tax-category"
								postTypes={[]}
							/>,
							facetEl,
					  )
					: null}
				{searchEl ? createPortal(<SearchTermFacet />, searchEl) : null}
				{postTypeEl
					? createPortal(<PostTypeFacet defaultIsOpen label="Type" />, postTypeEl)
					: null}
				{resultsEl ? createPortal(<Results />, resultsEl) : null}
			</ApiSearchProvider>,
			el,
		);
	} else {
		render(
			<ApiSearchProvider
				apiEndpoint={apiEndpoint}
				apiHost={apiHost}
				argsSchema={argsSchema}
				paramPrefix={paramPrefix}
			>
				<Modal />
			</ApiSearchProvider>,
			el,
		);
	}
};

window.addEventListener('DOMContentLoaded', init);
