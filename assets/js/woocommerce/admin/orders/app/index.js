/**
 * WordPress dependencies.
 */
import { useCallback, WPElement } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { useApiSearch } from '../../../../api-search';
import { useDebounce } from '../hooks';
import Combobox from './components/combobox';
import Suggestion from './components/suggestion';

/**
 * Autosuggest app.
 *
 * @param {object} props Component props.
 * @param {string} props.adminUrl Admin URL.
 * @param {string} props.dateFormat Date format string.
 * @param {object} props.statusLabels Post status labels.
 * @param {string} props.timeFormat Time format string.
 * @param {string} props.value Input value.
 * @returns {WPElement} Rendered component.
 */
export default ({ adminUrl, dateFormat, statusLabels, timeFormat, value, ...props }) => {
	/**
	 * Use the API Search provider.
	 */
	const { clearResults, isLoading, searchFor, searchResults } = useApiSearch();

	/**
	 * Update the search term, debounced.
	 *
	 * @param {string} searchTerm Search term.
	 * @returns {void}
	 */
	const dispatchSearchFor = useDebounce((searchTerm) => {
		searchFor(searchTerm);
	}, 300);

	/**
	 * Input event handler.
	 *
	 * @param {Event} event keyupEvent
	 */
	const onInput = useCallback(
		(event) => {
			const { value } = event.target;

			if (value) {
				dispatchSearchFor(event.target.value);
			} else {
				clearResults();
			}
		},
		[clearResults, dispatchSearchFor],
	);

	/**
	 * Selection event handler.
	 *
	 * Fires when a suggestion from the combobox component is selected. The
	 * value will be the value prop of the child that was selected.
	 *
	 * @param {*} value Selection value.
	 * @param {Event} event Click or keydown event.
	 */
	const onSelect = useCallback(
		(value, event) => {
			window.open(
				`${adminUrl}?post=${value}&action=edit`,
				event.metaKey ? '_blank' : '_self',
			);
		},
		[adminUrl],
	);

	/**
	 * Render.
	 */
	return (
		<Combobox
			defaultValue={value}
			id="ep-orders-suggestions"
			isBusy={isLoading}
			onInput={onInput}
			onSelect={onSelect}
			{...props}
		>
			{searchResults.map((hit) => {
				const {
					_id,
					_source: { post_id },
				} = hit;

				return (
					<Suggestion
						dateFormat={dateFormat}
						id={`ep-order-suggestion-${_id}`}
						hit={hit}
						key={_id}
						statusLabels={statusLabels}
						timeFormat={timeFormat}
						value={post_id}
					/>
				);
			})}
		</Combobox>
	);
};
