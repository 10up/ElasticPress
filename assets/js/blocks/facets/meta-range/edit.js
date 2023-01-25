/* global facetMetaBlock */

import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl, Spinner, Placeholder, SelectControl } from '@wordpress/components';
import {
	Fragment,
	useEffect,
	useState,
	useCallback,
	createInterpolateElement,
} from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

const FacetBlockEdit = (props) => {
	const { attributes, setAttributes } = props;
	const [metaKeys, setMetaKeys] = useState([]);
	const [preview, setPreview] = useState('');
	const [loading, setLoading] = useState(false);
	const { facet, min, max } = attributes;

	const blockProps = useBlockProps();

	const load = useCallback(async () => {
		const metaKeys = await apiFetch({
			path: '/elasticpress/v1/facets/meta-range/keys',
		});
		setMetaKeys(metaKeys);
	}, [setMetaKeys]);

	useEffect(load, [load]);

	useEffect(() => {
		setLoading(true);
		const params = new URLSearchParams({
			facet,
			min,
			max,
		});
		apiFetch({
			path: `/elasticpress/v1/facets/meta-range/block-preview?${params}`,
		})
			.then((preview) => setPreview(preview))
			.finally(() => setLoading(false));
	}, [facet, min, max]);

	return (
		<Fragment>
			<InspectorControls>
				<PanelBody title={__('Facet Settings', 'elasticpress')}>
					<SelectControl
						label={__('Meta Field Key', 'elasticpress')}
						help={createInterpolateElement(
							__(
								'This is the list of meta fields indexed in Elasticsearch. If your desired field does not appear in this list please try to <a>sync your content</a>',
								'elasticpress',
							),
							// eslint-disable-next-line jsx-a11y/anchor-has-content, jsx-a11y/control-has-associated-label
							{ a: <a href={facetMetaBlock.syncUrl} /> },
						)}
						value={facet}
						options={[
							...metaKeys.map((metaKey) => ({
								label: metaKey,
								value: metaKey,
							})),
						]}
						onChange={(value) => setAttributes({ facet: value })}
					/>
					<TextControl
						label={__('Minimum', 'elasticpress')}
						value={min}
						onChange={(value) => setAttributes({ min: value })}
					/>
					<TextControl
						label={__('Max', 'elasticpress')}
						value={min}
						onChange={(value) => setAttributes({ min: value })}
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
