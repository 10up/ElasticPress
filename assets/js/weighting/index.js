/**
 * WordPress dependencies.
 */
import { createRoot, render, WPElement } from '@wordpress/element';

/**
 * Internal Dependencies.
 */
import { apiUrl, metaMode, syncUrl, weightableFields, weightingConfiguration } from './config';
import WeightingProvider from './provider';
import SettingsPage from './apps/settings-page';

/**
 * App component.
 *
 * @returns {WPElement} App component.
 */
const App = () => (
	<WeightingProvider
		apiUrl={apiUrl}
		metaMode={metaMode}
		syncUrl={syncUrl}
		weightingConfiguration={weightingConfiguration}
		weightableFields={weightableFields}
	>
		<SettingsPage />
	</WeightingProvider>
);

if (typeof createRoot === 'function') {
	const root = createRoot(document.getElementById('ep-weighting-screen'));

	root.render(<App />);
} else {
	render(<App />, document.getElementById('ep-weighting-screen'));
}
