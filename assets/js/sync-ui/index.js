/**
 * WordPress dependencies.
 */
import { createRoot, render, WPElement } from '@wordpress/element';
import { SyncProvider } from '../sync';

/**
 * Internal dependencies.
 */
import {
	ajaxUrl,
	autoIndex,
	lastSyncDateTime,
	lastSyncFailed,
	indexMeta,
	isEpio,
	nonce,
} from './config';
import SettingsPage from './apps/settings-page';

/**
 * App component.
 *
 * @returns {WPElement} App component.
 */
const App = () => (
	<SyncProvider
		ajaxUrl={ajaxUrl}
		autoIndex={autoIndex}
		defaultLastSyncDateTime={lastSyncDateTime}
		defaultLastSyncFailed={lastSyncFailed}
		indexMeta={indexMeta}
		isEpio={isEpio}
		nonce={nonce}
	>
		<SettingsPage />
	</SyncProvider>
);

if (typeof createRoot === 'function') {
	const root = createRoot(document.getElementById('ep-sync'));

	root.render(<App />);
} else {
	render(<App />, document.getElementById('ep-sync'));
}
