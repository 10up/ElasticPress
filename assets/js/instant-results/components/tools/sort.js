/**
 * WordPress dependencies.
 */
import { useMemo, WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { applyFilters } from '@wordpress/hooks';

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

	const sort = (
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

	/**
	 * Filter the sort component.
	 *
	 * @filter ep.InstantResults.component.sort
	 * @since 5.3.0
	 *
	 * @param {WPElement} sort Sort component.
	 * @param {object} context Sort context.
	 * @param {string} context.orderby Current orderby value.
	 * @param {string} context.order Current order value.
	 * @returns {WPElement} Sort component.
	 */
	return applyFilters('ep.InstantResults.component.sort', sort, { orderby, order });
};
