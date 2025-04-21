/**
 * WordPress dependencies.
 */
import domReady from '@wordpress/dom-ready';

/**
 * Initialize.
 *
 * @returns {void}
 */
const init = () => {
	/**
	 * If '.wp-header-end' is found, append the notices after it.
	 */
	const headerEnd = document.getElementById('ep-wp-header-end');
	const notices = document.querySelectorAll('div.update-nag');

	for (const notice of notices) {
		headerEnd.after(notice);
	}
};

/**
 * Initialize.
 */
domReady(init);
