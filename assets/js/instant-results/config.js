/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';

/**
 * Window dependencies.
 */
const {
	apiEndpoint,
	apiHost,
	argsSchema,
	currencyCode,
	facets,
	isWooCommerce,
	locale,
	matchType,
	numberedPagination,
	paramPrefix,
	postTypeLabels,
	taxonomyLabels,
	termCount,
	requestIdBase,
	showSuggestions,
	suggestionsBehavior,
} = window.epInstantResults;

const isNumberedPagination = String(numberedPagination || '0') === '1';

/**
 * Sorting options configuration.
 */
const sortOptions = {
	relevance_desc: {
		name: __('Most relevant', 'elasticpress'),
		orderby: 'relevance',
		order: 'desc',
		currencyCode,
	},
	date_desc: {
		name: __('Date, newest to oldest', 'elasticpress'),
		orderby: 'date',
		order: 'desc',
	},
	date_asc: {
		name: __('Date, oldest to newest', 'elasticpress'),
		orderby: 'date',
		order: 'asc',
	},
};

/**
 * Sort by price is only available for WooCommerce.
 */
if (isWooCommerce) {
	sortOptions.price_desc = {
		name: __('Price, highest to lowest', 'elasticpress'),
		orderby: 'price',
		order: 'desc',
	};

	sortOptions.price_asc = {
		name: __('Price, lowest to highest', 'elasticpress'),
		orderby: 'price',
		order: 'asc',
	};
}

export {
	apiEndpoint,
	apiHost,
	argsSchema,
	currencyCode,
	facets,
	isWooCommerce,
	isNumberedPagination,
	locale,
	matchType,
	paramPrefix,
	postTypeLabels,
	sortOptions,
	taxonomyLabels,
	termCount,
	requestIdBase,
	showSuggestions,
	suggestionsBehavior,
};
