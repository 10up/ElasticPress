/**
 * WordPress dependencies.
 */
import { render } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { argsSchema, paramPrefix } from './config';
import { getArgsFromUrlParams } from './functions';
import Modal from './components/modal';
import InstantResults from './provider';

/**
 * Render Instant Results as a modal.
 *
 * @param {object} defaultArgs Default search args.
 * @returns {void}
 */
const renderModal = (defaultArgs) => {
	const el = document.getElementById('ep-instant-results');
	const defaultIsOpen = defaultArgs && Object.keys(defaultArgs).length > 0;

	render(
		<InstantResults defaultArgs={defaultArgs}>
			<Modal defaultIsOpen={defaultIsOpen} />
		</InstantResults>,
		el,
	);
};

/**
 * Initialize Instant Results.
 */
const init = () => {
	const urlParams = new URLSearchParams(window.location.search);
	const urlArgs = getArgsFromUrlParams(urlParams, argsSchema, paramPrefix, false);

	renderModal(urlArgs);
};

window.addEventListener('DOMContentLoaded', init);
