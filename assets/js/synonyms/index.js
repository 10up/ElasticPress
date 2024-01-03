/**
 * WordPress dependencies.
 */
import { createRoot, render } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { SettingsScreenProvider } from '../settings-screen';
import { defaultIsSolr, defaultAlternatives, defaultSets } from './config';
import { SynonymsSettingsProvider } from './provider';
import SynonymsEditor from './apps/synonyms-settings';

/**
 * Styles.
 */
import './style.css';

const SELECTOR = '#synonym-root';

/**
 * Get Root.
 *
 * @returns {Element|false} Root element
 */
const getRoot = () => document.querySelector(SELECTOR) || false;

if (typeof createRoot === 'function') {
	const root = createRoot(getRoot());
	root.render(
		<SettingsScreenProvider title={__('Manage Synonyms', 'elasticpress')}>
			<SynonymsSettingsProvider
				defaultAlternatives={defaultAlternatives}
				defaultIsSolr={defaultIsSolr}
				defaultSets={defaultSets}
			>
				<SynonymsEditor />
			</SynonymsSettingsProvider>
		</SettingsScreenProvider>,
	);
} else {
	render(
		<SettingsScreenProvider title={__('Manage Synonyms', 'elasticpress')}>
			<SynonymsSettingsProvider
				defaultAlternatives={defaultAlternatives}
				defaultIsSolr={defaultIsSolr}
				defaultSets={defaultSets}
			>
				<SynonymsEditor />
			</SynonymsSettingsProvider>
		</SettingsScreenProvider>,
		getRoot(),
	);
}
