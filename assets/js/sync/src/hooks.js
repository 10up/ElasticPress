/**
 * WordPress dependencies.
 */
import { useCallback, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Indexing hook.
 *
 * Provides methods for indexing, getting indexing status, and cancelling
 * indexing. Methods share an abort controller so that requests can
 * interrupt eachother to avoid multiple sync requests causing race conditions
 * or duplicate output, such as by rapidly pausing and unpausing indexing.
 *
 * @param {string} apiUrl AJAX endpoint URL.
 * @param {string} nonce WordPress nonce.
 * @returns {object} Sync, sync status, and cancel functions.
 */
export const useIndex = (apiUrl, nonce) => {
	const abort = useRef(new AbortController());
	const request = useRef(null);

	const onResponse = useCallback(
		/**
		 * Handle the response to the request.
		 *
		 * @param {Response} response Request response.
		 * @throws {Error} An error for unexpected responses.
		 * @returns {void}
		 */
		async (response) => {
			const responseBody = await response.text();

			const errorMessage = `${__(
				'ElasticPress: Unexpected response.',
				'elasticpress',
			)}\n${responseBody}`;

			/**
			 * Throw an error for non-20X responses.
			 */
			if (!response.ok) {
				if (response.status === 403) {
					/**
					 * A 403 response will occur if the nonce has expired or
					 * if the user's session has expired. Reloading the page
					 * will reset the nonce or prompt the user to log in again.
					 */
					throw new Error(
						__(
							'Permission denied. Reload the sync page and try again.',
							'elasticpress',
						),
					);
				} else {
					/**
					 * Log the raw response to the console to assist with
					 * debugging.
					 */
					console.error(errorMessage); // eslint-disable-line no-console

					/**
					 * Any other response is unexpected, and the user will
					 * need to troubleshoot.
					 */
					throw new Error(
						__(
							'Something went wrong. Find troubleshooting steps at https://elasticpress.zendesk.com/hc/en-us/articles/20857557098125/.',
							'elasticpress',
						),
					);
				}
			}

			/**
			 * Parse the response and throw an error if it fails.
			 */
			try {
				return JSON.parse(responseBody);
			} catch (e) {
				/**
				 * Log the raw response to the console to assist with
				 * debugging.
				 */
				console.error(e.message); // eslint-disable-line no-console
				console.error(errorMessage); // eslint-disable-line no-console

				/**
				 * Invalid JSON is unexpected at this stage, and the user will
				 * need to troubleshoot.
				 */
				throw new Error(
					__(
						'Unable to parse response. Find troubleshooting steps at https://elasticpress.zendesk.com/hc/en-us/articles/20857557098125/.',
						'elasticpress',
					),
				);
			}
		},
		[],
	);

	const onComplete = useCallback(
		/**
		 * Handle completion of the request, whether successful or not.
		 *
		 * @returns {void}
		 */
		() => {
			request.current = null;
		},
		[],
	);

	const sendRequest = useCallback(
		/**
		 * Send AJAX request.
		 *
		 * Silently catches abort errors and clears the current request on
		 * completion.
		 *
		 * @param {URL} url API URL.
		 * @param {object} options Request options.
		 * @throws {Error} Any non-abort errors.
		 * @returns {Promise} Current request promise.
		 */
		(url, options) => {
			request.current = fetch(url, options).then(onResponse).finally(onComplete);

			return request.current;
		},
		[onComplete, onResponse],
	);

	const cancelIndex = useCallback(
		/**
		 * Send a request to cancel sync.
		 *
		 * @returns {Promise} Fetch request promise.
		 */
		async () => {
			abort.current.abort();
			abort.current = new AbortController();

			const url = new URL(apiUrl);

			const options = {
				headers: {
					'X-WP-Nonce': nonce,
				},
				method: 'DELETE',
				signal: abort.current.signal,
			};

			return sendRequest(url, options);
		},
		[apiUrl, nonce, sendRequest],
	);

	const index = useCallback(
		/**
		 * Send a request to sync.
		 *
		 * @param {object} args Sync args.
		 * @returns {Promise} Fetch request promise.
		 */
		async (args) => {
			abort.current.abort();
			abort.current = new AbortController();

			const url = new URL(apiUrl);

			Object.keys(args).forEach((arg) => {
				if (args[arg]) {
					url.searchParams.append(arg, args[arg]);
				}
			});

			const options = {
				headers: {
					'X-WP-Nonce': nonce,
				},
				method: 'POST',
				signal: abort.current.signal,
			};

			return sendRequest(url, options);
		},
		[apiUrl, nonce, sendRequest],
	);

	const indexStatus = useCallback(
		/**
		 * Send a request for CLI sync status.
		 *
		 * @returns {Promise} Fetch request promise.
		 */
		async () => {
			abort.current.abort();
			abort.current = new AbortController();

			const url = new URL(apiUrl);

			const options = {
				headers: {
					'X-WP-Nonce': nonce,
				},
				method: 'GET',
				signal: abort.current.signal,
			};

			return sendRequest(url, options);
		},
		[apiUrl, nonce, sendRequest],
	);

	return { cancelIndex, index, indexStatus };
};
