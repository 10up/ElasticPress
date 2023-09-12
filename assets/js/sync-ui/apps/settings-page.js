/**
 * WordPress dependencies.
 */
import { WPElement } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { useSync } from '../../sync';
import SyncPanel from '../components/sync/panel';

/**
 * Sync page component.
 *
 * @returns {WPElement} Sync page component.
 */
export default () => {
	const {
		isCli,
		isComplete,
		isDeleting,
		isEpio,
		isFailed,
		isSyncing,
		lastSyncDateTime,
		log,
		logMessage,
		pauseSync,
		resumeSync,
		startSync,
		stopSync,
	} = useSync();

	const isInitialSync = lastSyncDateTime === null;

	/**
	 * Handle clicking delete and sync button.
	 *
	 * @returns {void}
	 */
	const onDelete = async () => {
		startSync(true);
		logMessage(__('Starting delete and sync…', 'elasticpress'), 'info');
	};

	/**
	 * Handle clicking pause button.
	 *
	 * @returns {void}
	 */
	const onPause = () => {
		pauseSync();
		logMessage(__('Pausing sync…', 'elasticpress'), 'info');
	};

	/**
	 * Handle clicking play button.
	 *
	 * @returns {void}
	 */
	const onResume = () => {
		resumeSync();
		logMessage(__('Resuming sync…', 'elasticpress'), 'info');
	};

	/**
	 * Handle clicking stop button.
	 *
	 * @returns {void}
	 */
	const onStop = () => {
		stopSync();
		logMessage(__('Sync stopped', 'elasticpress'), 'info');
	};

	/**
	 * Handle clicking sync button.
	 *
	 * @returns {void}
	 */
	const onSync = async () => {
		startSync(false);
		logMessage(__('Starting sync…', 'elasticpress'), 'info');
	};

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
				isDisabled={(isSyncing && isDeleting) || (isSyncing && isCli)}
				isLoading={isSyncing && !isDeleting}
				logMessages={log.filter((m) => !m.isDeleting)}
				showLastSync
				showProgress={!isDeleting && (isSyncing || isComplete || isFailed)}
				showSync
				onDelete={onDelete}
				onPause={onPause}
				onResume={onResume}
				onSync={onSync}
				onStop={onStop}
			/>

			{!isInitialSync ? (
				<SyncPanel
					heading={__('Delete All Data and Sync', 'elasticpress')}
					introduction={__(
						'If you are still having issues with your search results, you may need to do a completely fresh sync.',
						'elasticpress',
					)}
					isDisabled={(isSyncing && !isDeleting) || (isSyncing && isCli)}
					isLoading={isSyncing && isDeleting}
					logMessages={log.filter((m) => m.isDeleting)}
					showDelete
					showProgress={isDeleting && (isSyncing || isComplete || isFailed)}
					warningMessage={__(
						'All indexed data on ElasticPress will be deleted without affecting anything on your WordPress website. This may take a few hours depending on the amount of content that needs to be synced and indexed. While this is happening, searches will use the default WordPress results',
						'elasticpress',
					)}
					onDelete={onDelete}
					onPause={onPause}
					onResume={onResume}
					onSync={onSync}
					onStop={onStop}
				/>
			) : null}
		</>
	);
};
