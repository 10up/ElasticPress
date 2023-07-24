import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, Spinner, Placeholder } from '@wordpress/components';
import { Fragment, useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

import FacetDisplayCountControl from '../common/components/facet-display-count-control';
import FacetOrderControl from '../common/components/facet-order-control';
import FacetSearchPlaceholderControl from '../common/components/facet-search-placeholder-control';

const FacetBlockEdit = (props) => {
	const { attributes, setAttributes } = props;
	const [preview, setPreview] = useState('');
	const [loading, setLoading] = useState(false);
	const { searchPlaceholder, displayCount, orderby, order } = attributes;

	const blockProps = useBlockProps();

	useEffect(() => {
		setLoading(true);
		const params = new URLSearchParams({
			searchPlaceholder,
			displayCount,
			orderby,
			order,
		});
		apiFetch({
			path: `/elasticpress/v1/facets/post-type/block-preview?${params}`,
		})
			.then((preview) => setPreview(preview))
			.finally(() => setLoading(false));
	}, [searchPlaceholder, displayCount, orderby, order]);

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
		<Fragment>
			<InspectorControls>
				<PanelBody title={__('Facet Settings', 'elasticpress')}>
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
				{loading && (
					<Placeholder>
						<Spinner />
					</Placeholder>
				)}
				{/* eslint-disable-next-line react/no-danger */}
				{!loading && <div dangerouslySetInnerHTML={{ __html: preview }} />}
			</div>
		</Fragment>
	);
};
export default FacetBlockEdit;
