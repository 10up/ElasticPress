/**
 * WordPress dependencies.
 */
import { createRoot, render, useEffect, useRef, useState, WPElement } from '@wordpress/element';

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
	dateFormat,
	requestIdBase,
	statusLabels,
	timeFormat,
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
	const form = document.querySelector('#posts-filter, #wc-orders-filter');
	const input = form.s;

	if (!input) {
		return;
	}

	/**
	 * Get the attributes from the search input so that we can assign them to
	 * the combobox input, for visual and functional continuity with the
	 * original search input.
	 */
	const props = Object.values(input.attributes).reduce(
		(props, attribute) => ({ ...props, [attribute.name]: attribute.value }),
		{},
	);

	/**
	 * Render our application in place of the search input.
	 */
	const el = document.createElement('div');

	el.setAttribute('id', 'ep-woocommerce-order-search');

	input.replaceWith(el);

	if (typeof createRoot === 'function') {
		const root = createRoot(el);

		root.render(
			<AuthenticatedApiSearchProvider>
				<App
					adminUrl={adminUrl}
					dateFormat={dateFormat}
					statusLabels={statusLabels}
					timeFormat={timeFormat}
					{...props}
				/>
			</AuthenticatedApiSearchProvider>,
		);
	} else {
		render(
			<AuthenticatedApiSearchProvider>
				<App
					adminUrl={adminUrl}
					dateFormat={dateFormat}
					statusLabels={statusLabels}
					timeFormat={timeFormat}
					{...props}
				/>
			</AuthenticatedApiSearchProvider>,
			el,
		);
	}
};

init();
