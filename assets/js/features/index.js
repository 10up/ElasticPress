/**
 * WordPress dependencies.
 */
import { createInterpolateElement, createRoot, render, WPElement } from '@wordpress/element';
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
				{createInterpolateElement(
					__(
						'ElasticPress Features add functionality to enhance search and queries on your site. You may choose to activate some or all of these Features depending on your needs. You can learn more about each Feature <a>here</a>.',
						'elasticpress',
					),
					{
						a: (
							// eslint-disable-next-line jsx-a11y/anchor-has-content, jsx-a11y/control-has-associated-label
							<a
								target="_blank"
								href="https://elasticpress.zendesk.com/hc/en-us/articles/16671825423501-Features"
								rel="noreferrer"
							/>
						),
					},
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
