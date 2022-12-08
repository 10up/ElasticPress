/**
 * WordPress dependencies.
 */
import { render } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { apiEndpoint, apiHost, argsSchema, paramPrefix } from './config';
import { getArgsFromUrlParams, ApiSearchProvider } from '../api-search';
import Modal from './components/modal';

/**
 * Render Instant Results as a modal.
 *
 * @param {object} defaultArgs Default search args.
 * @param {boolean} defaultIsOpen Whether the modal should be open.
 * @returns {void}
 */
const renderModal = (defaultArgs, defaultIsOpen) => {
	const el = document.getElementById('ep-instant-results');

	render(
		<ApiSearchProvider
			apiEndpoint={apiEndpoint}
			apiHost={apiHost}
			argsSchema={argsSchema}
			defaultArgs={defaultArgs}
			paramPrefix={paramPrefix}
		>
			<Modal defaultIsOpen={defaultIsOpen} />
		</ApiSearchProvider>,
		el,
	);
};

/**
 * Initialize Instant Results.
 */
const init = () => {
	const urlParams = new URLSearchParams(window.location.search);
	const defaultArgs = getArgsFromUrlParams(urlParams, argsSchema, paramPrefix, false);
	const defaultIsOpen = Object.keys(defaultArgs).length > 0;

	renderModal(defaultArgs, defaultIsOpen);
};

window.addEventListener('DOMContentLoaded', init);
