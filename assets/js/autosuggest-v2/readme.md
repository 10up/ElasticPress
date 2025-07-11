# ElasticPress Autosuggest V2 - Hooks Documentation

This document outlines the available hooks and filters for customizing the ElasticPress Autosuggest V2 feature.

## PHP Hooks

### `ep_autosuggest_v2_per_page`

**Type**: Filter  
**Description**: Modifies the number of suggestions displayed per page in autosuggest results.

**Parameters**:

-   `$default_per_page_value` (int): The default number of results per page

**Example**:

```php
add_filter( 'ep_autosuggest_v2_per_page', function( $default_per_page_value ) {
    return 8; // Show 8 suggestions instead of default
} );

```

----------

## JavaScript Hooks

### `ep.Autosuggest.queryParams`

**Type**: Filter  
**Description**: Modifies the query parameters sent to ElasticSearch before the search is executed.

**Parameters**:

-   `params` (URLSearchParams): The original query parameters
-   `searchTerm` (string): The current search term
-   `filters` (object): Any applied filters

**Returns**: URLSearchParams object with modified parameters

**Example**:

```javascript
wp.hooks.addFilter(
    'ep.Autosuggest.queryParams',
    'my-theme/filter-search-by-author',
    function (params, searchTerm, filters) {
        const newParams = new URLSearchParams(params.toString());
        
        // Add author filter if search term includes "by:admin"
        if (searchTerm.toLowerCase().includes('by:admin')) {
            newParams.set('author_name', 'admin');
        }
        
        // Add custom parameter
        newParams.set('custom_param', 'custom_value');
        
        return newParams;
    }
);

```

### `ep.Autosuggest.suggestions`

**Type**: Filter  
**Description**: Modifies the search results/suggestions after they are retrieved but before they are rendered.

**Parameters**:

-   `searchResults` (array): Array of search result objects
-   `searchTerm` (string): The current search term

**Returns**: Modified array of search results

**Example**:

```javascript
wp.hooks.addFilter(
    'ep.Autosuggest.suggestions',
    'my-theme/prioritize-pages',
    function (searchResults, searchTerm) {
        if (!Array.isArray(searchResults)) {
            return searchResults;
        }
        
        // Separate pages from other content types
        const pages = searchResults.filter(item => 
            item._source && item._source.post_type === 'page'
        );
        const others = searchResults.filter(item => 
            !item._source || item._source.post_type !== 'page'
        );
        
        // Prioritize pages and add custom flags
        const prioritizedResults = [...pages, ...others];
        
        return prioritizedResults.map(item => ({
            ...item,
            _source: {
                ...item._source,
                customFlag: item._source.post_type === 'page' 
                    ? 'Priority Content' 
                    : 'Standard Content',
            },
        }));
    }
);

```

### `ep.Autosuggest.suggestionItem`

**Type**: Filter  
**Description**: Customizes the rendering of individual suggestion items in the dropdown.

**Parameters**:

-   `props` (object): Contains suggestion data, isActive state, and onClick handler
-   `originalSuggestion` (object): The original suggestion object

**Returns**: Modified props object with custom renderSuggestion function

**Example**:

```javascript
const MyCustomSuggestionItem = (props) => {
    const { suggestion, isActive, onClick } = props;
    const itemClasses = `my-custom-item ${isActive ? 'my-active-item' : ''}`;
    
    return (
        <li
            className={itemClasses}
            role="option"
            aria-selected={isActive}
            id={`custom-suggestion-${suggestion.id}`}
            onMouseDown={onClick}
            tabIndex={-1}
        >
            <a href={suggestion.url}>
                {suggestion.thumbnail && (
                    <img src={suggestion.thumbnail} alt="" className="item-thumbnail" />
                )}
                <div className="item-content">
                    <strong className="item-title">{suggestion.title}</strong>
                    {suggestion.category && (
                        <span className="item-meta-category">
                            Category: {suggestion.category}
                        </span>
                    )}
                    <span className="item-meta-type">Type: {suggestion.type}</span>
                    {suggestion._source.customFlag && (
                        <p className="item-custom-flag">
                            {suggestion._source.customFlag}
                        </p>
                    )}
                </div>
            </a>
        </li>
    );
};

wp.hooks.addFilter(
    'ep.Autosuggest.suggestionItem',
    'my-theme/custom-suggestion-item-renderer',
    function (props, originalSuggestion) {
        return {
            ...props,
            renderSuggestion: () => <MyCustomSuggestionItem {...props} />,
        };
    }
);

```

### `ep.Autosuggest.suggestionList`

**Type**: Filter  
**Description**: Customizes the entire suggestion list container and layout.

**Parameters**:

-   `listProps` (object): Contains suggestions array, activeIndex, click handlers, and other list properties

**Returns**: Modified listProps object with custom renderSuggestionList function

**Example**:

```javascript
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

    // Group suggestions by type
    const groupedSuggestions = suggestions.reduce((acc, suggestion) => {
        const type = suggestion.type || 'other';
        if (!acc[type]) {
            acc[type] = [];
        }
        acc[type].push(suggestion);
        return acc;
    }, {});

    return (
        <div className="my-custom-suggestion-list-wrapper">
            <h3 className="list-main-title">Custom Search Results</h3>
            {Object.entries(groupedSuggestions).map(([type, items]) => (
                <div key={type} className="suggestion-group">
                    <h4 className="suggestion-group-title">
                        {type.charAt(0).toUpperCase() + type.slice(1)}s ({items.length})
                    </h4>
                    <ul className="custom-group-items" role="listbox">
                        {items.map((suggestion) => {
                            const originalIndex = suggestions.findIndex(s => s.id === suggestion.id);
                            return (
                                <SuggestionItemTemplate
                                    key={suggestion.id}
                                    suggestion={suggestion}
                                    isActive={originalIndex === activeIndex}
                                    onClick={() => onItemClick(originalIndex)}
                                />
                            );
                        })}
                    </ul>
                </div>
            ))}
            {showViewAll && (
                <button
                    className="my-custom-view-all"
                    onClick={onViewAll}
                    type="button"
                >
                    {expanded ? 'View Less Results' : 'View All Results'}
                </button>
            )}
        </div>
    );
};

wp.hooks.addFilter(
    'ep.Autosuggest.suggestionList',
    'my-theme/custom-suggestion-list-renderer',
    function (listProps) {
        return {
            ...listProps,
            renderSuggestionList: () => <MyCustomSuggestionList {...listProps} />,
        };
    }
);

```

### `ep.Autosuggest.onItemClick`

**Type**: Action Hook  
**Description**: Fires when a user clicks on a suggestion item. Useful for analytics and tracking.

**Parameters**:

-   `suggestion` (object): The clicked suggestion object
-   `index` (number): The index of the clicked suggestion
-   `inputValue` (string): The current search term

**Example**:

```javascript
wp.hooks.addAction(
    'ep.Autosuggest.onItemClick',
    'my-theme/track-suggestion-clicks',
    function (suggestion, index, inputValue) {
        console.log('Suggestion Clicked (Action Hook):', {
            title: suggestion.title,
            url: suggestion.url,
            type: suggestion.type,
            index: index,
            searchTerm: inputValue,
        });
        
        // Send to analytics service
        // gtag('event', 'autosuggest_click', { ... });
    }
);

```

## Event Listener

The customizations should be registered when the ElasticPress Autosuggest V2 is fully loaded:

```javascript
document.addEventListener('ep_autosuggest_loaded', registerAutosuggestCustomizations);

```

This ensures that all the necessary components are available before attempting to modify them.
