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
import SyncControls from './controls';
import SyncLog from './log';
import SyncProgress from './progress';
import SyncStatus from './status';

/**
 * Sync page component.
 *
 * @param {object} props Component props.
 * @param {string} props.heading Panel heading.
 * @param {string} props.introduction Panel introduction.
 * @param {boolean} props.isCli If sync is a CLI sync.
 * @param {boolean} props.isComplete If sync is complete.
 * @param {boolean} props.isDisabled If controls are disabled.
 * @param {boolean} props.isPaused If sync is paused.
 * @param {boolean} props.isSyncing If sync is running.
 * @param {number} props.itemsProcessed Number of items processed.
 * @param {number} props.itemsTotal Number of items to process.
 * @param {string} props.lastSyncDateTime Date and time of last sync in ISO-8601.
 * @param {boolean} props.lastSyncFailed If the last sync had failures.
 * @param {object[]} props.logMessages Log messages.
 * @param {Function} props.onDelete Callback for clicking delete and sync.
 * @param {Function} props.onPause Callback for clicking pause.
 * @param {Function} props.onResume Callback for clicking resume.
 * @param {Function} props.onStop Callback for clicking stop.
 * @param {Function} props.onSync Callback for clicking sync.
 * @param {string} props.syncStartDateTime Date and time of current sync in ISO 8601.
 * @param {boolean} props.showLastSync Whether to show the last sync details.
 * @param {boolean} props.showDelete Whether to show the delete button.
 * @param {boolean} props.showProgress Whether to show the progress bar.
 * @param {boolean} props.showSync Whether to show the sync button.
 * @param {string} props.warningMessage Warning message.
 * @returns {WPElement} Sync page component.
 */
export default ({
	heading,
	introduction,
	isCli,
	isComplete,
	isDisabled,
	isPaused,
	isSyncing,
	itemsProcessed,
	itemsTotal,
	lastSyncDateTime,
	lastSyncFailed,
	logMessages,
	onDelete,
	onPause,
	onResume,
	onStop,
	onSync,
	showDelete,
	showLastSync,
	showProgress,
	showSync,
	syncStartDateTime,
	warningMessage,
}) => {
	return (
		<>
			{heading ? <h2 className="ep-sync-heading">{heading}</h2> : null}

			<Panel className="ep-sync-panel">
				<PanelBody className="ep-sync-panel__body">
					<div className="ep-sync-panel__description">
						{introduction ? (
							<p className="ep-sync-panel__introduction">{introduction}</p>
						) : null}

						{showLastSync && lastSyncDateTime ? (
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

						{showDelete ? (
							<p>
								<Button
									className="ep-sync-button ep-sync-button--delete"
									disabled={isDisabled}
									isSecondary
									isDestructive
									onClick={onDelete}
								>
									{__('Delete all Data and Start a Fresh Sync', 'elasticpress')}
								</Button>
							</p>
						) : null}
					</div>

					<div className="ep-sync-panel__controls">
						<SyncControls
							disabled={isDisabled}
							isPaused={isPaused}
							isSyncing={isSyncing}
							onPause={onPause}
							onResume={onResume}
							onStop={onStop}
							onSync={onSync}
							showSync={showSync}
						/>
					</div>

					{showProgress ? (
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
						<SyncLog messages={logMessages} />
					</div>

					{warningMessage ? (
						<div className="ep-sync-panel__row">
							<p className="ep-sync-warning">
								<Icon icon={warning} />
								{warningMessage}
							</p>
						</div>
					) : null}
				</PanelBody>
			</Panel>
		</>
	);
};
