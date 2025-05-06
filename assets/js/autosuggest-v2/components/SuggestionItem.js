import { applySuggestionItemFilter } from '../src/hooks';

// Default Suggestion Item Template
const SuggestionItem = ({ suggestion, isActive, onClick }) => {
	// Apply filters to props
	const filteredProps = applySuggestionItemFilter(suggestion, isActive, onClick);

	// Check if a custom renderer was provided through the filter
	if (filteredProps.renderSuggestion) {
		return filteredProps.renderSuggestion();
	}

	// Otherwise, use the default rendering
	const {
		suggestion: filteredSuggestion,
		isActive: filteredIsActive,
		onClick: filteredOnClick,
	} = filteredProps;

	return (
		<li
			className={`autosuggest-item${filteredIsActive ? ' selected' : ''}`}
			role="option"
			aria-selected={filteredIsActive}
			id={`autosuggest-option-${filteredSuggestion.id}`}
			onMouseDown={filteredOnClick}
			tabIndex={-1}
		>
			<a href={filteredSuggestion.url}>
				{filteredSuggestion.thumbnail && (
					<img src={filteredSuggestion.thumbnail} alt="" className="autosuggest-thumb" />
				)}
				<span className="autosuggest-title">{filteredSuggestion.title}</span>
				{filteredSuggestion.category && (
					<span className="autosuggest-category">{filteredSuggestion.category}</span>
				)}
			</a>
		</li>
	);
};

export default SuggestionItem;
