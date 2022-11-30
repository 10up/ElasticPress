/**
 * WordPress dependencies.
 */
import { addFilter } from '@wordpress/hooks';

window.addEventListener('load', () => {
	if (typeof window.epDataFilter !== 'undefined') {
		addFilter('ep.Autosuggest.data', 'ep/epDatafilter', window.epDatafilter);
	}

	if (typeof window.epAutosuggestItemHTMLFilter !== 'undefined') {
		addFilter(
			'ep.Autosuggest.itemHTML',
			'ep/epAutosuggestItemHTMLFilter',
			window.epAutosuggestItemHTMLFilter,
		);
	}

	if (typeof window.epAutosuggestListItemsHTMLFilter !== 'undefined') {
		addFilter(
			'ep.Autosuggest.listHTML',
			'ep/epAutosuggestListItemsHTMLFilter',
			window.epAutosuggestListItemsHTMLFilter,
		);
	}

	if (typeof window.epAutosuggestQueryFilter !== 'undefined') {
		addFilter(
			'ep.Autosuggest.query',
			'ep/epAutosuggestQueryFilter',
			window.epAutosuggestQueryFilter,
		);
	}

	if (typeof window.epAutosuggestElementFilter !== 'undefined') {
		addFilter(
			'ep.Autosuggest.element',
			'ep/epAutosuggestElementFilter',
			window.epAutosuggestElementFilter,
		);
	}
});
