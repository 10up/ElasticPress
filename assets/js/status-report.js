/* global ClipboardJS */

/**
 * WordPress dependencies.
 */
import domReady from '@wordpress/dom-ready';

domReady(() => {
	const clipboard = new ClipboardJS('#ep-copy-report');
	clipboard.on('success', function (e) {
		const copySuccess = document.getElementById('ep-copy-success');
		setTimeout(function () {
			copySuccess.style.display = 'inline-block';
		}, 300);
		setTimeout(function () {
			copySuccess.style.display = 'none';
		}, 5000);
		e.clearSelection();
	});
});
