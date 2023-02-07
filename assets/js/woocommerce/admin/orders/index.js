/**
 * WordPress dependencies.
 */
import domReady from '@wordpress/dom-ready';
import { render } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { ApiSearchProvider } from '../../../api-search';
import App from './app';
import {
	adminUrl,
	apiEndpoint,
	apiHost,
	argsSchema,
	dateFormat,
	nonce,
	restUrl,
	statusLabels,
	timeFormat,
} from './config';
import { getAuthorization, getNewAuthorization } from './utilities';

/**
 * Initialize.
 *
 * @returns {void}
 */
const init = async () => {
	/**
	 * Create an element for the app to render into.
	 */
	const form = document.getElementById('posts-filter');
	const input = form.s;

	if (!input) {
		return;
	}

	/**
	 * Render the orders autosuggest app.
	 *
	 * @param {string} authorization Authorization header.
	 * @param {Function} onAuthError Failed authentication handler.
	 * @returns {void}
	 */
	const renderApp = (authorization, onAuthError) => {
		const el = document.createElement('div');

		input.parentElement.appendChild(el);

		render(
			<ApiSearchProvider
				apiEndpoint={apiEndpoint}
				apiHost={apiHost}
				argsSchema={argsSchema}
				authorization={authorization}
				onAuthError={onAuthError}
			>
				{authorization}
				<App
					adminUrl={adminUrl}
					dateFormat={dateFormat}
					input={input}
					statusLabels={statusLabels}
					timeFormat={timeFormat}
				/>
			</ApiSearchProvider>,
			el,
		);
	};

	/**
	 * Handle authentiation failures.
	 *
	 * Generates a new Authorization header and re-renders the app. The app is
	 * re-rendered without the authentication failure handler so that getting a
	 * new Authorization header is only attempted once.
	 *
	 * @returns {void}
	 */
	const onAuthError = async () => {
		const authorization = await getNewAuthorization(restUrl, nonce);

		renderApp(authorization);
	};

	/**
	 * Get an Authorization header and render the app.
	 */
	const authorization = await getAuthorization(restUrl, nonce);

	if (!authorization) {
		return;
	}

	renderApp(authorization, onAuthError);
};

domReady(init);
