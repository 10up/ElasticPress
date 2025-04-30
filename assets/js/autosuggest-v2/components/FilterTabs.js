// Filter tabs component for content type filtering
const FilterTabs = ({ activeFilter, onFilterChange }) => {
	// Filter UI (Articles, Videos, Discussions)
	const filterTabs = [
		{ label: window.epasI18n?.articles || 'Articles', value: 'article' },
		{ label: window.epasI18n?.videos || 'Videos', value: 'video' },
		{
			label: window.epasI18n?.discussions || 'Discussions',
			value: 'discussion',
		},
	];

	return (
		<div
			className="ep-autosuggest-filters"
			style={{
				display: 'flex',
				gap: '8px',
				marginBottom: '8px',
			}}
		>
			{filterTabs.map((tab) => (
				<button
					key={tab.value}
					className={`ep-autosuggest-filter${activeFilter === tab.value ? ' active' : ''}`}
					onClick={() => onFilterChange(tab.value)}
					type="button"
				>
					{tab.label}
				</button>
			))}
			<button
				className={`ep-autosuggest-filter${!activeFilter ? ' active' : ''}`}
				onClick={() => onFilterChange(null)}
				type="button"
			>
				{window.epasI18n?.all || 'All'}
			</button>
		</div>
	);
};

export default FilterTabs;
