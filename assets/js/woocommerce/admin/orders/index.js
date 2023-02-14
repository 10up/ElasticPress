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
	authorization,
	dateFormat,
	statusLabels,
	timeFormat,
	requestIdBase,
} from './config';

/**
 * Initialize.
 */
const init = () => {
	const form = document.getElementById('posts-filter');
	const input = form.s;

	if (!input) {
		return;
	}

	const app = document.createElement('div');

	input.parentElement.appendChild(app);

	render(
		<ApiSearchProvider
			apiEndpoint={apiEndpoint}
			apiHost={apiHost}
			argsSchema={argsSchema}
			authorization={authorization}
			requestIdBase={requestIdBase}
			defaultIsOn
		>
			<App
				adminUrl={adminUrl}
				dateFormat={dateFormat}
				input={input}
				statusLabels={statusLabels}
				timeFormat={timeFormat}
			/>
		</ApiSearchProvider>,
		app,
	);
};

domReady(init);
