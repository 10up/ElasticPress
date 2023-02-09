/**
 * WordPress dependencies.
 */
import { useCallback, useEffect, useRef, WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { useApiSearch } from '../../../../api-search';
import { useDebounce } from '../hooks';
import Listbox from './components/listbox';
import ShopOrder from './components/shop-order';

/**
 * Autosuggest app.
 *
 * @param {object} props Component props.
 * @param {string} props.adminUrl Admin URL.
 * @param {string} props.input Input element.
 * @returns {WPElement} Rendered component.
 */
export default ({ adminUrl, input }) => {
	const { clearResults, searchFor, searchResults } = useApiSearch();

	const searchResultsRef = useRef(searchResults);

	searchResultsRef.current = searchResults;

	/**
	 * Selection event handler.
	 *
	 * @param {number} index Selected option index.
	 */
	const onSelect = useCallback(
		(index, isMetaKey) => {
			const { post_id } = searchResultsRef.current[index]._source;

			window.open(`${adminUrl}?post=${post_id}&action=edit`, isMetaKey ? '_blank' : '_self');
		},
		[adminUrl],
	);

	/**
	 * Dispatch the change, with debouncing.
	 */
	const dispatchInput = useDebounce((value) => {
		if (value) {
			searchFor(value);
		} else {
			clearResults();
		}
	}, 300);

	/**
	 * Callback for input keyup event.
	 *
	 * @param {Event} event keyupEvent
	 */
	const onInput = useCallback(
		(event) => {
			dispatchInput(event.target.value);
		},
		[dispatchInput],
	);

	/**
	 * Handle initialization.
	 *
	 * @returns {Function} Cleanup function.
	 */
	const handleInit = () => {
		input.addEventListener('input', onInput);

		return () => {
			input.removeEventListener('input', onInput);
		};
	};

	/**
	 * Effects.
	 */
	useEffect(handleInit, [input, onInput]);

	return (
		<Listbox
			id="ep-orders-suggestions"
			input={input}
			label={__('Search results powered by ElasticPress', 'elasticpress-orders')}
			onSelect={onSelect}
		>
			{searchResults.map((option) => {
				const {
					meta: {
						_billing_email: [{ value: billing_email } = {}] = [],
						_billing_first_name: [{ value: billing_first_name } = {}] = [],
						_billing_last_name: [{ value: billing_last_name } = {}] = [],
						_items: [{ value: items } = {}] = [],
					},
					post_date_gmt,
					post_id,
					post_status,
				} = option._source;

				const orderDate = `${post_date_gmt.split(' ').join('T')}+00:00`;

				return (
					<ShopOrder
						emailAddress={billing_email}
						firstName={billing_first_name}
						id={`ep-order-suggestion-${post_id}`}
						itemCount={items ? items.split('|').length : 0}
						key={post_id}
						lastName={billing_last_name}
						orderDate={orderDate}
						orderNumber={post_id}
						orderStatus={post_status}
					/>
				);
			})}
		</Listbox>
	);
};
