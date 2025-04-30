import React from 'react';

// Context for extensibility - allows custom templates for suggestions
export const AutosuggestContext = React.createContext({
	SuggestionItemTemplate: null,
	SuggestionListTemplate: null,
});
