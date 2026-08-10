/**
 * WordPress dependencies
 */
import { CheckboxControl, Panel, PanelBody, PanelHeader, PanelRow } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { useVectorEmbeddingSettings } from '../../provider';
import TaxonomyInclusion from '../fieldsets/taxonomy-inclusion';
import MetaInclusion from '../fieldsets/meta-inclusion';
import EmbeddedFields from '../fieldsets/embedded-fields';
import EmbeddingMode from '../fieldsets/embedding-mode';

export default ({ postType }) => {
	const { setEmbeddingForPostType } = useVectorEmbeddingSettings();
	const {
		embeddable,
		fieldsIndexingInclude,
		fieldsIndexingExclude,
		key,
		label,
		taxonomies,
		embeddingMode,
	} = postType;

	return (
		<Panel key={label} className="ep-vector-embeddings-panel">
			<PanelHeader>
				<h2>{label}</h2>
			</PanelHeader>
			<PanelBody>
				<PanelRow>
					<CheckboxControl
						label={__('Allow Vector Embedding', 'elasticpress')}
						help={__(
							'Enable or disable vector embeddings for this post type.',
							'elasticpress',
						)}
						checked={embeddable}
						onChange={() =>
							setEmbeddingForPostType(key, null, 'embeddable', !embeddable)
						}
					/>
				</PanelRow>
			</PanelBody>
			{embeddable && (
				<PanelBody title={__('Indexing Criteria', 'elasticpress')}>
					<EmbeddingMode {...{ postType }} />
					{embeddingMode === 'automatic' && (
						<>
							<h4>{__('Rules', 'elasticpress')}</h4>
							<ul style={{ paddingLeft: '20px', listStyle: 'disc' }}>
								<li>
									{__(
										'Include: If you specify terms or fields to include, only posts with those terms or fields will be included. All others will be excluded.',
										'elasticpress',
									)}
								</li>
								<li>
									{__(
										'Exclude: If you specify terms or fields to exclude, posts with those terms and fields will be left out, while all others will be included.',
										'elasticpress',
									)}
								</li>
								<li>
									{__(
										'Both Include & Exclude: If both are set, excluded terms and fields take priority — posts with those terms will always be left out, even if they match the included terms.',
										'elasticpress',
									)}
								</li>
								<li>
									{__(
										'If no rules are set, all posts are included by default.',
										'elasticpress',
									)}
								</li>
							</ul>
							<TaxonomyInclusion {...{ taxonomies, postType }} />
							<MetaInclusion
								{...{
									postType,
									fieldsIndexingExclude,
									fieldsIndexingInclude,
									embeddingMode,
								}}
							/>
						</>
					)}
				</PanelBody>
			)}
			{embeddable && (
				<PanelBody initialOpen title={__('Content Fields', 'elasticpress')}>
					<EmbeddedFields postType={postType} />
				</PanelBody>
			)}
		</Panel>
	);
};
