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
	indexMeta,
	isEpio,
	nonce,
	postTypes,
	syncHistory,
	syncTrigger,
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
	<SyncProvider
		apiUrl={apiUrl}
		defaultSyncHistory={syncHistory}
		defaultSyncTrigger={syncTrigger}
		indexMeta={indexMeta}
		isEpio={isEpio}
		nonce={nonce}
	>
		<SyncSettingsProvider autoIndex={autoIndex} indexables={indexables} postTypes={postTypes}>
			<SettingsScreenProvider title={__('Sync Settings', 'elasticpress')}>
				<Sync />
			</SettingsScreenProvider>
		</SyncSettingsProvider>
	</SyncProvider>
);

if (typeof createRoot === 'function') {
	const root = createRoot(document.getElementById('ep-sync'));

	root.render(<App />);
} else {
	render(<App />, document.getElementById('ep-sync'));
}
