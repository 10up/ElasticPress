/**
 * WordPress dependencies.
 */
import { useCallback, useContext, useRef } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { Context } from './lib';

/**
 * Get debounced version of a function that only runs a given ammount of time
 * after the last time it was run.
 *
 * @param {Function} callback Function to debounce.
 * @param {number}   delay    Milliseconds to delay.
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

/**
 * Use the Instant Results context.
 *
 * @returns {*} Context value.
 */
export const useInstantResults = () => {
	return useContext(Context);
};
