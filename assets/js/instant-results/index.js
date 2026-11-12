/**
 * WordPress dependencies.
 */
import { createRoot, render } from '@wordpress/element';
import { doAction } from '@wordpress/hooks';

/**
 * Internal dependencies.
 */
import { ApiSearchProvider, useApiSearch } from '../api-search';
import { apiEndpoint, apiHost, argsSchema, paramPrefix, requestIdBase } from './config';
import Modal from './apps/modal';
import SearchTermFacet from './components/facets/search-term-facet';
import Toolbar from './components/layout/toolbar';
import ActiveConstraints from './components/tools/active-constraints';
import ClearConstraints from './components/tools/clear-constraints';
import SidebarToggle from './components/tools/sidebar-toggle';

/**
 * Expose the components and the useApiSearch hook.
 *
 * @action ep.InstantResults.ready
 * @since 5.4.0
 */
doAction('ep.InstantResults.ready', {
	components: {
		ActiveConstraints,
		ClearConstraints,
		SearchTermFacet,
		SidebarToggle,
		Toolbar,
	},
	useApiSearch,
});

/**
 * Initialize Instant Results.
 */
const init = () => {
	const el = document.getElementById('ep-instant-results');

	if (typeof createRoot === 'function') {
		const root = createRoot(el);
		root.render(
			<ApiSearchProvider
				apiEndpoint={apiEndpoint}
				apiHost={apiHost}
				argsSchema={argsSchema}
				paramPrefix={paramPrefix}
				requestIdBase={requestIdBase}
			>
				<Modal />
			</ApiSearchProvider>,
		);
	} else {
		render(
			<ApiSearchProvider
				apiEndpoint={apiEndpoint}
				apiHost={apiHost}
				argsSchema={argsSchema}
				paramPrefix={paramPrefix}
				requestIdBase={requestIdBase}
			>
				<Modal />
			</ApiSearchProvider>,
			el,
		);
	}
};

window.addEventListener('DOMContentLoaded', init);
