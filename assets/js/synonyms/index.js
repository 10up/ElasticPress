/**
 * WordPress dependencies.
 */
import { createRoot, render, WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { SettingsScreenProvider } from '../settings-screen';
import { apiUrl, defaultIsSolr, defaultSolr, syncUrl } from './config';
import { SynonymsSettingsProvider } from './provider';
import SynonymsSettings from './apps/synonyms-settings';

/**
 * Styles.
 */
import './style.css';

/**
 * App component.
 *
 * @returns {WPElement}
 */
const App = () => (
	<SettingsScreenProvider title={__('Manage Synonyms', 'elasticpress')}>
		<SynonymsSettingsProvider
			apiUrl={apiUrl}
			defaultIsSolr={defaultIsSolr}
			defaultSolr={defaultSolr}
			syncUrl={syncUrl}
		>
			<SynonymsSettings />
		</SynonymsSettingsProvider>
	</SettingsScreenProvider>
);

/**
 * Root element.
 */
const el = document.getElementById('ep-synonyms');

/**
 * Render.
 */
if (typeof createRoot === 'function') {
	const root = createRoot(el);

	root.render(<App />);
} else {
	render(<App />, el);
}
