/**
 * WordPress dependencies.
 */
import { useMemo, WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { useApiSearch } from '../../../api-search';
import { sortOptions } from '../../config';

/**
 * Search results component.
 *
 * @returns {WPElement} Component element.
 */
export default () => {
	const {
		args: { orderby, order },
		search,
	} = useApiSearch();

	/**
	 * The key for the current sorting option.
	 */
	const currentOption = useMemo(() => {
		return Object.keys(sortOptions).find((key) => {
			return sortOptions[key].orderby === orderby && sortOptions[key].order === order;
		});
	}, [orderby, order]);

	/**
	 * Handle sorting option change.
	 *
	 * @param {Event} event Change event.
	 */
	const onChange = (event) => {
		const { orderby, order } = sortOptions[event.target.value];

		search({ orderby, order });
	};

	return (
		<label className="ep-search-sort" htmlFor="ep-sort">
			<span className="ep-search-sort__label">{__('Sort by', 'elasticpress')}</span>{' '}
			<select
				className="ep-search-sort__options"
				id="ep-sort"
				onChange={onChange}
				value={currentOption}
			>
				{Object.entries(sortOptions).map(([key, { name }]) => (
					<option key={key} value={key}>
						{name}
					</option>
				))}
			</select>
		</label>
	);
};
