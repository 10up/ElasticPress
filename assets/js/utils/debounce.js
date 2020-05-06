/**
 * Simple throttling function for waiting a set amount of time after the last keypress
 * So we don't overload the server with too many requests at once
 *
 * @param {Function} fn - function to be debounced
 * @param {number} delay - integer
 * @returns {Function} - new function, with the provided function wrapped in a timeout
 */
function debounce(fn, delay) {
	let timer = null;

	return function debouncedFunction(...args) {
		const context = this;
		window.clearTimeout(timer);

		timer = window.setTimeout(() => {
			fn.apply(context, args);
		}, delay);
	};
}

export default {
	debounce,
};
