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
