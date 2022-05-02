import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, RadioControl, SelectControl } from '@wordpress/components';
import { Fragment, useEffect, useState, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';

const FacetBlockEdit = (props) => {
	const { attributes, setAttributes } = props;
	const [taxonomies, setTaxonomies] = useState({});
	const { facet, orderby, order } = attributes;

	const blockProps = useBlockProps();

	const load = useCallback(async () => {
		const taxonomies = await apiFetch({
			path: '/elasticpress/v1/facets/taxonomies',
		});
		setTaxonomies(taxonomies);
	}, [setTaxonomies]);

	useEffect(load, [load]);

	return (
		<Fragment>
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
				<small>{__('Block Preview', 'elasticpress')}</small>
				<br />
				{taxonomies[facet]?.terms?.length && (
					<div
						className={`terms ${
							taxonomies[facet].terms.length > 15 ? 'searchable' : ''
						}`}
					>
						{taxonomies[facet].terms.length > 15 && (
							<input
								className="facet-search"
								type="search"
								placeholder={sprintf(
									/* translators: Taxonomy label (plural) */
									__('Search %s', 'elasticpress'),
									taxonomies[facet].plural,
								)}
							/>
						)}
						<div className="inner">
							{Object.entries(taxonomies[facet]?.terms).map(([, term]) => (
								<div key={term.term_id} className="term level-0">
									{/* eslint-disable-next-line jsx-a11y/anchor-is-valid */}
									<a href="#">
										<div className="ep-checkbox" role="presentation" />
										{term.name}
									</a>
								</div>
							))}
						</div>
					</div>
				)}
			</div>
		</Fragment>
	);
};
export default FacetBlockEdit;
