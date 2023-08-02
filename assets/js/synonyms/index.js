/**
 * WordPress dependencies.
 */
import { createRoot, render } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { AppContext } from './context';
import SynonymsEditor from './components/SynonymsEditor';

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
		<AppContext>
			<SynonymsEditor />
		</AppContext>,
	);
} else {
	render(
		<AppContext>
			<SynonymsEditor />
		</AppContext>,
		getRoot(),
	);
}
