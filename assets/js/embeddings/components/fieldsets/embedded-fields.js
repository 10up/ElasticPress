/**
 * External dependencies
 */
import { CheckboxControl, __experimentalVStack as VStack } from '@wordpress/components'; // eslint-disable-line @wordpress/no-unsafe-wp-apis
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */

import MetaSelect from '../fields/meta-select';
import Group from '../layout/group';
import { useVectorEmbeddingSettings } from '../../provider';

export default ({ postType }) => {
	const { setEmbeddingForPostType } = useVectorEmbeddingSettings();
	const { fieldsEmbedding, key } = postType;

	const coreFields = [
		{
			label: __('Post Title', 'elasticpress'),
			value: 'post_title',
		},
		{
			label: __('Post Content', 'elasticpress'),
			value: 'post_content',
		},
		{
			label: __('Post Excerpt', 'elasticpress'),
			value: 'post_excerpt',
		},
	];

	return (
		<>
			<p>
				{__(
					'This setting controls which fields will be used to create embedded data.',
					'elasticpress',
				)}
			</p>
			<p>
				{__(
					'Select from the post fields below. Additional meta keys can be added to the input below.',
					'elasticpress',
				)}
			</p>
			<Group>
				<VStack>
					{coreFields.map((field) => {
						const { label, value } = field;
						return (
							<CheckboxControl
								key={value}
								label={label}
								checked={fieldsEmbedding.includes(value)}
								onChange={() => {
									setEmbeddingForPostType(
										key,
										null,
										'fieldsEmbedding',
										fieldsEmbedding.includes(value)
											? fieldsEmbedding.filter((f) => f !== value)
											: [...fieldsEmbedding, value],
									);
								}}
							/>
						);
					})}
				</VStack>
			</Group>
			<Group>
				<MetaSelect
					postType={postType}
					label={__('Add Custom Fields', 'elasticpress')}
					value={fieldsEmbedding}
					updateKey="fieldsEmbedding"
				/>
			</Group>
		</>
	);
};
