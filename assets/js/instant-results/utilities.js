/**
 * WordPress dependencies.
 */
import { applyFilters } from '@wordpress/hooks';

/**
 * Internal dependencies.
 */
import { sanitizeArg } from '../api-search/src/utilities';

/**
 * Format a date.
 *
 * @param {string} date Date string.
 * @param {string} locale BCP 47 language tag.
 * @returns {string} Formatted number.
 */
export const formatDate = (date, locale) => {
	return new Date(date).toLocaleString(locale, { dateStyle: 'long' });
};

/**
 * Format a number as a price.
 *
 * @param {number} number  Number to format.
 * @param {object} options Formatter options.
 * @returns {string} Formatted number.
 */
export const formatPrice = (number, options) => {
	const format = new Intl.NumberFormat(navigator.language, {
		style: 'currency',
		currencyDisplay: 'narrowSymbol',
		...options,
	});

	return format.format(number);
};

/**
 * Get the post types from a search form.
 *
 * @param {HTMLFormElement} form Form element.
 * @returns {Array} Post types.
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
 * Get search args from a search form for Instant Results.
 *
 * @param {HTMLFormElement} form       Form element.
 * @param {object}          argsSchema Search args schema.
 * @returns {object} Search args.
 */
export const getArgsFromForm = (form, argsSchema) => {
	/**
	 * Filter the map of query variable names to Instant Results arg names.
	 *
	 * @filter ep.instantResults.queryVarMap
	 * @since 5.4.0
	 *
	 * @param {object} map Map of WordPress query var names to Instant Results arg names.
	 * @param {HTMLFormElement} form Form element.
	 * @param {object} argsSchema Search args schema.
	 * @returns {object} Map of WordPress query var names to Instant Results arg names.
	 */
	const QUERY_VAR_MAP = applyFilters(
		'ep.instantResults.queryVarMap',
		{
			s: 'search',
			cat: 'tax-category',
			tag_id: 'tax-post_tag',
		},
		{ form, argsSchema },
	);

	const formData = new FormData(form);
	const params = new URLSearchParams();
	const formEntries = Array.from(formData.entries());

	formEntries.forEach(([key, value]) => {
		// Strip trailing [] from array-style field names.
		const cleanKey = key.replace(/\[\]$/, '');
		// Resolve the EP arg name: explicit map → direct schema match → tax- prefix.
		const argName =
			QUERY_VAR_MAP[cleanKey] ||
			(argsSchema[cleanKey] ? cleanKey : null) ||
			(argsSchema[`tax-${cleanKey}`] ? `tax-${cleanKey}` : null);

		if (value && argName) {
			const existing = params.get(argName);
			params.set(argName, existing ? `${existing},${value}` : value);
		}
	});

	return Object.entries(argsSchema).reduce((args, [arg, options]) => {
		const param = params.get(arg);
		if (param !== null) {
			const value = sanitizeArg(param, options, false);
			if (value !== null) {
				args[arg] = value;
			}
		}

		return args;
	}, {});
};
