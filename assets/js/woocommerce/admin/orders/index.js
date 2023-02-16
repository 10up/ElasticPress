/**
 * WordPress dependencies.
 */
import { render, useEffect, useRef, useState, WPElement } from '@wordpress/element';

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
	credentialsApiUrl,
	credentialsNonce,
	requestIdBase,
} from './config';

/**
 * Order search provider component.
 *
 * Bundles several provider components with authentication handling.
 *
 * @param {object} props Component props.
 * @param {WPElement} props.children Component children.
 * @returns {WPElement}
 */
const AuthenticatedApiSearchProvider = ({ children }) => {
	/**
	 * State.
	 */
	const [credentials, setCredentials] = useState(null);

	/**
	 * Refs.
	 */
	const hasRefreshed = useRef(false);

	/**
	 * Refresh credentials on authentication errors.
	 *
	 * @returns {void}
	 */
	const onAuthError = () => {
		if (hasRefreshed.current) {
			setCredentials(null);
			return;
		}

		fetch(credentialsApiUrl, {
			headers: { 'X-WP-Nonce': credentialsNonce },
			method: 'POST',
		})
			.then((response) => response.text())
			.then(setCredentials);

		hasRefreshed.current = true;
	};

	/**
	 * Set credentials on initialization.
	 *
	 * @returns {void}
	 */
	const onInit = () => {
		fetch(credentialsApiUrl, {
			headers: { 'X-WP-Nonce': credentialsNonce },
		})
			.then((response) => response.text())
			.then(setCredentials);
	};

	/**
	 * Effects.
	 */
	useEffect(onInit, []);

	/**
	 * Render.
	 */
	return credentials ? (
		<ApiSearchProvider
			apiEndpoint={apiEndpoint}
			apiHost={apiHost}
			argsSchema={argsSchema}
			authorization={`Basic ${credentials}`}
			requestIdBase={requestIdBase}
			onAuthError={onAuthError}
		>
			{children}
		</ApiSearchProvider>
	) : null;
};

/**
 * Initialize.
 *
 * @returns {void}
 */
const init = async () => {
	const form = document.getElementById('posts-filter');
	const input = form.s;

	if (!input) {
		return;
	}

	const el = document.createElement('div');

	input.parentElement.appendChild(el);

	render(
		<AuthenticatedApiSearchProvider>
			<App adminUrl={adminUrl} input={input} />
		</AuthenticatedApiSearchProvider>,
		el,
	);
};

init();
