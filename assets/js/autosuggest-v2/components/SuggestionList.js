import { applySuggestionListFilter } from '../src/hooks';

// Default Suggestion List Template
const SuggestionList = (props) => {
	// Apply filters to props
	const filteredProps = applySuggestionListFilter(props);

	// Check if a custom renderer was provided through the filter
	if (filteredProps.renderSuggestionList) {
		return filteredProps.renderSuggestionList();
	}

	// Otherwise, use the default rendering
	const {
		suggestions,
		activeIndex,
		onItemClick,
		SuggestionItemTemplate,
		showViewAll,
		onViewAll,
		expanded,
	} = filteredProps;

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
			{showViewAll && (
				<button className="ep-autosuggest-view-all" onClick={onViewAll} type="button">
					{expanded
						? window.epasI18n?.viewLess || 'View less results'
						: window.epasI18n?.viewAll || 'View all results'}
				</button>
			)}
		</div>
	);
};

export default SuggestionList;
