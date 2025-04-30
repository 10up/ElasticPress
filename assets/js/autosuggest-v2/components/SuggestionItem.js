// Default Suggestion Item Template
const SuggestionItem = ({ suggestion, isActive, onClick }) => (
	<li
		className={`autosuggest-item${isActive ? ' selected' : ''}`}
		role="option"
		aria-selected={isActive}
		id={`autosuggest-option-${suggestion.id}`}
		onMouseDown={onClick}
		tabIndex={-1}
	>
		<a href={suggestion.url}>
			{suggestion.thumbnail && (
				<img src={suggestion.thumbnail} alt="" className="autosuggest-thumb" />
			)}
			<span className="autosuggest-title">{suggestion.title}</span>
			{suggestion.category && (
				<span className="autosuggest-category">{suggestion.category}</span>
			)}
		</a>
	</li>
);

export default SuggestionItem;
