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
import { useSync } from '../../../sync';

/**
 * Sync page component.
 *
 * @param {object} props Component props.
 * @param {string} props.heading Panel heading.
 * @param {string} props.introduction Panel introduction.
 * @param {boolean} props.isDisabled If controls are disabled.
 * @param {boolean} props.isLoading If sync is running.
 * @param {object[]} props.logMessages Log messages.
 * @param {Function} props.onDelete Callback for clicking delete and sync.
 * @param {Function} props.onPause Callback for clicking pause.
 * @param {Function} props.onResume Callback for clicking resume.
 * @param {Function} props.onStop Callback for clicking stop.
 * @param {Function} props.onSync Callback for clicking sync.
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
	isDisabled,
	isLoading,
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
	warningMessage,
}) => {
	const {
		isCli,
		isComplete,
		isFailed,
		isPaused,
		itemsProcessed,
		itemsTotal,
		lastSyncDateTime,
		lastSyncFailed,
		syncStartDateTime,
	} = useSync();

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
									disabled={isDisabled || isLoading}
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
							isLoading={isLoading}
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
								isFailed={isFailed}
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
