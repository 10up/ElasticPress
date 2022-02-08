/**
 * Internal deendencies.
 */
import { currencyCode } from './config';

/**
 * Format a number as a price.
 *
 * @param {number} number Number to format.
 * @param {Object} options Formatter options.
 * @return {string} Formatted number.
 */
export const formatPrice = (number, options) => {
	const format = new Intl.NumberFormat(navigator.language, {
		style: 'currency',
		currency: currencyCode,
		currencyDisplay: 'narrowSymbol',
		...options,
	});

	return format.format(number);
};

/**
 * Get the post types from a search form.
 *
 * @param {HTMLFormElement} form Form element.
 * @return {Array} Post types.
 */
export const getPostTypesFromForm = (form) => {
	const data = new FormData(form);

	if (data.has('post_type')) {
		return data.getAll('post_type').slice(-1);
	}

	if (data.has('post_type[]')) {
		return data.getAll('post_type[]');
	}

	return [];
};

/**
 * Get query parameters for an API request from the state.
 *
 * @param {Object} state State.
 * @return {URLSearchParams} URLSearchParams instance.
 */
export const getURLParamsFromState = (state) => {
	const { args } = state;

	const init = Object.entries(args).reduce((init, [key, value]) => {
		if (Array.isArray(value)) {
			if (value.length > 0) {
				init[key] = value.join(',');
			}
		} else {
			init[key] = value;
		}

		return init;
	}, {});

	return new URLSearchParams(init);
};
