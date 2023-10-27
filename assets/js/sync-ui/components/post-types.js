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
		const post_type = [...args.post_type];

		if (checked) {
			post_type.push(postType);
		} else {
			post_type.splice(post_type.indexOf(postType), 1);
		}

		setArgs({ ...args, post_type });
	};

	return postTypes.length > 1 ? (
		<fieldset className="ep-sync-advanced-control">
			<legend className="ep-sync-advanced-control__label">
				{__('Post types to sync', 'elasticpress')}
			</legend>
			{postTypes.map(([postType, label]) => (
				<CheckboxControl
					checked={args.post_type.includes(postType)}
					disabled={isSyncing}
					indeterminate={!args.post_type.length}
					key={postType}
					label={label}
					onChange={(checked) => onChange(postType, checked)}
				/>
			))}
		</fieldset>
	) : null;
};
