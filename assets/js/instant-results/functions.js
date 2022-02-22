/**
 * Internal deendencies.
 */
import { currencyCode, facets } from './config';
import { sanitizeArg, sanitizeParam } from './utilities';

/**
 * Clear facet filters from a set of args.
 *
 * @param {Object} args Args to clear facets from.
 * @return {Object} Cleared args.
 */
export const clearFacetsFromArgs = (args) => {
	const clearedArgs = { ...args };

	facets.forEach(({ name, type }) => {
		switch (type) {
			case 'price_range':
				delete clearedArgs.max_price;
				delete clearedArgs.min_price;
				break;
			default:
				delete clearedArgs[name];
				break;
		}
	});

	return clearedArgs;
};

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
 * Get permalink URL parameters from args.
 *
 * @typedef {Object} ArgSchema
 * @property {string} type Arg type.
 * @property {any} [default] Default arg value.
 * @property {Array} [allowedValues] Array of allowed values.
 *
 * @param {Object} args Args
 * @param {ArgSchema} schema Args schema.
 * @param {string} [prefix] Prefix to prepend to args.
 * @return {URLSearchParams} URLSearchParams instance.
 */
export const getUrlParamsFromArgs = (args, schema, prefix = '') => {
	const urlParams = new URLSearchParams();

	Object.entries(schema).forEach(([arg, options]) => {
		const param = prefix + arg;
		const value = typeof args[arg] !== 'undefined' ? sanitizeParam(args[arg], options) : null;

		if (value !== null) {
			urlParams.set(param, value);
		}
	});

	return urlParams;
};

/**
 * Build request args from URL parameters using a given schema.
 *
 * @typedef {Object} ArgSchema
 * @property {string} type Arg type.
 * @property {any} [default] Default arg value.
 * @property {Array} [allowedValues] Array of allowed values.
 *
 * @param {URLSearchParams} urlParams URL parameters.
 * @param {object.<string, ArgSchema>} schema Schema to build args from.
 * @param {string} [prefix] Parameter prefix.
 * @param {boolean} [useDefaults] Whether to populate params with default values.
 * @return {object.<string, any>} Query args.
 */
export const getArgsFromUrlParams = (urlParams, schema, prefix = '', useDefaults = true) => {
	const args = Object.entries(schema).reduce((args, [arg, options]) => {
		const param = urlParams.get(prefix + arg);
		const value =
			typeof param !== 'undefined' ? sanitizeArg(param, options, useDefaults) : null;

		if (value !== null) {
			args[arg] = value;
		}

		return args;
	}, {});

	return args;
};
