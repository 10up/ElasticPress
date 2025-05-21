/**
 * WordPress dependencies.
 */
import { WPElement } from '@wordpress/element';
import { applyFilters } from '@wordpress/hooks';

/**
 * Internal dependencies.
 */
import PostTypeFacet from './post-type-facet';
import PriceRangeFacet from './price-range-facet';
import TaxonomyTermsFacet from './taxonomy-terms-facet';

/**
 * Facet component.
 *
 * @param {object}                               props           Props.
 * @param {number}                               props.index     Facet index.
 * @param {string}                               props.name      Facet name.
 * @param {string}                               props.label     Facet label.
 * @param {string}                               props.postTypes Facet post types.
 * @param {'post_type'|'price_range'|'taxonomy'} props.type      Facet type.
 * @returns {WPElement} Component element.
 */
export default ({ index, label, name, postTypes, type }) => {
	const defaultIsOpen = index < 2;

	/**
	 * Filter the filter label.
	 *
	 * @filter ep.InstantResults.filter.label
	 * @since 5.3.0
	 *
	 * @param {string} label Filter label.
	 * @param {string} name Filter name.
	 * @param {string} type Filter type.
	 * @param {string[]} postTypes Filter post types.
	 * @returns {string} Filtered filter label.
	 */
	const filteredLabel = applyFilters(
		'ep.InstantResults.filter.label',
		label,
		name,
		type,
		postTypes,
	);

	switch (type) {
		case 'post_type':
			return <PostTypeFacet defaultIsOpen={defaultIsOpen} label={filteredLabel} />;
		case 'price_range':
			return <PriceRangeFacet defaultIsOpen={defaultIsOpen} label={filteredLabel} />;
		case 'taxonomy':
			return (
				<TaxonomyTermsFacet
					defaultIsOpen={defaultIsOpen}
					label={filteredLabel}
					name={name}
					postTypes={postTypes}
				/>
			);
		default:
			return null;
	}
};
