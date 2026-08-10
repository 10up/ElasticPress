/**
 * External dependencies
 */
import { RangeControl, Panel, PanelBody, PanelHeader } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { useVectorEmbeddingSettings } from '../../provider';

export default () => {
	const { currentSettings, setChunkSize, setChunkOverlap } = useVectorEmbeddingSettings();
	const { chunkSize, chunkOverlap } = currentSettings;
	return (
		<Panel>
			<PanelHeader>
				<h2>{__('Indexing', 'elasticpress')}</h2>
			</PanelHeader>
			<PanelBody>
				<RangeControl
					label={__('Chunk Size (in words)', 'elasticpress')}
					value={chunkSize}
					onChange={setChunkSize}
					min={1}
					max={300}
					__next40pxDefaultSize
				/>
				<RangeControl
					label={__('Chunk Overlap (in words)', 'elasticpress')}
					value={chunkOverlap}
					onChange={setChunkOverlap}
					min={1}
					max={100}
					__next40pxDefaultSize
				/>
			</PanelBody>
		</Panel>
	);
};
