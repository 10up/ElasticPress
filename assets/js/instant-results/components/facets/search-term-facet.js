/**
 * WordPress dependencies.
 */
import { useContext, WPElement } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import Context from '../../context';
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

	/**
	 * Handle input changes.
	 *
	 * @param {Event} event Change event.
	 */
	const onChange = (event) => {
		dispatch({ type: 'SET_SEARCH_TERM', payload: event.target.value });
	};

	/**
	 * Handle clearing.
	 */
	const onClear = () => {
		dispatch({ type: 'SET_SEARCH_TERM', payload: '' });
	};

	return (
		<>
			<input
				className="ep-search-input"
				placeholder={__('Search…', 'elasticpress')}
				type="search"
				value={search}
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
