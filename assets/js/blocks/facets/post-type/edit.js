/**
 * WordPress dependencies.
 */
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { Disabled, PanelBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';

/**
 * Internal dependencies.
 */
import EmptyResponsePlaceholder from '../common/components/empty-response-placeholder';
import FacetDisplayCountControl from '../common/components/facet-display-count-control';
import FacetOrderControl from '../common/components/facet-order-control';
import FacetSearchPlaceholderControl from '../common/components/facet-search-placeholder-control';
import LoadingResponsePlaceholder from '../common/components/loading-response-placeholder';

const FacetBlockEdit = (props) => {
	const { attributes, name, setAttributes } = props;
	const { searchPlaceholder, displayCount, orderby, order } = attributes;

	const blockProps = useBlockProps();

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
		<>
			<InspectorControls>
				<PanelBody title={__('Settings', 'elasticpress')}>
					<FacetSearchPlaceholderControl
						onChange={onChangeSearchPlaceholder}
						value={searchPlaceholder}
					/>
					<FacetDisplayCountControl
						checked={displayCount}
						onChange={onChangeDisplayCount}
					/>
					<FacetOrderControl onChange={onChangeOrder} orderby={orderby} order={order} />
				</PanelBody>
			</InspectorControls>

			<div {...blockProps}>
				<Disabled>
					<ServerSideRender
						attributes={{
							...attributes,
							isPreview: true,
						}}
						block={name}
						EmptyResponsePlaceholder={EmptyResponsePlaceholder}
						LoadingResponsePlaceholder={LoadingResponsePlaceholder}
						skipBlockSupportAttributes
					/>
				</Disabled>
			</div>
		</>
	);
};
export default FacetBlockEdit;
