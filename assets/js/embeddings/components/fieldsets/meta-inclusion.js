/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { CheckboxControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import MetaSelect from '../fields/meta-select';
import Group from '../layout/group';
import { useVectorEmbeddingSettings } from '../../provider';

export default ({ postType, embeddingMode }) => {
	const { fieldsIndexingInclude, fieldsIndexingExclude, enableFieldsIndexing, key } = postType;
	const { setEmbeddingForPostType } = useVectorEmbeddingSettings();
	return (
		<>
			<h4>{__('Post Meta', 'elasticpress')}</h4>
			<CheckboxControl
				label={__('Post Meta Fields', 'elasticpress')}
				checked={enableFieldsIndexing}
				onChange={() => {
					setEmbeddingForPostType(
						key,
						null,
						'enableFieldsIndexing',
						!enableFieldsIndexing,
					);
				}}
			/>
			{embeddingMode === 'automatic' && enableFieldsIndexing && (
				<>
					<Group indent>
						<MetaSelect
							postType={postType}
							label={__(
								'Include posts that have any of these meta keys',
								'elasticpress',
							)}
							updateKey="fieldsIndexingInclude"
							value={fieldsIndexingInclude}
						/>
					</Group>
					<Group indent>
						<MetaSelect
							postType={postType}
							updateKey="fieldsIndexingExclude"
							label={__(
								'Exclude posts that have any of these meta keys',
								'elasticpress',
							)}
							value={fieldsIndexingExclude}
						/>
					</Group>
				</>
			)}
		</>
	);
};
