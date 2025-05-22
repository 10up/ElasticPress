There are hooks available for use to provide developer customization. You can insert the following hooks into a theme or plugin to customize the query, filter the results, override the markup, or trigger an action when a result is clicked.

    import './style.css';
    
    /**
     * Available JS hooks:
     * - ep.Autosuggest.queryParams - Modify search query parameters
     * - ep.Autosuggest.suggestions - Filter search results
     * - ep.Autosuggest.suggestionItem - Customize individual suggestion item rendering
     * - ep.Autosuggest.suggestionList - Customize suggestion list rendering
     * - ep.Autosuggest.onItemClick - Triggered when a suggestion item is clicked
	 *
	 * Available PHP hooks:
	 * - ep_autosuggest_v2_per_page - Change the number of results returned
     * 
     * Available Events:
     * - ep_autosuggest_loaded - window.EPAutosuggest is available - safe to hook into ui overrides
     */
    
    /**
     * Hook: ep.Autosuggest.queryParams
     * Example: Only show "post" post_types in the search results.
     * Status: Works
     */
    const enableOnlyShowPosts = false;
    if (enableOnlyShowPosts) {
        wp.hooks.addFilter(
            "ep.Autosuggest.queryParams",
            "my-theme/filter-post-types",
            function (params, searchTerm, filters) {
                const newParams = new URLSearchParams(params.toString());
                newParams.set("post_type", "post");
                return newParams;
            }
        );
    }
    
    /**
     * Hook: ep.Autosuggest.suggestions
     * Example: Only show posts with thumbnails
     * Status: Works
     */
    const enableOnlyPostsWithThumbnails = false;
    if (enableOnlyPostsWithThumbnails) {
        wp.hooks.addFilter(
            "ep.Autosuggest.suggestions",
            "my-theme/only-with-thumbnails",
            function (searchResults, searchTerm) {
                return searchResults.filter(
                    (item) =>
                        item._source.thumbnail && item._source.thumbnail !== ""
                );
            }
        );
    }
    
    /**
     * Hook: ep.Autosuggest.suggestions
     * Example: Add some extra data to the source object (could be output later with a suggestionItem hook)
     * Status: Works
     */
    const enableEnhanceResults = false;
    if (enableEnhanceResults) {
        wp.hooks.addFilter(
            "ep.Autosuggest.suggestions",
            "my-theme/enhance-results",
            function (searchResults, searchTerm) {
                return searchResults.map((item) => ({
                    ...item,
                    _source: {
                        ...item._source,
                        relevanceScore: 100,
                        highlightedTitle: item._source.post_title,
                    },
                }));
            }
        );
    }
    
    /**
     * Hook: ep.Autosuggest.onItemClick
     * Example: Console log data on click (could be used for GA tracking)
     * Status: Works
     */
    const enableAddClickHook = false;
    if (enableAddClickHook) {
        wp.hooks.addAction(
            "ep.Autosuggest.onItemClick",
            "my-theme/track-clicks",
            function (suggestion, index, searchTerm) {
                console.log("on click:", suggestion, index, searchTerm);
            }
        );
    }
    
    /**
     * Hook: ep.Autosuggest.suggestionItem
     * Example: Custom UI Overrides
     * Status: Works
     */
    const registerCustomSuggestionItem = () => {
        const CustomSuggestionItem = (props) => {
            const { suggestion, isActive, onClick } = props;
            return (
                <li
                    className={`custom-suggestion ${isActive ? "active" : ""}`}
                    role="option"
                    aria-selected={isActive}
                    id={`suggestion-${suggestion.id}`}
                    onMouseDown={onClick}
                    tabIndex={-1}
                >
                    <a href={suggestion.url}>
                        <div className="suggestion-content">
                            {suggestion.thumbnail && (
                                <img
                                    src={suggestion.thumbnail}
                                    alt=""
                                    className="thumb"
                                />
                            )}
                            <div className="suggestion-text">
                                <strong>Title Here!!! : {suggestion.title}</strong>
                                {suggestion.excerpt && <p>{suggestion.excerpt}</p>}
                                {suggestion.type && (
                                    <span className="content-type">
                                        {suggestion.type}
                                    </span>
                                )}
                            </div>
                        </div>
                    </a>
                </li>
            );
        };
        wp.hooks.addFilter(
            "ep.Autosuggest.suggestionItem",
            "my-theme/custom-suggestion-item",
            function (props, originalSuggestion) {
                if (!originalSuggestion || !originalSuggestion._source) {
                    return props;
                }
                return {
                    ...props,
                    renderSuggestion: () => <CustomSuggestionItem {...props} />,
                };
            },
            5
        );
    };
    document.addEventListener("ep_autosuggest_loaded", function () {
        registerCustomSuggestionItem();
    });
    
    /**
     * Hook: ep.Autosuggest.suggestionList
     * Example: Custom UI Overrides
     * Status: Works
     */
    const registerCustomSuggestionList = () => {
        const CustomSuggestionList = (props) => {
            const {
                suggestions,
                activeIndex,
                onItemClick,
                SuggestionItemTemplate,
                showViewAll,
                onViewAll,
                expanded,
            } = props;
    
            // Group suggestions by type
            const groupedSuggestions = {};
            suggestions.forEach((suggestion) => {
                const type = suggestion.type || "other";
                if (!groupedSuggestions[type]) {
                    groupedSuggestions[type] = [];
                }
                groupedSuggestions[type].push(suggestion);
            });
    
            return (
                <div className="custom-suggestion-list">
                    <div className="custom-header">
                        <h3>Search Results ({suggestions.length})</h3>
                    </div>
                    {Object.entries(groupedSuggestions).map(([type, items]) => (
                        <div key={type} className="suggestion-group">
                            <h4 className="group-title">
                                {type.charAt(0).toUpperCase() + type.slice(1)}s
                            </h4>
                            <ul className="group-items" role="listbox">
                                {items.map((suggestion, idx) => (
                                    <SuggestionItemTemplate
                                        key={suggestion.id}
                                        suggestion={suggestion}
                                        isActive={
                                            suggestions.indexOf(suggestion) ===
                                            activeIndex
                                        }
                                        onClick={() =>
                                            onItemClick(
                                                suggestions.indexOf(suggestion)
                                            )
                                        }
                                    />
                                ))}
                            </ul>
                        </div>
                    ))}
                    {showViewAll && (
                        <button
                            className="custom-view-all"
                            onClick={onViewAll}
                            type="button"
                        >
                            {expanded ? "Show Less" : "Show All Results"}
                        </button>
                    )}
                </div>
            );
        };
        wp.hooks.addFilter(
            "ep.Autosuggest.suggestionList",
            "my-theme/custom-suggestion-list",
            function (props) {
                return {
                    ...props,
                    renderSuggestionList: () => <CustomSuggestionList {...props} />,
                };
            },
            5
        );
    };
    document.addEventListener("ep_autosuggest_loaded", function () {
        registerCustomSuggestionList();
    });

