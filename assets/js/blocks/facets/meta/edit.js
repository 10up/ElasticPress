/* global facetMetaBlock */

import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	PanelBody,
	RadioControl,
	TextControl,
	Spinner,
	Placeholder,
	SelectControl,
} from '@wordpress/components';
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
	const { searchPlaceholder, facet, orderby, order } = attributes;

	const blockProps = useBlockProps();

	const load = useCallback(async () => {
		const metaKeys = await apiFetch({
			path: '/elasticpress/v1/facets/meta/keys',
		});
		setMetaKeys(metaKeys);
	}, [setMetaKeys]);

	useEffect(load, [load]);

	useEffect(() => {
		setLoading(true);
		const params = new URLSearchParams({
			searchPlaceholder,
			facet,
			orderby,
			order,
		});
		apiFetch({
			path: `/elasticpress/v1/facets/meta/block-preview?${params}`,
		})
			.then((preview) => setPreview(preview))
			.finally(() => setLoading(false));
	}, [searchPlaceholder, facet, orderby, order]);

	return (
		<Fragment>
			<InspectorControls>
				<PanelBody title={__('Facet Settings', 'elasticpress')}>
					<TextControl
						label={__('Search Placeholder', 'elasticpress')}
						value={searchPlaceholder}
						onChange={(value) => setAttributes({ searchPlaceholder: value })}
					/>
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
