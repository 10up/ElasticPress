/**
 * Child Theme js/index.js
 */

const { createElement: e } = wp.element;

function registerAutosuggestCustomizations() {
	// 1. Hook: ep.Autosuggest.queryParams
	wp.hooks.addFilter(
		'ep.Autosuggest.queryParams',
		'my-theme/filter-search-by-author',
		function (params, searchTerm) {
			const newParams = new URLSearchParams(params.toString());
			if (searchTerm.toLowerCase().includes('by:admin')) {
				newParams.set('author_name', 'admin');
			}
			newParams.set('custom_param', 'custom_value');
			return newParams;
		},
	);

	// 2. Hook: ep.Autosuggest.suggestions
	wp.hooks.addFilter(
		'ep.Autosuggest.suggestions',
		'my-theme/prioritize-pages',
		function (searchResults) {
			if (!Array.isArray(searchResults)) {
				return searchResults;
			}
			const pages = searchResults.filter(
				(item) => item._source && item._source.post_type === 'page',
			);
			const others = searchResults.filter(
				(item) => !item._source || item._source.post_type !== 'page',
			);
			const prioritizedResults = [...pages, ...others];
			return prioritizedResults.map((item) => ({
				...item,
				_source: {
					...item._source,
					customFlag:
						item._source.post_type === 'page' ? 'Priority Content' : 'Standard Content',
				},
			}));
		},
	);

	// 3. Hook: ep.Autosuggest.suggestionItem
	const MyCustomSuggestionItem = (props) => {
		const { suggestion, isActive, onClick } = props;
		const itemClasses = `my-custom-item ${isActive ? 'my-active-item' : ''}`;

		return e(
			'li',
			{
				className: itemClasses,
				role: 'option',
				'aria-selected': isActive,
				id: `custom-suggestion-${suggestion.id}`,
				onMouseDown: onClick,
				tabIndex: -1,
			},
			e(
				'a',
				{ href: suggestion.url },
				// Thumbnail (conditional)
				suggestion.thumbnail &&
					e('img', {
						src: suggestion.thumbnail,
						alt: '',
						className: 'item-thumbnail',
					}),
				// Content div
				e(
					'div',
					{ className: 'item-content' },
					e('strong', { className: 'item-title' }, suggestion.title),
					suggestion.category &&
						e(
							'span',
							{ className: 'item-meta-category' },
							'Category: ',
							suggestion.category,
						),
					e('span', { className: 'item-meta-type' }, 'Type: ', suggestion.type),
					suggestion._source.customFlag &&
						e('p', { className: 'item-custom-flag' }, suggestion._source.customFlag),
				),
			),
		);
	};

	wp.hooks.addFilter(
		'ep.Autosuggest.suggestionItem',
		'my-theme/custom-suggestion-item-renderer',
		function (props) {
			return {
				...props,
				renderSuggestion: () => e(MyCustomSuggestionItem, props),
			};
		},
	);

	// 4. Hook: ep.Autosuggest.suggestionList (Modified to use createElement)
	const MyCustomSuggestionList = (props) => {
		const {
			suggestions,
			activeIndex,
			onItemClick,
			SuggestionItemTemplate,
			showViewAll,
			onViewAll,
			expanded,
		} = props;

		if (!suggestions.length) {
			return null;
		}

		const groupedSuggestions = suggestions.reduce((acc, suggestion) => {
			const type = suggestion.type || 'other';
			if (!acc[type]) {
				acc[type] = [];
			}
			acc[type].push(suggestion);
			return acc;
		}, {});

		return e(
			'div',
			{ className: 'my-custom-suggestion-list-wrapper' },
			e('h3', { className: 'list-main-title' }, 'Custom Search Results'),
			// Map over grouped suggestions
			...Object.entries(groupedSuggestions).map(([type, items]) =>
				e(
					'div',
					{ key: type, className: 'suggestion-group' },
					e(
						'h4',
						{ className: 'suggestion-group-title' },
						`${type.charAt(0).toUpperCase() + type.slice(1)}s (${items.length})`,
					),
					e(
						'ul',
						{ className: 'custom-group-items', role: 'listbox' },
						...items.map((suggestion) => {
							const originalIndex = suggestions.findIndex(
								(s) => s.id === suggestion.id,
							);
							return e(SuggestionItemTemplate, {
								key: suggestion.id,
								suggestion,
								isActive: originalIndex === activeIndex,
								onClick: () => onItemClick(originalIndex),
							});
						}),
					),
				),
			),
			// View All button (conditional)
			showViewAll &&
				e(
					'button',
					{
						className: 'my-custom-view-all',
						onClick: onViewAll,
						type: 'button',
					},
					expanded ? 'View Less Results' : 'View All Results',
				),
		);
	};

	wp.hooks.addFilter(
		'ep.Autosuggest.suggestionList',
		'my-theme/custom-suggestion-list-renderer',
		function (listProps) {
			return {
				...listProps,
				renderSuggestionList: () => e(MyCustomSuggestionList, listProps),
			};
		},
	);

	// 5. Hook: ep.Autosuggest.onItemClick (Action Hook - No changes from previous example)
	wp.hooks.addAction(
		'ep.Autosuggest.onItemClick',
		'my-theme/track-suggestion-clicks',
		function (suggestion, index, inputValue) {
			// eslint-disable-next-line no-console
			console.log('Suggestion Clicked (Action Hook):', {
				title: suggestion.title,
				url: suggestion.url,
				type: suggestion.type,
				index,
				searchTerm: inputValue,
			});
		},
	);
}

document.addEventListener('ep_autosuggest_loaded', registerAutosuggestCustomizations);
