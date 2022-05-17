/**
 * WordPress dependencies.
 */
import { Button, Icon, Panel, PanelBody } from '@wordpress/components';
import { WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { warning } from '@wordpress/icons';

/**
 * Internal dependencies.
 */
import SyncControls from './sync/controls';
import SyncLog from './sync/log';
import SyncProgress from './sync/progress';
import SyncStatus from './sync/status';

/**
 * Sync page component.
 *
 * @param {object} props Component props.
 * @param {boolean} props.isCli If sync is a CLI sync.
 * @param {boolean} props.isComplete If sync is complete.
 * @param {boolean} props.isDeleting If sync is a delete and sync.
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
export default ({
	isCli,
	isComplete,
	isDeleting,
	isPaused,
	isSyncing,
	itemsProcessed,
	itemsTotal,
	lastSyncDateTime,
	lastSyncFailed,
	log,
	onDelete,
	onPause,
	onResume,
	onStop,
	onSync,
	syncStartDateTime,
}) => {
	return (
		<>
			<h1 className="ep-sync-heading">{__('Sync Settings', 'elasticpress')}</h1>

			<Panel className="ep-sync-panel">
				<PanelBody className="ep-sync-panel__body">
					<div className="ep-sync-panel__description">
						<p className="ep-sync-panel__introduction">
							{__(
								'If you are missing data in your search results or have recently added custom content types to your site, you should run a sync to reflect these changes.',
								'elasticpress',
							)}
						</p>

						{lastSyncDateTime ? (
							<>
								<h3 className="ep-sync-heading">
									{__('Last Sync', 'elasticpress')}
								</h3>
								<SyncStatus
									dateTime={lastSyncDateTime}
									isSuccess={!lastSyncFailed}
								/>
							</>
						) : null}
					</div>

					<div className="ep-sync-panel__controls">
						<SyncControls
							disabled={(isSyncing && isDeleting) || (isSyncing && isCli)}
							isPaused={isPaused}
							isSyncing={isSyncing && !isDeleting}
							onPause={onPause}
							onResume={onResume}
							onStop={onStop}
							onSync={onSync}
							showSync
						/>
					</div>

					{!isDeleting && (isSyncing || isComplete) ? (
						<div className="ep-sync-panel__row">
							<SyncProgress
								dateTime={syncStartDateTime}
								isCli={isCli}
								isComplete={isComplete}
								isPaused={isPaused}
								itemsProcessed={itemsProcessed}
								itemsTotal={itemsTotal}
							/>
						</div>
					) : null}

					<div className="ep-sync-panel__row">
						<SyncLog messages={log.filter((m) => !m.isDeleting)} />
					</div>
				</PanelBody>
			</Panel>

			<h2 className="ep-sync-heading">{__('Delete All Data and Sync', 'elasticpress')}</h2>

			<Panel className="ep-sync-panel">
				<PanelBody className="ep-sync-panel__body">
					<div className="ep-sync-panel__description">
						<p className="ep-sync-panel__introduction">
							{__(
								'If you are still having issues with your search results, you may need to do a completely fresh sync.',
								'elasticpress',
							)}
						</p>

						<p>
							<Button
								className="ep-sync-button ep-sync-button--delete"
								disabled={isSyncing}
								isSecondary
								isDestructive
								onClick={onDelete}
							>
								{__('Delete all Data and Start a Fresh Sync', 'elasticpress')}
							</Button>
						</p>
					</div>

					<div className="ep-sync-panel__controls">
						<SyncControls
							disabled={(isSyncing && !isDeleting) || (isSyncing && isCli)}
							isPaused={isPaused}
							isSyncing={isSyncing && isDeleting}
							onPause={onPause}
							onResume={onResume}
							onStop={onStop}
						/>
					</div>

					{isDeleting && (isSyncing || isComplete) ? (
						<div className="ep-sync-panel__row">
							<SyncProgress
								dateTime={syncStartDateTime}
								isCli={isCli}
								isComplete={isComplete}
								isPaused={isPaused}
								itemsProcessed={itemsProcessed}
								itemsTotal={itemsTotal}
							/>
						</div>
					) : null}

					<div className="ep-sync-panel__row">
						<SyncLog messages={log.filter((m) => m.isDeleting)} />
					</div>

					<div className="ep-sync-panel__row">
						<p className="ep-sync-warning">
							<Icon icon={warning} />
							{__(
								'All indexed data on ElasticPress will be deleted without affecting anything on your WordPress website. This may take a few hours depending on the amount of content that needs to be synced and indexed. While this is happening, searches will use the default WordPress results',
								'elasticpress',
							)}
						</p>
					</div>
				</PanelBody>
			</Panel>
		</>
	);
};
