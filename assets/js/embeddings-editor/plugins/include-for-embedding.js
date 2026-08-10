/**
 * WordPress dependencies.
 */
import { CheckboxControl } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { PluginPostStatusInfo as PluginPostStatusInfoLegacy } from '@wordpress/edit-post';
import { PluginPostStatusInfo } from '@wordpress/editor';
import { __ } from '@wordpress/i18n';

export default () => {
	const { editPost } = useDispatch('core/editor');

	const { ep_embedding_include = false, ...meta } = useSelect(
		(select) => select('core/editor').getEditedPostAttribute('meta') || {},
	);

	const onChange = (ep_embedding_include) => {
		editPost({ meta: { ...meta, ep_embedding_include } });
	};

	const WrapperElement =
		typeof PluginPostStatusInfo !== 'undefined'
			? PluginPostStatusInfo
			: PluginPostStatusInfoLegacy;

	return (
		<WrapperElement>
			<CheckboxControl
				label={__('Include for vector embeddings', 'elasticpress')}
				help={__('Check this if you want this post to be vectorized.', 'elasticpress')}
				checked={ep_embedding_include}
				onChange={onChange}
				__nextHasNoMarginBottom
			/>
		</WrapperElement>
	);
};
