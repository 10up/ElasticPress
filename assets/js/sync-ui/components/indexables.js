/**
 * WordPress dependencies.
 */
import { CheckboxControl, Notice } from '@wordpress/components';
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
	const { args, hasOnlyGlobalIndexables, indexables, setArgs } = useSyncSettings();

	/**
	 * Handle changes to Indexable checkboxes.
	 *
	 * Sets the post types argument as empty if the Posts indexable is not
	 * being synced.
	 *
	 * @param {string} indexable Indexable.
	 * @param {boolean} checked Whether the Indexable is checked.
	 * @returns {void}
	 */
	const onChange = (indexable, checked) => {
		const indexables = args.indexables ? [...args.indexables] : [];

		if (checked) {
			indexables.push(indexable);
		} else {
			indexables.splice(indexables.indexOf(indexable), 1);
		}

		const post_type = !indexables.length || indexables.includes('post') ? args.post_type : [];

		setArgs({ ...args, indexables, post_type });
	};

	return indexables.length > 1 ? (
		<fieldset className="ep-sync-advanced-control">
			<legend className="ep-sync-advanced-control__label">
				{__('Content to sync', 'elasticpress')}
			</legend>
			{indexables.map(([indexable, label, isGlobal]) => {
				const isExcluded = Boolean(args.site_id) && isGlobal;

				return (
					<CheckboxControl
						checked={!isExcluded && args.indexables?.includes(indexable)}
						disabled={isSyncing || isExcluded}
						indeterminate={!isExcluded && !args.indexables.length}
						key={indexable}
						label={label}
						onChange={(checked) => onChange(indexable, checked)}
						__nextHasNoMarginBottom
					/>
				);
			})}
			{hasOnlyGlobalIndexables ? (
				<Notice status="warning" isDismissible={false}>
					{__(
						'Select site-specific content to sync, or select All eligible sites to sync shared network content.',
						'elasticpress',
					)}
				</Notice>
			) : null}
		</fieldset>
	) : null;
};
