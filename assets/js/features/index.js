/**
 * WordPress dependencies.
 */
import { createRoot, render, WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { SettingsScreenProvider } from '../settings-screen';
import {
	apiUrl,
	epioLogoUrl,
	features,
	indexMeta,
	settings,
	settingsDraft,
	syncUrl,
} from './config';
import { FeatureSettingsProvider } from './provider';
import Features from './apps/features';

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
	<SettingsScreenProvider title={__('Features', 'elasticpress')}>
		<FeatureSettingsProvider
			apiUrl={apiUrl}
			defaultSettings={settingsDraft || settings}
			epioLogoUrl={epioLogoUrl}
			features={features}
			indexMeta={indexMeta}
			syncedSettings={settings}
			syncUrl={syncUrl}
		>
			<p>
				{__(
					'Features explanation. Bacon ipsum dolor amet turkey cow turducken, tri-tip bresaola landjaeger biltong kevin short ribs alcatra shoulder frankfurter. Buffalo boudin meatloaf sausage cow prosciutto.',
					'elasticpress',
				)}
			</p>
			<Features />
		</FeatureSettingsProvider>
	</SettingsScreenProvider>
);

if (typeof createRoot === 'function') {
	const root = createRoot(document.getElementById('ep-dashboard'));

	root.render(<App />);
} else {
	render(<App />, document.getElementById('ep-dashboard'));
}
