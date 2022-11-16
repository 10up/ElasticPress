/* global ClipboardJS */

/**
 * WordPress dependencies.
 */
import domReady from '@wordpress/dom-ready';

/**
 * Status report copy button.
 *
 * @returns {void}
 */
const init = () => {
	const clipboard = new ClipboardJS('#ep-copy-report');

	/**
	 * Handle successful copy.
	 *
	 * @param {Event} event Copy event.
	 * @returns {void}
	 */
	const onSuccess = (event) => {
		event.trigger.nextElementSibling.style.display = 'initial';

		setTimeout(() => {
			event.trigger.nextElementSibling.style.display = 'none';
		}, 3000);

		event.clearSelection();
	};

	/**
	 * Bind events.
	 */
	clipboard.on('success', onSuccess);
};

domReady(init);
