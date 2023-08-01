/**
 * WordPress dependencies.
 */
import { createRoot } from '@wordpress/element';

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

const root = createRoot(getRoot());
root.render(
	<AppContext>
		<SynonymsEditor />
	</AppContext>,
);
