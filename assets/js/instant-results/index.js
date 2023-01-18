/**
 * WordPress dependencies.
 */
import { render } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { ApiSearchProvider } from '../api-search';
import { apiEndpoint, apiHost, argsSchema, paramPrefix } from './config';
import Modal from './apps/modal';

/**
 * Initialize Instant Results.
 */
const init = () => {
	const el = document.getElementById('ep-instant-results');

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
};

window.addEventListener('DOMContentLoaded', init);
