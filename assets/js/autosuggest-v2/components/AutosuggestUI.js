import { useContext, useState, useEffect } from 'react';
import { useApiSearch } from '../../api-search';
import { AutosuggestContext } from '../src/context';
import SuggestionItem from './SuggestionItem';
import SuggestionList from './SuggestionList';
import useAutosuggestInput from '../src/useAutosuggestInput';

// Main Autosuggest UI component
const AutosuggestUI = ({
	inputEl, // DOM node of the input
	minLength = 2,
	perPage = 3,
	// ...props
}) => {
	// Use enhanced context with additional methods from API Search Provider
	const {
		searchResults,
		searchFor,
		activeFilters: contextFilters = {},
		updateFilters,
	} = useApiSearch();

	const [inputValue, setInputValue] = useState('');
	const [activeIndex, setActiveIndex] = useState(-1);
	const [show, setShow] = useState(false);
	const [expanded, setExpanded] = useState(false);

	// Local state for active filters, synced with context
	const [activeFilters, setActiveFilters] = useState(contextFilters);
	const [activeTypeFilter, setActiveTypeFilter] = useState(null);

	// Get customized templates from context
	const { SuggestionItemTemplate = SuggestionItem, SuggestionListTemplate = SuggestionList } =
		useContext(AutosuggestContext);

	// Sync local filters with context filters when they change
	useEffect(() => {
		setActiveFilters(contextFilters);
	}, [contextFilters]);

	// Map searchResults to suggestions, applying any active filters
	const suggestions = (searchResults || [])
		// First apply type filter if active
		.filter((hit) => !activeTypeFilter || hit._source.post_type === activeTypeFilter)
		// Then map to the format expected by suggestion components
		.map((hit) => ({
			id: hit._source.ID,
			title: hit._source.post_title,
			url: hit._source.permalink,
			type: hit._source.post_type,
			thumbnail: hit._source.thumbnail || null,
			category: hit._source.category || null,
			// Include original source data for advanced filtering
			_source: hit._source,
		}));

	// Handle input events and keyboard navigation
	useAutosuggestInput({
		inputEl,
		minLength,
		show,
		setShow,
		inputValue,
		setInputValue,
		suggestions,
		activeIndex,
		setActiveIndex,
		// Pass search function that includes filters
		searchFor: (term) => searchFor(term, activeFilters),
	});

	/**
	 * Update a specific filter and trigger search
	 *
	 * @param {string} filterKey - The filter key to update
	 * @param {any} filterValue - The filter value
	 */
	const handleFilterChange = (filterKey, filterValue) => {
		// Update local state
		const newFilters = {
			...activeFilters,
			[filterKey]: filterValue,
		};

		// Update local state
		setActiveFilters(newFilters);

		// If this is a type filter, also update the UI state
		if (filterKey === 'post_type') {
			setActiveTypeFilter(filterValue);
		}

		// If expanded, collapse the view
		if (expanded) {
			setExpanded(false);
		}

		// Update context state and trigger search
		if (typeof updateFilters === 'function') {
			updateFilters(newFilters);
		}

		// If we have a search term, trigger a new search with the updated filters
		if (inputValue && inputValue.length >= minLength) {
			searchFor(inputValue, newFilters);
		}
	};

	/**
	 * Reset all filters to default values
	 */
	const resetFilters = () => {
		setActiveFilters({});
		setActiveTypeFilter(null);

		if (typeof updateFilters === 'function') {
			updateFilters({});
		}

		// Re-search with cleared filters
		if (inputValue && inputValue.length >= minLength) {
			searchFor(inputValue, {});
		}
	};

	/**
	 * Handle click on a suggestion item
	 *
	 * @param {number} idx - Index of the clicked suggestion
	 */
	const handleItemClick = (idx) => {
		// Trigger WordPress hook before navigation if hooks available
		if (typeof wp !== 'undefined' && wp.hooks) {
			wp.hooks.doAction('ep.Autosuggest.onItemClick', suggestions[idx], idx, inputValue);
		}

		// Navigate to the suggestion URL
		window.location.href = suggestions[idx].url;
		setShow(false);
	};

	/**
	 * Toggle expanded view (show all results)
	 * @function
	 * @description Switches between condensed and expanded view modes
	 * @returns {void} Updates the expanded state by toggling the previous value
	 */
	const handleViewAll = () => setExpanded((prev) => !prev);

	// Only show dropdown if show is true and there are suggestions
	if (!show || suggestions.length === 0) {
		return null;
	}

	// Prepare the filtered suggestions to pass to the list template
	const displayedSuggestions = expanded ? suggestions : suggestions.slice(0, perPage);

	// Assemble props for the suggestion list
	const suggestionListProps = {
		suggestions: displayedSuggestions,
		activeIndex,
		onItemClick: handleItemClick,
		SuggestionItemTemplate,
		showViewAll: suggestions.length > perPage,
		onViewAll: handleViewAll,
		expanded,
		activeFilters,
		onFilterChange: handleFilterChange,
		onResetFilters: resetFilters,
	};

	return (
		<div className="ep-autosuggest">
			<SuggestionListTemplate {...suggestionListProps} />
		</div>
	);
};

export default AutosuggestUI;
