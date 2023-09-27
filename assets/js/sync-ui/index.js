/**
 * WordPress dependencies.
 */
import { createRoot, render, WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { SettingsScreenProvider } from '../settings-screen';
import { SyncProvider } from '../sync';
import {
	apiUrl,
	autoIndex,
	indexables,
	lastSyncDateTime,
	lastSyncFailed,
	indexMeta,
	isEpio,
	postTypes,
	nonce,
} from './config';
import { SyncSettingsProvider } from './provider';
import Sync from './apps/sync';

/**
 * Styles.
 */
import './style.css';

/**
 * App component.
 *
 * @returns {WPElement} App component.
 */
const App = () => (
	<SettingsScreenProvider title={__('Sync Settings', 'elasticpress')}>
		<SyncProvider
			apiUrl={apiUrl}
			defaultLastSyncDateTime={lastSyncDateTime}
			defaultLastSyncFailed={lastSyncFailed}
			indexMeta={indexMeta}
			isEpio={isEpio}
			nonce={nonce}
		>
			<SyncSettingsProvider
				autoIndex={autoIndex}
				indexables={indexables}
				postTypes={postTypes}
			>
				<Sync />
			</SyncSettingsProvider>
		</SyncProvider>
	</SettingsScreenProvider>
);

if (typeof createRoot === 'function') {
	const root = createRoot(document.getElementById('ep-sync'));

	root.render(<App />);
} else {
	render(<App />, document.getElementById('ep-sync'));
}
