/**
 * WordPress dependencies.
 */
import { WPElement } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import SyncPanel from './sync/panel';

/**
 * Sync page component.
 *
 * @param {object} props Component props.
 * @param {boolean} props.isCli If sync is a CLI sync.
 * @param {boolean} props.isComplete If sync is complete.
 * @param {boolean} props.isDeleting If sync is a delete and sync.
 * @param {boolean} props.isEpio If ElasticPress is using ElasticPress.io.
 * @param {boolean} props.isPaused If sync is paused.
 * @param {boolean} props.isSyncing If sync is running.
 * @param {number} props.itemsProcessed Number of items processed.
 * @param {number} props.itemsTotal Number of items to process.
 * @param {string} props.lastSyncDateTime Date and time of last sync in ISO-8601.
 * @param {boolean} props.lastSyncFailed If the last sync had failures.
 * @param {object[]} props.log Sync message log.
 * @param {Function} props.onDelete Callback for clicking delete and sync.
 * @param {Function} props.onPause Callback for clicking pause.
 * @param {Function} props.onResume Callback for clicking resume.
 * @param {Function} props.onStop Callback for clicking stop.
 * @param {Function} props.onSync Callback for clicking sync.
 * @param {string} props.syncStartDateTime Date and time of current sync in ISO 8601.
 * @returns {WPElement} Sync page component.
 */
export default ({ isCli, isComplete, isDeleting, isEpio, isSyncing, log, ...props }) => {
	const isInitialSync = props.lastSyncDateTime === null;

	return (
		<>
			<h1 className="ep-sync-heading">{__('Sync Settings', 'elasticpress')}</h1>

			<SyncPanel
				introduction={
					isInitialSync
						? sprintf(
								/* translators: %s: Index type. ElasticPress.io or Elasticsearch. */
								__(
									'Run a sync to index your existing content %s. Once syncing finishes, your site is officially supercharged.',
									'elasticpress',
								),
								isEpio
									? __('on ElasticPress.io', 'elasticpress')
									: __('in Elasticsearch', 'elasticpress'),
						  )
						: __(
								'If you are missing data in your search results or have recently added custom content types to your site, you should run a sync to reflect these changes.',
								'elasticpress',
						  )
				}
				isComplete={isComplete}
				isDisabled={(isSyncing && isDeleting) || (isSyncing && isCli)}
				isSyncing={isSyncing && !isDeleting}
				logMessages={log.filter((m) => !m.isDeleting)}
				showLastSync
				showProgress={!isDeleting && (isSyncing || isComplete)}
				showSync
				{...props}
			/>

			{!isInitialSync ? (
				<SyncPanel
					heading={__('Delete All Data and Sync', 'elasticpress')}
					introduction={__(
						'If you are still having issues with your search results, you may need to do a completely fresh sync.',
						'elasticpress',
					)}
					isComplete={isComplete}
					isDisabled={(isSyncing && !isDeleting) || (isSyncing && isCli)}
					isSyncing={isSyncing && isDeleting}
					logMessages={log.filter((m) => m.isDeleting)}
					showDelete
					showProgress={isDeleting && (isSyncing || isComplete)}
					warningMessage={__(
						'All indexed data on ElasticPress will be deleted without affecting anything on your WordPress website. This may take a few hours depending on the amount of content that needs to be synced and indexed. While this is happening, searches will use the default WordPress results',
						'elasticpress',
					)}
					{...props}
				/>
			) : null}
		</>
	);
};
