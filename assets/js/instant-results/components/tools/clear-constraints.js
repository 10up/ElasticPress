/**
 * WordPress dependencies.
 */
import { useContext, useMemo, WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { facets } from '../../config';
import Context from '../../context';
import SmallButton from '../common/small-button';

/**
 * Active constraints component.
 *
 * @return {WPElement} Element.
 */
export default () => {
	const {
		state: { filters },
		dispatch,
	} = useContext(Context);

	/**
	 * Return whether there are active filters.
	 *
	 * Only filters that are available as facets are checked, as these are the
	 * only filters that will be cleared. This is to support applying filters
	 * that cannot be modified by the user.
	 *
	 * @return {boolean} Whether there are active filters.
	 */
	const hasFilters = useMemo(() => {
		return facets.some(({ name }) => filters[name]?.length > 0);
	}, [filters]);

	/**
	 * Handle clicking button.
	 *
	 * @return {void}
	 */
	const onClick = () => {
		dispatch({ type: 'CLEAR_FILTERS' });
	};

	return (
		hasFilters && (
			<SmallButton onClick={onClick}>{__('Clear filters', 'elasticpress')}</SmallButton>
		)
	);
};
