/**
 * External dependencies
 * */
import { CheckboxControl, VStack } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { useVectorEmbeddingSettings } from '../../provider';
import TermSelect from '../fields/term-select';
import Group from '../layout/group';

export default ({ taxonomies, postType }) => {
	const { setEmbeddingForPostType } = useVectorEmbeddingSettings();
	const hasTaxonomies = Object.keys(taxonomies).length > 0;
	const { key } = postType;

	return (
		<div>
			<h4>{__('Taxonomies', 'elasticpress')}</h4>
			{hasTaxonomies > 0 ? (
				<VStack>
					{Object.keys(taxonomies).map((taxonomy) => {
						const { label, termsInclude, termsExclude, enabled } = taxonomies[taxonomy];
						return (
							<>
								<CheckboxControl
									label={label}
									checked={enabled}
									onChange={() => {
										setEmbeddingForPostType(key, taxonomy, 'enabled', !enabled);
									}}
								/>
								{enabled && (
									<>
										<Group indent>
											<TermSelect
												postType={postType}
												taxonomy={taxonomy}
												label={__(
													'Include posts that have any of these terms',
													'elasticpress',
												)}
												value={termsInclude}
												onChange={(terms) =>
													setEmbeddingForPostType(
														key,
														taxonomy,
														'termsInclude',
														terms,
													)
												}
											/>
										</Group>
										<Group indent>
											<TermSelect
												postType={postType}
												taxonomy={taxonomy}
												label={__(
													'Exclude posts that have any of these terms',
													'elasticpress',
												)}
												onChange={(terms) =>
													setEmbeddingForPostType(
														key,
														taxonomy,
														'termsExclude',
														terms,
													)
												}
												value={termsExclude}
											/>
										</Group>
									</>
								)}
							</>
						);
					})}
				</VStack>
			) : (
				<p>
					{__('No public taxonomies are registered to this post type.', 'elasticpress')}
				</p>
			)}
		</div>
	);
};
