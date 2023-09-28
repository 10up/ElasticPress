/**
 * WordPress dependencies.
 */
import { createRoot, render, WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { SettingsScreenProvider } from '../settings-screen';
import { plainTextReport, reports } from './config';
import Reports from './components/reports';

/**
 * App component
 *
 * @returns {WPElement} App component.
 */
const App = () => {
	return (
		<SettingsScreenProvider title={__('Status Report', 'elasticpress')}>
			<Reports plainTextReport={plainTextReport} reports={reports} />
		</SettingsScreenProvider>
	);
};

if (typeof createRoot === 'function') {
	const root = createRoot(document.getElementById('ep-status-reports'));

	root.render(<App />);
} else {
	render(<App />, document.getElementById('ep-status-reports'));
}
