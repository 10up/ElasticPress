import { useCallback, useRef } from '@wordpress/element';
import { apiEndpoint, apiHost } from './config';

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
 * Get a callback function for retrieving search results.
 *
 * @returns {Function} Memoized callback function for retrieving search results.
 */
export const useGetResults = () => {
	const abort = useRef(new AbortController());
	const request = useRef(null);

	/**
	 * Get new search results from the API.
	 *
	 * @param {URLSearchParams} urlParams Query arguments.
	 * @returns {Promise} Request promise.
	 */
	const getResults = async (urlParams) => {
		const url = `${apiHost}${apiEndpoint}?${urlParams.toString()}`;

		abort.current.abort();
		abort.current = new AbortController();

		request.current = fetch(url, {
			signal: abort.current.signal,
			headers: {
				Accept: 'application/json',
			},
		})
			.then((response) => {
				return response.json();
			})
			.catch((error) => {
				if (error?.name !== 'AbortError' && !request.current) {
					throw error;
				}
			})
			.finally(() => {
				request.current = null;
			});

		return request.current;
	};

	return useCallback(getResults, []);
};
