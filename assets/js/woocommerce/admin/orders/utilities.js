/**
 * Get the user's authorization header.
 *
 * @param {string} url API URL.
 * @param {string} nonce WordPress nonce.
 * @returns {string} Authorization header.
 */
export const getAuthorization = async (url, nonce) => {
	const response = await fetch(url, {
		headers: { 'X-WP-Nonce': nonce },
	});

	return response.text();
};

/**
 * Get a new authorization header.
 *
 * @param {string} url API URL.
 * @param {string} nonce WordPress nonce.
 * @returns {string} Authorization header.
 */
export const getNewAuthorization = async (url, nonce) => {
	const response = await fetch(url, {
		headers: { 'X-WP-Nonce': nonce },
		method: 'POST',
	});

	return response.text();
};
