/**
 * WordPress dependencies.
 */
import { WPElement } from '@wordpress/element';

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

	switch (type) {
		case 'post_type':
			return <PostTypeFacet defaultIsOpen={defaultIsOpen} label={label} />;
		case 'price_range':
			return <PriceRangeFacet defaultIsOpen={defaultIsOpen} label={label} />;
		case 'taxonomy':
			return (
				<TaxonomyTermsFacet
					defaultIsOpen={defaultIsOpen}
					label={label}
					name={name}
					postTypes={postTypes}
				/>
			);
		default:
			return null;
	}
};
