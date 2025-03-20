/**
 * WordPress dependencies.
 */
import { createRoot, render, WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal Dependencies.
 */
import { SettingsScreenProvider } from '../settings-screen';
import { apiUrl, metaMode, syncUrl, weightableFields, weightingConfiguration } from './config';
import { WeightingSettingsProvider } from './provider';
import Weighting from './apps/weighting';

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
	<SettingsScreenProvider title={__('Manage Search Fields & Weighting', 'elasticpress')}>
		<WeightingSettingsProvider
			apiUrl={apiUrl}
			metaMode={metaMode}
			syncUrl={syncUrl}
			weightingConfiguration={weightingConfiguration}
			weightableFields={weightableFields}
		>
			<Weighting />
		</WeightingSettingsProvider>
	</SettingsScreenProvider>
);

if (typeof createRoot === 'function') {
	const root = createRoot(document.getElementById('ep-weighting-screen'));

	root.render(<App />);
} else {
	render(<App />, document.getElementById('ep-weighting-screen'));
}
