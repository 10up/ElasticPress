import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, RadioControl, TextControl, Spinner, Placeholder } from '@wordpress/components';
import { Fragment, useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

const FacetBlockEdit = (props) => {
	const { attributes, setAttributes } = props;
	const [preview, setPreview] = useState('');
	const [loading, setLoading] = useState(false);
	const { facet, orderby, order } = attributes;

	const blockProps = useBlockProps();

	useEffect(() => {
		setLoading(true);
		const params = new URLSearchParams({
			facet,
			orderby,
			order,
		});
		apiFetch({
			path: `/elasticpress/v1/facets/block-meta-preview?${params}`,
		})
			.then((preview) => setPreview(preview))
			.finally(() => setLoading(false));
	}, [facet, orderby, order]);

	return (
		<Fragment>
			<InspectorControls>
				<PanelBody title={__('Facet Settings', 'elasticpress')}>
					<TextControl
						label={__('Meta Field Key', 'elasticpress')}
						value={facet}
						onChange={(value) => setAttributes({ facet: value })}
					/>
					<RadioControl
						label={__('Order By', 'elasticpress')}
						help={__('The field used to order available options', 'elasticpress')}
						selected={orderby}
						options={[
							{ label: __('Count', 'elasticpress'), value: 'count' },
							{ label: __('Name', 'elasticpress'), value: 'name' },
						]}
						onChange={(value) => setAttributes({ orderby: value })}
					/>
					<RadioControl
						label={__('Order', 'elasticpress')}
						selected={order}
						options={[
							{ label: __('ASC', 'elasticpress'), value: 'asc' },
							{ label: __('DESC', 'elasticpress'), value: 'desc' },
						]}
						onChange={(value) => setAttributes({ order: value })}
					/>
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
