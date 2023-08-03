/**
 * WordPress dependencies.
 */
import { createRoot, render } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { Pointers } from './pointers';

const el = document.getElementById('ordering-app');

if (typeof createRoot === 'function') {
	const root = createRoot(el);
	root.render(<Pointers />);
} else {
	render(<Pointers />, el);
}
