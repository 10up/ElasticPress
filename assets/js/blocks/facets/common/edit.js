/* eslint-disable no-nested-ternary */
/**
 * WordPress dependencies.
 */
import { getBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { Disabled, PanelBody, Placeholder } from '@wordpress/components';
import { Fragment, WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';

/**
 * Internal dependencies.
 */
import icon from './icon';
import EmptyResponsePlaceholder from './components/empty-response-placeholder';
import FacetDisplayCountControl from './components/facet-display-count-control';
import FacetMetaControl from './components/facet-meta-control';
import FacetOrderControl from './components/facet-order-control';
import FacetSearchPlaceholderControl from './components/facet-search-placeholder-control';
import FacetTaxonomyControl from './components/facet-taxonomy-control';
import LoadingResponsePlaceholder from './components/loading-response-placeholder';

/**
 * Filter by Metadata block edit component.
 *
 * @param {object} props Component props.
 * @param {object} props.attributes Block attributes.
 * @param {string} props.name Block name.
 * @param {Function} props.setAttributes Block attribute setter.
 * @returns {WPElement}
 */
export default ({ attributes, name, setAttributes }) => {
	const { displayCount, facet, orderby, order, searchPlaceholder, type } = attributes;

	const { title } = getBlockType(name);

	const blockProps = useBlockProps();

	const FacetControl = type === 'meta' ? FacetMetaControl : FacetTaxonomyControl;

	/**
	 * Display count change handler.
	 *
	 * @param {boolean} displayCount Display count?
	 * @returns {void}
	 */
	const onChangeDisplayCount = (displayCount) => {
		setAttributes({ displayCount });
	};

	/**
	 * Facet change handler.
	 *
	 * @param {string} facet Selected facet.
	 * @returns {void}
	 */
	const onChangeFacet = (facet) => {
		setAttributes({ facet });
	};

	/**
	 * Facet change handler.
	 *
	 * @param {object} value Value.
	 * @param {string} value.orderby Order by value.
	 * @param {string} value.order Order value.
	 * @returns {void}
	 */
	const onChangeOrder = ({ orderby, order }) => {
		setAttributes({ orderby, order });
	};

	/**
	 * Search placeholder change handler.
	 *
	 * @param {string} searchPlaceholder Search placeholder.
	 * @returns {void}
	 */
	const onChangeSearchPlaceholder = (searchPlaceholder) => {
		setAttributes({ searchPlaceholder });
	};

	return (
		<Fragment>
			<InspectorControls>
				<PanelBody title={__('Settings', 'elasticpress')}>
					<FacetControl onChange={onChangeFacet} value={facet} />
					{type === 'meta' ? (
						<FacetSearchPlaceholderControl
							onChange={onChangeSearchPlaceholder}
							value={searchPlaceholder}
						/>
					) : null}
					<FacetDisplayCountControl
						checked={displayCount}
						onChange={onChangeDisplayCount}
					/>
					<FacetOrderControl onChange={onChangeOrder} orderby={orderby} order={order} />
				</PanelBody>
			</InspectorControls>
			<div {...blockProps}>
				{facet ? (
					<Disabled>
						<ServerSideRender
							attributes={{
								displayCount,
								facet,
								isPreview: true,
								orderby,
								order,
								searchPlaceholder,
								type,
							}}
							block={name}
							EmptyResponsePlaceholder={EmptyResponsePlaceholder}
							LoadingResponsePlaceholder={LoadingResponsePlaceholder}
							skipBlockSupportAttributes
						/>
					</Disabled>
				) : (
					<Placeholder icon={icon} label={title}>
						<FacetControl onChange={onChangeFacet} value={facet} />
					</Placeholder>
				)}
			</div>
		</Fragment>
	);
};
