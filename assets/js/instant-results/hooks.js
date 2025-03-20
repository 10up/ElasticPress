/**
 * WordPress dependencies.
 */
import { useCallback, useRef } from '@wordpress/element';

/**
 * Get debounced version of a function that only runs a given amount of time
 * after the last time it was run.
 *
 * @param {Function} callback Function to debounce.
 * @param {number} delay Milliseconds to delay.
 * @returns {Function} Debounced function.
 */
export const useDebounce = (callback, delay) => {
	const timeout = useRef(null);

	return useCallback(
		(...args) => {
			window.clearTimeout(timeout.current);

			timeout.current = window.setTimeout(() => {
				callback(...args);
			}, delay);
		},
		[callback, delay],
	);
};
