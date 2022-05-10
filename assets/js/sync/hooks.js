/**
 * WordPress dependencies.
 */
import apiFetch from '@wordpress/api-fetch';
import { useCallback, useRef } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { ajaxUrl, nonce } from './config';

/**
 * Indexing hook.
 *
 * Provides methods for indexing, getting indexing status, and cancelling
 * indexing. Methods share an abort controller so that requests can
 * interrupt eachother to avoid multiple sync requests causing race conditions
 * or duplicate output, such as by rapidly pausing and unpausing indexing.
 *
 * @returns {object} Sync, sync status, and cancel functions.
 */
export const useIndex = () => {
	const abort = useRef(new AbortController());
	const request = useRef(null);

	const sendRequest = useCallback(
		/**
		 * Send AJAX request.
		 *
		 * Silently catches abort errors and clears the current request on
		 * completion.
		 *
		 * @param {object} options Request options.
		 * @throws {Error} Any non-abort errors.
		 * @returns {Promise} Current request promise.
		 */
		(options) => {
			request.current = apiFetch(options).finally(() => {
				request.current = null;
			});

			return request.current;
		},
		[],
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

			const body = new FormData();

			body.append('action', 'ep_cancel_index');
			body.append('nonce', nonce);

			const options = {
				url: ajaxUrl,
				method: 'POST',
				body,
				signal: abort.current.signal,
			};

			return sendRequest(options);
		},
		[sendRequest],
	);

	const index = useCallback(
		/**
		 * Send a request to sync.
		 *
		 * @param {boolean} putMapping Whether to put mapping.
		 * @returns {Promise} Fetch request promise.
		 */
		async (putMapping) => {
			abort.current.abort();
			abort.current = new AbortController();

			const body = new FormData();

			body.append('action', 'ep_index');
			body.append('put_mapping', putMapping ? 1 : 0);
			body.append('nonce', nonce);

			const options = {
				url: ajaxUrl,
				method: 'POST',
				body,
				signal: abort.current.signal,
			};

			return sendRequest(options);
		},
		[sendRequest],
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

			const body = new FormData();

			body.append('action', 'ep_index_status');
			body.append('nonce', nonce);

			const options = {
				url: ajaxUrl,
				method: 'POST',
				body,
				signal: abort.current.signal,
			};

			return sendRequest(options);
		},
		[sendRequest],
	);

	return { cancelIndex, index, indexStatus };
};
