import { useEffect } from 'react';
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

/**
 * Attaches autosuggest behavior and keyboard navigation to an input element.
 *
 * @param {object} options - Hook options.
 * @param {HTMLInputElement|null} options.inputEl - Input element to attach handlers to.
 * @param {number} options.minLength - Minimum characters before triggering a search.
 * @param {boolean} options.show - Whether the suggestions dropdown is visible.
 * @param {function(boolean):void} options.setShow - Setter for dropdown visibility.
 * @param {string} options.inputValue - Current value of the input field.
 * @param {function(string):void} options.setInputValue - Setter for the input value state.
 * @param {Array<{url: string}>} options.suggestions - Array of suggestion objects.
 * @param {number} options.activeIndex - Index of the currently highlighted suggestion.
 * @param {function(number):void} options.setActiveIndex - Setter for the active suggestion index.
 * @param {function(string):void} options.searchFor - Function to perform the search given input.
 * @returns {void}
 *
 */
export const useKeyboardInput = ({
	inputEl,
	minLength,
	show,
	setShow,
	inputValue,
	setInputValue,
	suggestions,
	activeIndex,
	setActiveIndex,
	searchFor,
}) => {
	useEffect(() => {
		if (!inputEl) {
			return () => {};
		}

		const handleInputChange = (e) => {
			const { value } = e.target;
			setInputValue(value);
			setActiveIndex(-1);
			if (value.length >= minLength) {
				searchFor(value);
				setShow(true);
			} else {
				setShow(false);
			}
		};

		const handleKeyDown = (e) => {
			if (!show) return;
			if (e.key === 'ArrowDown') {
				setActiveIndex((prev) => Math.min(prev + 1, suggestions.length - 1));
				e.preventDefault();
			} else if (e.key === 'ArrowUp') {
				setActiveIndex((prev) => Math.max(prev - 1, 0));
				e.preventDefault();
			} else if (e.key === 'Enter' && activeIndex >= 0) {
				window.location.href = suggestions[activeIndex].url;
				setShow(false);
			} else if (e.key === 'Escape') {
				setShow(false);
			}
		};

		const handleFocus = () => {
			if (inputEl.value.length >= minLength && suggestions.length > 0) {
				setShow(true);
			}
		};

		const handleBlur = () => setTimeout(() => setShow(false), 200);

		inputEl.addEventListener('input', handleInputChange);
		inputEl.addEventListener('keydown', handleKeyDown);
		inputEl.addEventListener('focus', handleFocus);
		inputEl.addEventListener('blur', handleBlur);

		return () => {
			inputEl.removeEventListener('input', handleInputChange);
			inputEl.removeEventListener('keydown', handleKeyDown);
			inputEl.removeEventListener('focus', handleFocus);
			inputEl.removeEventListener('blur', handleBlur);
		};
	}, [
		inputEl,
		minLength,
		show,
		suggestions,
		activeIndex,
		setActiveIndex,
		setInputValue,
		setShow,
		searchFor,
	]);

	// Keep input value in sync if changed externally
	useEffect(() => {
		if (inputEl && inputEl.value !== inputValue) {
			setInputValue(inputEl.value);
		}
	}, [inputEl, inputValue, setInputValue]);
};
