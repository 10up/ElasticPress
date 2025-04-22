/**
 * WordPress dependencies.
 */
import { useMemo, WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { useApiSearch } from '../../../api-search';
import { facets } from '../../config';
import SmallButton from '../common/small-button';

/**
 * Active constraints component.
 *
 * @returns {WPElement} Element.
 */
export default () => {
	const { args, clearConstraints } = useApiSearch();

	/**
	 * Return whether there are active filters.
	 *
	 * Only filters that are available as facets are checked, as these are the
	 * only filters that will be cleared. This is to support applying filters
	 * that cannot be modified by the user.
	 *
	 * @returns {boolean} Whether there are active filters.
	 */
	const hasFilters = useMemo(() => {
		return facets.some(({ name, type }) => {
			switch (type) {
				case 'post_type':
				case 'taxonomy':
					return args[name]?.length > 0;
				case 'price_range':
					return args.max_price || args.min_price;
				default:
					return args[name];
			}
		});
	}, [args]);

	/**
	 * Handle clicking button.
	 *
	 * @returns {void}
	 */
	const onClick = () => {
		clearConstraints();
	};

	return (
		hasFilters && (
			<SmallButton onClick={onClick}>{__('Clear filters', 'elasticpress')}</SmallButton>
		)
	);
};
