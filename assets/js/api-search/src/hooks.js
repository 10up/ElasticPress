/**
 * WordPress dependencies.
 */
import { useCallback, useRef } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { generateRequestId } from '../../utils/helpers';

/**
 * Get a callback function for retrieving search results.
 *
 * @param {string} apiHost API host.
 * @param {string} apiEndpoint API endpoint.
 * @param {string} Authorization Authorization header.
 * @param {string} requestIdBase Base of Request IDs.
 * @returns {Function} Function for retrieving search results.
 */
export const useFetchResults = (apiHost, apiEndpoint, Authorization, requestIdBase = '') => {
	const abort = useRef(new AbortController());
	const request = useRef(null);

	/**
	 * Get new search results from the API.
	 *
	 * @param {URLSearchParams} urlParams Query arguments.
	 * @returns {Promise} Request promise.
	 */
	const fetchResults = async (urlParams) => {
		const url = `${apiHost}${apiEndpoint}?${urlParams.toString()}`;

		abort.current.abort();
		abort.current = new AbortController();

		const headers = {
			Accept: 'application/json',
			Authorization,
		};

		const requestId = generateRequestId(requestIdBase);
		if (requestId) {
			headers['X-ElasticPress-Request-ID'] = requestId;
		}

		request.current = fetch(url, {
			signal: abort.current.signal,
			headers,
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

	return useCallback(fetchResults, [apiHost, apiEndpoint, Authorization, requestIdBase]);
};
