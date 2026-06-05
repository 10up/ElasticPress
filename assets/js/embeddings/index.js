/**
 * WordPress dependencies.
 */
import { createRoot, render, WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { SettingsScreenProvider } from '../settings-screen';
import { VectorEmbeddingsProvider } from './provider';
import PostTypesList from './apps/settings';
import { apiUrl, postTypeConfig, chunkSize, chunkOverlap, embeddingsFiltered } from './config';

/**
 * Styles.
 */
import './style.css';

/**
 * App component.
 *
 * @returns {WPElement}
 */
const App = () => {
	return (
		<SettingsScreenProvider title={__('Manage Vector Embeddings', 'elasticpress')}>
			<VectorEmbeddingsProvider
				{...{ apiUrl, postTypeConfig, chunkSize, chunkOverlap, embeddingsFiltered }}
			>
				<PostTypesList />
			</VectorEmbeddingsProvider>
		</SettingsScreenProvider>
	);
};

/**
 * Root element.
 */
const el = document.getElementById('ep-vector-embeddings');

/**
 * Render.
 */
if (typeof createRoot === 'function') {
	const root = createRoot(el);

	root.render(<App />);
} else {
	render(<App />, el);
}
