import { BlockControls, InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	PanelBody,
	RadioControl,
	SelectControl,
	Spinner,
	TextControl,
	ToolbarButton,
	Placeholder,
	Popover,
} from '@wordpress/components';
import { Fragment, useEffect, useState, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __, _x } from '@wordpress/i18n';

const FacetBlockEdit = (props) => {
	const { attributes, setAttributes } = props;
	const [taxonomies, setTaxonomies] = useState({});
	const [preview, setPreview] = useState('');
	const [loading, setLoading] = useState(false);
	const { facet, orderby, order } = attributes;
	const [isInputDialogVisible, setIsInputDialogVisible] = useState(false);
	const [name, setName] = useState('');
	const blockProps = useBlockProps();

	const load = useCallback(async () => {
		const taxonomies = await apiFetch({
			path: '/elasticpress/v1/facets/taxonomies',
		});
		setTaxonomies(taxonomies);
	}, [setTaxonomies]);

	useEffect(load, [load]);

	useEffect(() => {
		setLoading(true);
		const params = new URLSearchParams({
			facet,
			orderby,
			order,
		});
		apiFetch({
			path: `/elasticpress/v1/facets/block-preview?${params}`,
		})
			.then((preview) => setPreview(preview))
			.finally(() => setLoading(false));
	}, [facet, orderby, order]);

	const InputNameDialog = useCallback(
		() => (
			<TextControl
				className="name-attribute"
				label={_x('Control name', 'Popup control name', 'namespace')}
				help={_x('This is title of field', 'Popup help text', 'namespace')}
				value={name}
				onChange={(name) => setName(name)}
			/>
		),
		[name],
	);

	return (
		<Fragment>
			<BlockControls group="block">
				<ToolbarButton
					onClick={() => {
						setIsInputDialogVisible(true);
					}}
					icon="nametag"
				>
					{isInputDialogVisible && (
						<Popover
							onClose={() => {
								setIsInputDialogVisible(false);
							}}
						>
							<InputNameDialog />
						</Popover>
					)}
				</ToolbarButton>
			</BlockControls>
			<InspectorControls>
				<PanelBody title={__('Facet Settings', 'elasticpress')}>
					<SelectControl
						label={__('Taxonomy', 'elasticpress')}
						value={facet}
						options={[
							...Object.entries(taxonomies).map(([slug, taxonomy]) => ({
								label: taxonomy.label,
								value: slug,
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
