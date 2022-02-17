/**
 * WordPress dependencies.
 */
import { useContext, useState, WPElement } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import Context from '../../context';
import { useDebounce } from '../../hooks';
import { ActiveContraint } from '../tools/active-constraints';

/**
 * Search field component.
 *
 * @return {WPElement} Component element.
 */
export default () => {
	const {
		state: {
			args: { search },
			searchedTerm,
		},
		dispatch,
	} = useContext(Context);

	const [value, setValue] = useState(search);

	/**
	 * Dispatch the change, with debouncing.
	 */
	const dispatchChange = useDebounce((value) => {
		dispatch({ type: 'NEW_SEARCH_TERM', payload: value });
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
		dispatch({ type: 'NEW_SEARCH_TERM', payload: '' });
	};

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
