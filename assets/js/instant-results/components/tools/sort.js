/**
 * WordPress deendencies.
 */
import { useContext, useMemo, WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal deendencies.
 */
import { sortOptions } from '../../config';
import Context from '../../context';

/**
 * Search results component.
 *
 * @returns {WPElement} Component element.
 */
export default () => {
	const {
		state: {
			args: { orderby, order },
		},
		dispatch,
	} = useContext(Context);

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

		dispatch({ type: 'APPLY_ARGS', payload: { orderby, order } });
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
