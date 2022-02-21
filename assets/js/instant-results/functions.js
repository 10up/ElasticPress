/**
 * Internal deendencies.
 */
import { currencyCode } from './config';

/**
 * Format a number as a price.
 *
 * @param {number} number  Number to format.
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
	const { args, filters } = state;

	const filterArgs = Object.entries(filters).reduce((filterArgs, [filter, value]) => {
		switch (filter) {
			case 'price_range':
				if (value.length > 0) {
					filterArgs.min_price = value[0];
					filterArgs.max_price = value[1];
				}

				break;
			case 'post_type':
				if (value.length > 0) {
					filterArgs[filter] = value.join(',');
				}

				break;
			default:
				if (value.length > 0) {
					filterArgs[`tax-${filter}`] = value.join(',');
				}

				break;
		}

		return filterArgs;
	}, {});

	const init = { ...args, ...filterArgs };

	return new URLSearchParams(init);
};
