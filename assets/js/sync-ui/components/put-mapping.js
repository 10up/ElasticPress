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
				help={__(
					'All indexed data on ElasticPress will be deleted without affecting anything on your WordPress website. This may take a few hours depending on the amount of content that needs to be synced and indexed. While this is happening, searches will use the default WordPress results.',
					'elasticpress',
				)}
				indeterminate={isSyncing && isDeleting && !args.put_mapping}
				label={__('Delete all data and start fresh sync', 'elasticpress')}
				onChange={(checked) => setArgs({ ...args, put_mapping: checked })}
			/>
		</>
	);
};
