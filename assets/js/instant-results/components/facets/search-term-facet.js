/**
 * WordPress dependencies.
 */
import { useEffect, useState, WPElement } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { useApiSearch } from '../../../api-search';
import { useDebounce } from '../../hooks';
import { ActiveConstraint } from '../tools/active-constraints';

/**
 * Search field component.
 *
 * @returns {WPElement} Component element.
 */
export default () => {
	const { args, searchFor, searchTerm } = useApiSearch();

	const [value, setValue] = useState(args.search);

	/**
	 * Dispatch the change, with debouncing.
	 */
	const dispatchChange = useDebounce((value) => {
		searchFor(value);
	}, 300);

	/**
	 * Handle input changes.
	 *
	 * @param {Event} event Change event.
	 */
	const onChange = (event) => {
		setValue(event.target.value);
		dispatchChange(event.target.value);
	};

	/**
	 * Handle clearing.
	 */
	const onClear = () => {
		searchFor('');
	};

	/**
	 * Handle an external change to the search value, such as from popping
	 * state.
	 */
	const handleSearch = () => {
		setValue(args.search);
	};

	/**
	 * Effects.
	 */
	useEffect(handleSearch, [args.search]);

	return (
		<>
			<input
				className="ep-search-input"
				placeholder={__('Search…', 'elasticpress')}
				type="search"
				value={value}
				onChange={onChange}
			/>
			{searchTerm && (
				<ActiveConstraint
					label={sprintf(
						/* translators: %s: Search term. */
						__('“%s”', 'elasticpress'),
						searchTerm,
					)}
					onClick={onClear}
				/>
			)}
		</>
	);
};
