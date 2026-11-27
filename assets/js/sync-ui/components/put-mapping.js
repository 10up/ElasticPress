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
	const { isDeleting, isSyncing } = useSync();
	const { args, setArgs } = useSyncSettings();
	const hasSelectedSite = Boolean(args.site_id);
	const label = hasSelectedSite
		? __('Delete selected site data and start fresh sync', 'elasticpress')
		: __('Delete all data and start fresh sync', 'elasticpress');
	const help = hasSelectedSite
		? __(
				'Indexed data for the selected content on this site will be deleted and rebuilt. Your WordPress content will not be deleted. Other sites and shared network data are not included in this sync. This may take a few hours depending on the amount of content. While this is happening, searches will use the default WordPress results.',
				'elasticpress',
			)
		: __(
				'All indexed data on ElasticPress will be deleted without affecting anything on your WordPress website. This may take a few hours depending on the amount of content that needs to be synced and indexed. While this is happening, searches will use the default WordPress results.',
				'elasticpress',
			);

	return (
		<>
			{args.put_mapping ? (
				<Notice isDismissible={false} status="warning">
					{__(
						'Search results could be out of date or returned in different order while the sync completes.',
						'elasticpress',
					)}
				</Notice>
			) : null}
			<CheckboxControl
				className="ep-sync-delete"
				disabled={isSyncing}
				checked={args.put_mapping}
				help={help}
				indeterminate={isSyncing && isDeleting && !args.put_mapping}
				label={label}
				onChange={(checked) => setArgs({ ...args, put_mapping: checked })}
				__nextHasNoMarginBottom
			/>
		</>
	);
};
