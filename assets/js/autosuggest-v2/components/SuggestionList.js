import { applySuggestionListFilter } from '../hooks';

const SuggestionList = (props) => {
	const filteredProps = applySuggestionListFilter(props);
	if (filteredProps.renderSuggestionList) {
		return filteredProps.renderSuggestionList();
	}

	const { suggestions, activeIndex, onItemClick, SuggestionItemTemplate } = filteredProps;

	return (
		<div className="ep-autosuggest-list-wrapper">
			<div className="ep-autosuggest-header">
				{window.epasI18n?.searchIn && <span>{window.epasI18n?.searchIn}</span>}
			</div>
			<ul className="autosuggest-list" role="listbox">
				{suggestions.map((suggestion, idx) => (
					<SuggestionItemTemplate
						key={suggestion.id}
						suggestion={suggestion}
						isActive={idx === activeIndex}
						onClick={() => onItemClick(idx)}
					/>
				))}
			</ul>
		</div>
	);
};

export default SuggestionList;
