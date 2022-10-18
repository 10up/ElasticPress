/**
 * WordPress dependencies.
 */
import { useEffect, useState, WPElement } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { useDebounce, useInstantResults } from '../../hooks';
import { ActiveContraint } from '../tools/active-constraints';

/**
 * Search field component.
 *
 * @param {object} props Component props.
 * @param {string} props.arg Argument to use.
 * @returns {WPElement} Component element.
 */
export default ({ arg }) => {
	const { args, newSearch, searchedTerm } = useInstantResults();

	const [value, setValue] = useState(args[arg]);

	/**
	 * Dispatch the change, with debouncing.
	 */
	const dispatchChange = useDebounce((value) => {
		newSearch(value);
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
		newSearch('');
	};

	/**
	 * Handle an external change to the search value, such as from popping
	 * state.
	 */
	const handleSearch = () => {
		setValue(args[arg]);
	};

	/**
	 * Effects.
	 */
	useEffect(handleSearch, [arg, args]);

	return (
		<>
			<input
				className="ep-search-input"
				placeholder={__('Search…', 'elasticpress')}
				type="search"
				value={value}
				onChange={onChange}
			/>
			{searchedTerm && (
				<ActiveContraint
					label={sprintf(
						/* translators: %s: Search term. */
						__('“%s”', 'elasticpress'),
						searchedTerm,
					)}
					onClick={onClear}
				/>
			)}
		</>
	);
};
