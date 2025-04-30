// Default Suggestion List Template
const SuggestionList = ({
	suggestions,
	activeIndex,
	onItemClick,
	onClose,
	SuggestionItemTemplate,
	showViewAll,
	onViewAll,
	expanded,
}) => (
	<div className="ep-autosuggest-list-wrapper">
		<div className="ep-autosuggest-header">
			<span>{window.epasI18n?.searchIn || 'Search in'}</span>
			<button
				aria-label={window.epasI18n?.close || 'Close'}
				className="ep-autosuggest-close"
				onClick={onClose}
				type="button"
			>
				×
			</button>
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

export default SuggestionList;
