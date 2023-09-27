/**
 * WordPress dependencies.
 */
import { CheckboxControl } from '@wordpress/components';
import { WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { useSync } from '../../sync';
import { useSyncSettings } from '../provider';

/**
 * Delete checkbox component.
 *
 * @returns {WPElement} Sync page component.
 */
export default () => {
	const { isSyncing } = useSync();
	const { args, postTypes, setArgs } = useSyncSettings();

	/**
	 * Handle changes to post type checkboxes.
	 *
	 * @param {string} postType Post type.
	 * @param {boolean} checked Whether the post type is checked.
	 * @returns {void}
	 */
	const onChange = (postType, checked) => {
		const post_types = [...args.post_types];

		if (checked) {
			post_types.push(postType);
		} else {
			post_types.splice(post_types.indexOf(postType), 1);
		}

		setArgs({ ...args, post_types });
	};

	return postTypes.length > 1 ? (
		<fieldset className="ep-sync-advanced-control">
			<legend className="ep-sync-advanced-control__label">
				{__('Post types to sync', 'elasticpress')}
			</legend>
			{postTypes.map(([postType, label]) => (
				<CheckboxControl
					checked={args.post_types.includes(postType)}
					disabled={isSyncing}
					indeterminate={!args.post_types.length}
					key={postType}
					label={label}
					onChange={(checked) => onChange(postType, checked)}
				/>
			))}
		</fieldset>
	) : null;
};
