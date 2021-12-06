/**
 * Internal deendencies.
 */
import { currencyCode } from './config';

/**
 * Set API request args for post type.
 *
 * @param {string[]} postTypes Post types.
 * @return {Object} Price range args.
 */
export const getArgsFromPostTypes = (postTypes) => {
	const args = {};

	if (postTypes.length > 0) {
		args.post_type = postTypes;
	}

	return args;
};

/**
 * Set API request args for a price range.
 *
 * @param {number} min Minimum price.
 * @param {number} max Maximum price.
 * @return {Object} Request args.
 */
export const getArgsFromPriceRange = (min, max) => {
	const args = {};

	if (min) {
		args.min_price = min;
	}

	if (max) {
		args.max_price = max;
	}

	return args;
};

/**
 * Set API request args for taxonomy terms.
 *
 * @param {Object[]} taxonomyTerms Taxonomies and their terms.
 * @return {Object} Request args.
 */
export const getArgsFromTaxonomyTerms = (taxonomyTerms) => {
	return Object.entries(taxonomyTerms).reduce((args, [taxonomy, terms]) => {
		const param = `tax-${taxonomy}`;

		if (terms.length > 0) {
			args[param] = terms.join(',');
		}

		return args;
	}, {});
};

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
 * Get query parameters for an API request from the state.
 *
 * @param {Object} state State.
 * @return {URLSearchParams} URLSearchParams instance.
 */
export const getURLParamsFromState = (state) => {
	const { args, postTypes, priceRange, taxonomyTerms } = state;

	const postTypeParam = getArgsFromPostTypes(postTypes);
	const priceRangeParams = getArgsFromPriceRange(...priceRange);
	const taxonomyTermsParams = getArgsFromTaxonomyTerms(taxonomyTerms);

	const init = {
		...args,
		...postTypeParam,
		...priceRangeParams,
		...taxonomyTermsParams,
	};

	return new URLSearchParams(init);
};
