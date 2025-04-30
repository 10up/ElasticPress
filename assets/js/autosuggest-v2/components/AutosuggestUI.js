import { useContext, useState, useEffect } from 'react';
import { useApiSearch } from '../../api-search';
import { AutosuggestContext } from '../src/context';
import SuggestionItem from './SuggestionItem';
import SuggestionList from './SuggestionList';
import FilterTabs from './FilterTabs';
import useAutosuggestInput from '../src/useAutosuggestInput';

// Main Autosuggest UI component
const AutosuggestUI = ({
	inputEl, // DOM node of the input
	minLength = 2,
	perPage = 3,
	// ...props
}) => {
	const { searchResults, searchFor } = useApiSearch();

	const [inputValue, setInputValue] = useState('');
	const [activeIndex, setActiveIndex] = useState(-1);
	const [show, setShow] = useState(false);
	// eslint-disable-next-line @wordpress/no-unused-vars-before-return
	const [expanded, setExpanded] = useState(false);
	const [activeFilter, setActiveFilter] = useState(null);

	const { SuggestionItemTemplate = SuggestionItem, SuggestionListTemplate = SuggestionList } =
		useContext(AutosuggestContext);

	// Map searchResults to suggestions, filter by activeFilter if set
	const suggestions = (searchResults || [])
		.filter((hit) => !activeFilter || hit._source.type === activeFilter)
		.map((hit) => ({
			id: hit._source.ID,
			title: hit._source.post_title,
			url: hit._source.permalink,
			// thumbnail: hit._source.thumbnail,
			// category: hit.category,
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
		searchFor,
	});

	const handleFilterChange = (filterValue) => {
		setActiveFilter(filterValue);
		setExpanded(false);
	};

	const handleItemClick = (idx) => {
		window.location.href = suggestions[idx].url;
		setShow(false);
	};

	const handleClose = () => setShow(false);

	const handleViewAll = () => setExpanded((prev) => !prev);

	// Position the dropdown absolutely under the input
	// eslint-disable-next-line @wordpress/no-unused-vars-before-return
	const [dropdownStyle, setDropdownStyle] = useState({});
	useEffect(() => {
		if (!inputEl || !show) return;
		const rect = inputEl.getBoundingClientRect();
		setDropdownStyle({
			position: 'absolute',
			top: rect.bottom + window.scrollY,
			left: rect.left + window.scrollX,
			width: rect.width,
			zIndex: 100,
		});
	}, [inputEl, show, inputValue]);

	// Only show dropdown if show is true and there are suggestions
	if (!show || suggestions.length === 0) {
		return null;
	}

	return (
		<div className="ep-autosuggest" style={dropdownStyle}>
			<FilterTabs activeFilter={activeFilter} onFilterChange={handleFilterChange} />
			<SuggestionListTemplate
				suggestions={expanded ? suggestions : suggestions.slice(0, perPage)}
				activeIndex={activeIndex}
				onItemClick={handleItemClick}
				onClose={handleClose}
				SuggestionItemTemplate={SuggestionItemTemplate}
				showViewAll={suggestions.length > perPage}
				onViewAll={handleViewAll}
				expanded={expanded}
			/>
		</div>
	);
};

export default AutosuggestUI;
