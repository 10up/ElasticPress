/**
 * WordPress hooks integration for Autosuggest component
 */

/**
 * Apply filters to search results
 *
 * @param {Array} searchResults - The original search results from API
 * @param {string} searchTerm - The current search term
 * @returns {Array} - The filtered search results
 */
export const applyResultsFilter = (searchResults, searchTerm) => {
	// Check if wp.hooks is available (WordPress environment)
	if (typeof wp !== 'undefined' && wp.hooks) {
		return wp.hooks.applyFilters('ep.Autosuggest.suggestions', searchResults, searchTerm);
	}

	return searchResults;
};

/**
 * Apply filters to query parameters
 *
 * @param {object} params - The original query parameters
 * @param {string} searchTerm - The current search term
 * @param {object} filters - Any active filters
 * @returns {object} - The modified query parameters
 */
export const applyQueryParamsFilter = (params, searchTerm, filters = {}) => {
	// Check if wp.hooks is available (WordPress environment)
	if (typeof wp !== 'undefined' && wp.hooks) {
		return wp.hooks.applyFilters('ep.Autosuggest.queryParams', params, searchTerm, filters);
	}

	return params;
};

/**
 * Apply filters to a suggestion item before rendering
 *
 * @param {object} suggestion - The suggestion item data
 * @param {boolean} isActive - Whether this item is currently active/focused
 * @param {Function} onClick - Click handler for the item
 * @returns {object} - The modified suggestion item props
 */
export const applySuggestionItemFilter = (suggestion, isActive, onClick) => {
	if (typeof wp !== 'undefined' && wp.hooks) {
		return wp.hooks.applyFilters(
			'ep.Autosuggest.suggestionItem',
			{ suggestion, isActive, onClick },
			suggestion,
		);
	}

	return { suggestion, isActive, onClick };
};

/**
 * Apply filters to suggestion list props before rendering
 *
 * @param {object} listProps - The suggestion list props
 * @returns {object} - The modified suggestion list props
 */
export const applySuggestionListFilter = (listProps) => {
	if (typeof wp !== 'undefined' && wp.hooks) {
		return wp.hooks.applyFilters('ep.Autosuggest.suggestionList', listProps);
	}

	return listProps;
};
