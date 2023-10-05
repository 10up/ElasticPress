/**
 * WordPress dependencies.
 */
import { useCallback, useRef } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

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
 * @param {Function} onAuthError Function to run when request authentication fails.
 * @param {string} requestIdBase Base of Request IDs.
 * @returns {Function} Function for retrieving search results.
 */
export const useFetchResults = (
	apiHost,
	apiEndpoint,
	Authorization,
	onAuthError,
	requestIdBase = '',
) => {
	const abort = useRef(new AbortController());
	const request = useRef(null);
	const onAuthErrorRef = useRef(onAuthError);

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
				if (!response.ok) {
					if (response.status === 401 && onAuthErrorRef.current) {
						onAuthErrorRef.current();
						return '';
					}

					throw new Error(sprintf(__('HTTP %d.', 'elasticpress'), response.status));
				}

				return response.json();
			})
			.catch((error) => {
				if (error?.name !== 'AbortError') {
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
