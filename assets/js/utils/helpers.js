/**
 * WordPress dependencies.
 */
import { applyFilters } from '@wordpress/hooks';

/**
 * External dependencies.
 */
import { v4 as uuidv4 } from 'uuid';

/**
 * Simple throttling function for waiting a set amount of time after the last keypress
 * So we don't overload the server with too many requests at once
 *
 * @param {Function} fn    - function to be debounced
 * @param {number}   delay - integer
 * @returns {Function} - new function, with the provided function wrapped in a timeout
 */
export const debounce = (fn, delay) => {
	let timer = null;

	// don't use a fat arrow in order to preserve the proper context
	return function debouncedFunction(...args) {
		const context = this;
		window.clearTimeout(timer);

		timer = window.setTimeout(() => {
			fn.apply(context, args);
		}, delay);
	};
};

/**
 * Helper function to escape input to be treated as a literal string with a RegEx
 *
 * @param {string} string - string to be escaped
 * @returns {string} escaped string
 */
export const escapeRegExp = (string) => string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

/**
 * Helper function to escape input to be treated as a literal string with a RegEx
 *
 * @param {string} string      - string to replace
 * @param {string} term        - tearm to search for
 * @param {string} replacement replace value to use
 * @returns {string} replaced string
 */
export const replaceGlobally = (string, term, replacement) => {
	return string.replace(
		new RegExp(escapeRegExp(term), 'g'),
		JSON.stringify(replacement).slice(1, -1), // Escapes especial chars and remove quotes added by JSON.stringify
	);
};

/**
 * Escapes double quotes for specific data-attr
 *
 * @param {string} str The provided string containing double quotes
 * @returns {string} The escaped string
 */
export const escapeDoubleQuotes = (str) => str.replace(/\\([\s\S])|(")/g, '&quot;');

/**
 * Finds parent node with the provided class param
 *
 * @param {*} el        - node to search for its ancestor
 * @param {*} className - class attribute to search for
 * @returns {Element} - ancestor element of provided el
 */
export const findAncestorByClass = (el, className) => {
	// eslint-disable-next-line
	while ( ( el = el.parentElement ) && !el.classList.contains( className ) );
	return el;
};

/**
 * Array pluck
 *
 * @param {Array}  array - array to search
 * @param {string} key   - array to search
 * @returns {Array} - new array
 */
export const pluck = (array, key) => {
	return array.map((o) => o[key]);
};

/**
 * Formats object like a url query string, which is how ajax methods
 * in PHP expect to receive the data, e.g. action_wp_ajax_ep_save_feature
 * from dashboard.php
 *
 * @param {object} obj - js object
 * @returns {string} urlencoded string for POST ajax request
 */
export const formatPostBody = (obj) => {
	return Object.keys(obj)
		.map((key) => `${encodeURIComponent(key)}=${encodeURIComponent(obj[key])}`)
		.join('&')
		.replace(/%20/g, '+');
};

/**
 * Helper method to wrap show/hide elements. Not exported.
 *
 * @param {Array}  els     - could possibly be a single node, or an array of nodes
 * @param {string} display - css display property to set
 */
const showOrHideNodes = (els, display) => {
	let nodes = [];

	// convert nodelist to array
	// eslint-disable-next-line no-prototype-builtins
	if (NodeList.prototype.isPrototypeOf(els)) {
		nodes = Array.from(els);
	}

	// if not converted, then it was a single node,
	// so create an array
	if (!nodes.length) {
		if (Array.isArray(els)) {
			nodes = [...els, ...nodes];
		} else {
			nodes.push(els);
		}
	}

	nodes.forEach((el) => {
		el.style.display = display; // eslint-disable-line no-param-reassign
	});
};

/**
 * Decorated helper function to show node/NodeList/array of nodes
 *
 * @param {Array} els - Nodelist/array of Nodes to show
 * @returns {Function} - showOrHideNodes
 */
export const showElements = (els) => showOrHideNodes(els, 'inline-block');

/**
 * Decorated helper function to hide node/NodeList/array of nodes
 *
 * @param {Array} els - Nodelist/array of Nodes to show
 * @returns {Function} - showOrHideNodes
 */
export const hideElements = (els) => showOrHideNodes(els, 'none');

/**
 * Specify a function to execute when the DOM is fully loaded.
 *
 * @param {Function} callback A function to execute after the DOM is ready.
 * @returns {void}
 */
export const domReady = (callback) => {
	if (typeof document === 'undefined') {
		return;
	}

	if (
		document.readyState === 'complete' || // DOMContentLoaded + Images/Styles/etc loaded, so we call directly.
		document.readyState === 'interactive' // DOMContentLoaded fires at this point, so we call directly.
	) {
		callback();
		return;
	}

	// DOMContentLoaded has not fired yet, delay callback until then.
	document.addEventListener('DOMContentLoaded', callback);
};

/**
 * Generate a Request ID for autosuggest
 *
 * @param {string} requestIdBase - base for request ID generation
 * @returns {string} Request ID
 */
export const generateRequestId = (requestIdBase) => {
	const uuid = uuidv4().replaceAll('-', '');

	/**
	 * Filter the request ID used for an autosuggest request.
	 *
	 * @filter ep.Autosuggest.requestId
	 * @since 4.5.0
	 *
	 * @param {string} requestId The Request ID.
	 * @returns {string} New Request ID.
	 */
	return applyFilters('ep.requestId', requestIdBase + uuid);
};
