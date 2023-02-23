/**
 * WordPress dependencies.
 */
import { render } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';

/**
 * Internal dependencies.
 */
import App from './src';

/**
 * Initialize.
 *
 * @returns {void}
 */
const init = () => {
	const blocks = document.querySelectorAll('.ep-facet-meta-range');

	blocks.forEach((block) => {
		const [min, max] = block.querySelectorAll('input[type="hidden"]');
		const el = document.createElement('div');

		block.insertAdjacentElement('afterbegin', el);

		render(<App min={min} max={max} />, el);
	});
};

domReady(init);
