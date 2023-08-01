/**
 * WordPress dependencies.
 */
import { createRoot } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { Pointers } from './pointers';

const el = document.getElementById('ordering-app');
const root = createRoot(el);
root.render(<Pointers />);
