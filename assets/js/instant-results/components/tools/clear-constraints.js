/**
 * WordPress dependencies.
 */
import { useContext, useMemo, WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import Context from '../../context';
import SmallButton from '../common/small-button';

/**
 * Active constraints component.
 *
 * @return {WPElement} Element.
 */
export default () => {
	const {
		state: { postTypes, priceRange, taxonomyTerms },
		dispatch,
	} = useContext(Context);

	/**
	 * Return whether there are active filters.
	 *
	 * @return {boolean} Whether there are active filters.
	 */
	const hasFilters = useMemo(() => {
		const [minPrice, maxPrice] = priceRange;

		const hasPostTypes = postTypes.length > 0;
		const hasPriceRange = Boolean(minPrice || maxPrice);
		const hasTaxonomyTerms = Object.values(taxonomyTerms).some((terms) => terms.length > 0);

		return hasPostTypes || hasPriceRange || hasTaxonomyTerms;
	}, [postTypes, priceRange, taxonomyTerms]);

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
