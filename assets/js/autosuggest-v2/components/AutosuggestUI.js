import { useState } from 'react';
import { useApiSearch } from '../../api-search';
import SuggestionItem from './SuggestionItem'; // Original SuggestionItem component
import SuggestionList from './SuggestionList';
import { useKeyboardInput } from '../hooks';

const AutosuggestUI = ({ inputEl, minLength = 2 }) => {
	const { searchResults, searchFor } = useApiSearch();

	const [inputValue, setInputValue] = useState('');
	const [activeIndex, setActiveIndex] = useState(-1);
	const [show, setShow] = useState(false);

	const suggestions = (searchResults || []).map((hit) => ({
		id: hit._source.ID,
		title: hit._source.post_title,
		url: hit._source.permalink,
		type: hit._source.post_type,
		thumbnail: typeof hit._source.thumbnail === 'string' ? hit._source.thumbnail : null,
		category: hit._source.category || null,
		_source: hit._source,
	}));

	useKeyboardInput({
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

	const handleItemClick = (idx) => {
		if (typeof wp !== 'undefined' && wp.hooks) {
			wp.hooks.doAction('ep.Autosuggest.onItemClick', suggestions[idx], idx, inputValue);
		}
		window.location.href = suggestions[idx].url;
		setShow(false);
	};

	const suggestionListProps = {
		suggestions,
		activeIndex,
		onItemClick: handleItemClick,
		SuggestionItemTemplate: SuggestionItem,
	};

	return (
		<div className="ep-autosuggest">
			{show && suggestions.length > 0 && <SuggestionList {...suggestionListProps} />}
		</div>
	);
};

export default AutosuggestUI;
