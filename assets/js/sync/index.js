/**
 * External dependencies.
 */
import { v4 as uuid } from 'uuid';

/**
 * WordPress dependencies.
 */
import { Button, Panel, PanelBody } from '@wordpress/components';
import { render, useCallback, useEffect, useRef, useState, WPElement } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { autoIndex, lastSyncDateTime, lastSyncFailed, isEpio, indexMeta } from './config';
import { useIndex } from './hooks';
import {
	clearSyncParam,
	getItemsProcessedFromIndexMeta,
	getItemsTotalFromIndexMeta,
} from './utilities';

import SyncControls from './components/sync-controls';
import SyncLog from './components/sync-logs';
import SyncProgress from './components/sync-progress';
import SyncStatus from './components/sync-status';

/**
 * App component.
 *
 * @returns {WPElement} App component.
 */
const App = () => {
	/**
	 * Indexing methods.
	 */
	const { cancelIndex, index, indexStatus } = useIndex();

	/**
	 * Message log state.
	 */
	const [log, setLog] = useState([]);

	/**
	 * Sync state.
	 */
	const [state, setState] = useState({
		isComplete: false,
		isDeleting: false,
		isSyncing: false,
		itemsProcessed: 0,
		itemsTotal: 100,
		lastSyncDateTime,
		lastSyncFailed,
		syncStartDateTime: null,
	});

	/**
	 * Current state reference.
	 */
	const stateRef = useRef(state);

	/**
	 * Update state, and current state ref.
	 *
	 * @param {object} newState New state properties.
	 * @returns {void}
	 */
	const updateState = (newState) => {
		stateRef.current = { ...stateRef.current, ...newState };
		setState((state) => ({ ...state, ...newState }));
	};

	const logMessage = useCallback(
		/**
		 * Log a message.
		 *
		 * @param {string} message Message/s to log.
		 * @param {string} status Message status.
		 * @returns {void}
		 */
		(message, status) => {
			const { isDeleting } = stateRef.current;

			const messages = Array.isArray(message) ? message : [message];

			for (const message of messages) {
				setLog((log) => [...log, { message, status, isDeleting, id: uuid() }]);
			}
		},
		[],
	);

	const syncCompleted = useCallback(
		/**
		 * Set sync state to completed, with success based on the number of
		 * failures in the index totals.
		 *
		 * @param {object} indexTotals Index totals.
		 * @returns {void}
		 */
		(indexTotals) => {
			updateState({
				isComplete: true,
				isPaused: false,
				isSyncing: false,
				lastSyncDateTime: indexTotals.end_date_time,
				lastSyncFailed: indexTotals.failed > 0,
			});
		},
		[],
	);

	const syncInterrupted = useCallback(
		/**
		 * Set sync state to interrupted.
		 *
		 * Logs an appropriate message based on the sync method and
		 * Elasticsearch hosting.
		 *
		 * @returns {void}
		 */
		() => {
			const { isDeleting } = stateRef.current;

			const message = isDeleting
				? sprintf(
						/* translators: %s: Index type. ElasticPress.io or Elasticsearch. */
						__(
							'Your indexing process has been stopped by WP-CLI and your %s index could be missing content. To restart indexing, please click the Start button or use WP-CLI commands to perform the reindex. Please note that search results could be incorrect or incomplete until the reindex finishes.',
							'elasticpress',
						),
						isEpio
							? __('ElasticPress.io', 'elasticpress')
							: __('Elasticsearch', 'elasticpress'),
				  )
				: __('Sync interrupted by WP-CLI command.', 'elasticpress');

			logMessage(message, 'info');
			updateState({ isSyncing: false });
		},
		[logMessage],
	);

	const syncInProgress = useCallback(
		/**
		 * Set state for a sync in progress from its index meta.
		 *
		 * @param {object} indexMeta Index meta.
		 * @returns {void}
		 */
		(indexMeta) => {
			updateState({
				isCli: indexMeta.method === 'cli',
				isComplete: false,
				isDeleting: indexMeta.put_mapping,
				isSyncing: true,
				itemsProcessed: getItemsProcessedFromIndexMeta(indexMeta),
				itemsTotal: getItemsTotalFromIndexMeta(indexMeta),
				syncStartDateTime: indexMeta.start_date_time,
			});
		},
		[],
	);

	const updateSyncState = useCallback(
		/**
		 * Handle the response to a request to index.
		 *
		 * Updates the application state from the response data and logs any
		 * messages. Returns a Promise that resolves if syncing should
		 * continue.
		 *
		 * @param {object} response AJAX response.
		 * @returns {Promise} Promise that resolves if sync is to continue.
		 */
		(response) => {
			const { isPaused, isSyncing } = stateRef.current;
			const { message, status, totals = [], index_meta: indexMeta } = response.data;

			return new Promise((resolve) => {
				/**
				 * Don't continue if syncing has been stopped.
				 */
				if (!isSyncing) {
					return;
				}

				/**
				 * Log any messages.
				 */
				if (message) {
					logMessage(message, status);
				}

				/**
				 * If totals are available the index is complete.
				 */
				if (!Array.isArray(totals)) {
					syncCompleted(totals);
					return;
				}

				/**
				 * Update sync progress from index meta.
				 */
				syncInProgress(indexMeta);

				/**
				 * Don't continue if the sync was interrupted externally.
				 */
				if (indexMeta.should_interrupt_sync) {
					syncInterrupted();
					return;
				}

				/**
				 * Don't continue if syncing has been paused.
				 */
				if (isPaused) {
					logMessage(__('Sync paused', 'elasticpress'), 'info');
					return;
				}

				/**
				 * Syncing should continue.
				 */
				resolve(indexMeta.method);
			});
		},
		[syncCompleted, syncInProgress, syncInterrupted, logMessage],
	);

	const doIndexStatus = useCallback(
		/**
		 * Check the status of a sync.
		 *
		 * Used to get the status of an external sync already in progress, such
		 * as a WP CLI index.
		 *
		 * @returns {void}
		 */
		() => {
			indexStatus().then(updateSyncState).then(doIndexStatus);
		},
		[indexStatus, updateSyncState],
	);

	const doIndex = useCallback(
		/**
		 * Start or continues a sync.
		 *
		 * @param {boolean} isDeleting Whether to delete and sync.
		 * @returns {void}
		 */
		(isDeleting) => {
			index(isDeleting)
				.then(updateSyncState)
				.then(
					/**
					 * If an existing sync has been found just check its status,
					 * otherwise continue syncing.
					 *
					 * @param {string} method Sync method.
					 */
					(method) => {
						if (method === 'cli') {
							doIndexStatus();
						} else {
							doIndex(isDeleting);
						}
					},
				);
		},
		[doIndexStatus, index, updateSyncState],
	);

	const pauseSync = useCallback(
		/**
		 * Stop syncing.
		 *
		 * @returns {void}
		 */
		() => {
			updateState({ isComplete: false, isPaused: true, isSyncing: true });
		},
		[],
	);

	const stopSync = useCallback(
		/**
		 * Stop syncing.
		 *
		 * @returns {void}
		 */
		() => {
			updateState({ isComplete: false, isPaused: false, isSyncing: false });
			cancelIndex();
		},
		[cancelIndex],
	);

	const resumeSync = useCallback(
		/**
		 * Resume syncing.
		 *
		 * @returns {void}
		 */
		() => {
			updateState({ isComplete: false, isPaused: false, isSyncing: true });
			doIndex(stateRef.current.isDeleting);
		},
		[doIndex],
	);

	const startSync = useCallback(
		/**
		 * Stop syncing.
		 *
		 * @param {boolean} isDeleting Whether to delete and sync.
		 * @returns {void}
		 */
		(isDeleting) => {
			updateState({ isComplete: false, isPaused: false, isSyncing: true });
			updateState({ itemsProcessed: 0, itemsTotal: 100, syncStartDateTime: Date.now() });
			doIndex(isDeleting);
		},
		[doIndex],
	);

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

	/**
	 * Initialize.
	 *
	 * @returns {void}
	 */
	const init = () => {
		/**
		 * Clear sync parameter from the URL to prevent a refresh triggering a new
		 * sync.
		 */
		clearSyncParam();

		/**
		 * If a sync is in progress, update state to reflect its progress.
		 */
		if (indexMeta) {
			syncInProgress(indexMeta);
			pauseSync();

			/**
			 * If the sync is a CLI sync, start getting its status.
			 */
			if (indexMeta.method === 'cli') {
				doIndexStatus();
				logMessage(__('WP CLI sync in progress', 'elasticpress'), 'info');
			} else {
				logMessage(__('Sync paused', 'elasticpress'), 'info');
			}

			return;
		}

		/**
		 * Start an initial index.
		 */
		if (autoIndex) {
			doIndex(true);
			logMessage(__('Starting delete and sync…', 'elasticpress'), 'info');
		}
	};

	/**
	 * Effects.
	 */
	useEffect(init, [doIndex, doIndexStatus, syncInProgress, logMessage, pauseSync]);

	/**
	 * Render.
	 */
	return (
		<div id="sync">
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

						{state.lastSyncDateTime ? (
							<>
								<h3 className="ep-sync-heading">
									{__('Last Sync', 'elasticpress')}
								</h3>
								<SyncStatus
									dateTime={state.lastSyncDateTime}
									isSuccess={!state.lastSyncFailed}
								/>
							</>
						) : null}
					</div>

					<div className="ep-sync-panel__controls">
						<SyncControls
							disabled={(state.isSyncing && state.isDeleting) || state.isCli}
							isPaused={state.isPaused}
							isSyncing={state.isSyncing && !state.isDeleting && !state.isCli}
							onPause={onPause}
							onResume={onResume}
							onStop={onStop}
							onSync={onSync}
							showSync
						/>
					</div>

					{!state.isDeleting && (state.isSyncing || state.isComplete) ? (
						<div className="ep-sync-panel__row">
							<SyncProgress
								dateTime={state.syncStartDateTime}
								isCli={state.isCli}
								isComplete={state.isComplete}
								isPaused={state.isPaused}
								itemsProcessed={state.itemsProcessed}
								itemsTotal={state.itemsTotal}
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
								disabled={state.isSyncing}
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
							disabled={(state.isSyncing && !state.isDeleting) || state.isCli}
							isPaused={state.isPaused}
							isSyncing={state.isSyncing && state.isDeleting && !state.isCli}
							onPause={onPause}
							onResume={onResume}
							onStop={onStop}
						/>
					</div>

					{state.isDeleting && (state.isSyncing || state.isComplete) ? (
						<div className="ep-sync-panel__row">
							<SyncProgress
								dateTime={state.syncStartDateTime}
								isCli={state.isCli}
								isComplete={state.isComplete}
								isPaused={state.isPaused}
								itemsProcessed={state.itemsProcessed}
								itemsTotal={state.itemsTotal}
							/>
						</div>
					) : null}

					<div className="ep-sync-panel__row">
						<SyncLog messages={log.filter((m) => m.isDeleting)} />
					</div>

					<div className="ep-sync-panel__row">
						<p>
							{__(
								'All indexed data on ElasticPress will be deleted without affecting anything on your WordPress website. This may take a few hours depending on the amount of content that needs to be synced and indexed. While this is happenening, searches will use the default WordPress results',
								'elasticpress',
							)}
						</p>
					</div>
				</PanelBody>
			</Panel>
		</div>
	);
};

render(<App />, document.getElementById('ep-sync'));
