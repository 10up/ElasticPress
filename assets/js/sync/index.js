/**
 * WordPress dependencies.
 */
import { Panel, PanelBody } from '@wordpress/components';
import { render, useCallback, useEffect, useRef, useState, WPElement } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { auto_start_index, is_epio, index_meta } from './config';
import { initialState, SyncContext } from './context';
import SyncButton from './components/sync-button';
import SyncControls from './components/sync-controls';
import SyncLog from './components/sync-log';
import SyncProgress from './components/sync-progress';
import SyncStatus from './components/sync-status';
import { useIndex } from './hooks';
import { getItemsProcessedFromIndexMeta, getItemsTotalFromIndexMeta } from './utilities';

/**
 * App component.
 *
 * @returns {WPElement} App component.
 */
const App = () => {
	const [log, setLog] = useState([]);
	const [state, setState] = useState(initialState);
	const { cancelIndex, index, indexStatus } = useIndex();

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

	/**
	 * Log message callback.
	 */
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
				setLog((log) => [...log, { message, status, isDeleting }]);
			}
		},
		[],
	);

	/**
	 * Complete sync callback.
	 */
	const completeSync = useCallback(
		/**
		 * Update sync status from totals.
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

	/**
	 * Interrupt sync callback.
	 */
	const interruptSync = useCallback(
		/**
		 * Interrupt a sync.
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
						is_epio
							? __('ElasticPress.io', 'elasticpress')
							: __('Elasticsearch', 'elasticpress'),
				  )
				: __('Sync interrupted by WP-CLI command.', 'elasticpress');

			logMessage(message, 'info');
			updateState({ isSyncing: false });
		},
		[logMessage],
	);

	/**
	 * Update state from index meta callback.
	 */
	const updateStateFromIndexMeta = useCallback(
		/**
		 * Update sync status from index meta.
		 *
		 * Index meta is either present on the window object, if a sync is
		 * already in progress, or returned in response to index requests.
		 *
		 * @param {object} indexMeta Index meta.
		 * @returns {void}
		 */
		(indexMeta) => {
			updateState({
				isCli: indexMeta.method === 'cli',
				isDeleting: indexMeta.put_mapping,
				itemsProcessed: getItemsProcessedFromIndexMeta(indexMeta),
				itemsTotal: getItemsTotalFromIndexMeta(indexMeta),
				syncStartDateTime: indexMeta.start_date_time,
			});
		},
		[],
	);

	/**
	 * Update sync status callback.
	 */
	const handleSyncResponse = useCallback(
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
				 * Don't resolve if syncing has been stopped.
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
				 * If totals are present the index is complete.
				 */
				if (!Array.isArray(totals)) {
					completeSync(totals);
					return;
				}

				/**
				 * Update sync state from index meta.
				 */
				updateStateFromIndexMeta(indexMeta);

				/**
				 * Handle sync interruption.
				 */
				if (indexMeta.should_interrupt_sync) {
					interruptSync();
					return;
				}

				/**
				 * Don't resolve if syncing has been paused.
				 */
				if (isPaused) {
					logMessage(__('Sync paused.', 'elasticpress'), 'info');
					return;
				}

				resolve(indexMeta.method);
			});
		},
		[completeSync, interruptSync, logMessage, updateStateFromIndexMeta],
	);

	/**
	 * Get sync status callback.
	 */
	const syncStatus = useCallback(
		/**
		 * Get the status of a sync.
		 *
		 * Used to get the status of an external sync already in progress, such
		 * as a WP CLI index.
		 *
		 * @returns {void}
		 */
		() => {
			updateState({ isComplete: false, isPaused: false, isSyncing: true });
			indexStatus().then(handleSyncResponse).then(syncStatus);
		},
		[handleSyncResponse, indexStatus],
	);

	/**
	 * Sync callback.
	 */
	const sync = useCallback(
		/**
		 * Start or continues a sync.
		 *
		 * @param {boolean} isDeleting Whether to delete and sync.
		 * @returns {void}
		 */
		(isDeleting) => {
			updateState({
				isCli: false,
				isComplete: false,
				isDeleting,
				isSyncing: true,
			});

			index(isDeleting)
				.then(handleSyncResponse)
				.then((method) => {
					/**
					 * If an existing sync has been found just check its status,
					 * otherwise continue syncing.
					 */
					if (method === 'cli') {
						syncStatus();
					} else {
						sync(isDeleting);
					}
				});
		},
		[handleSyncResponse, index, syncStatus],
	);

	/**
	 * Handle clicking delete and sync button.
	 *
	 * @returns {void}
	 */
	const onDelete = async () => {
		updateState({ itemsProcessed: 0, itemsTotal: 100, syncStartDateTime: null });
		sync(true);
		logMessage(__('Starting delete and sync…', 'elasticpress'), 'info');
	};

	/**
	 * Handle clicking play/pause button.
	 */
	const onPlayPause = () => {
		const { isDeleting, isPaused } = stateRef.current;

		if (isPaused) {
			updateState({ isPaused: false });
			sync(isDeleting);
			logMessage(__('Resuming sync…', 'elasticpress'), 'info');
		} else {
			updateState({ isPaused: true });
			logMessage(__('Pausing sync…', 'elasticpress'), 'info');
		}
	};

	/**
	 * Handle clicking stop button.
	 *
	 * @returns {void}
	 */
	const onStop = () => {
		updateState({ isSyncing: false });
		cancelIndex();
		logMessage(__('Sync stopped.', 'elasticpress'), 'info');
	};

	/**
	 * Handle clicking sync button.
	 *
	 * @returns {void}
	 */
	const onSync = async () => {
		updateState({ itemsProcessed: 0, itemsTotal: 100, syncStartDateTime: null });
		sync(false);
		logMessage(__('Starting sync…', 'elasticpress'), 'info');
	};

	/**
	 * Clear sync parameter from the URL to prevent a refresh triggering a new
	 * sync.
	 *
	 * @returns {void}
	 */
	const clearSearchParam = () => {
		window.history.replaceState(
			{},
			document.title,
			document.location.pathname + document.location.search.replace(/&do_sync/, ''),
		);
	};

	/**
	 * Set initial state.
	 *
	 * @returns {void}
	 */
	const handleInit = () => {
		clearSearchParam();

		if (index_meta) {
			updateStateFromIndexMeta(index_meta);

			if (index_meta.method === 'cli') {
				syncStatus();
			} else {
				updateState({ isComplete: false, isPaused: true, isSyncing: true });
			}

			return;
		}

		if (auto_start_index) {
			sync(true);
			logMessage(__('Starting delete and sync…', 'elasticpress'), 'info');
		}
	};

	/**
	 * Effects.
	 */
	useEffect(handleInit, [logMessage, sync, syncStatus, updateStateFromIndexMeta]);

	/**
	 * Render.
	 */
	return (
		<SyncContext.Provider value={state}>
			<h1>{__('Sync Settings', 'elasticpress')}</h1>
			<Panel>
				<PanelBody>
					<p>
						{__(
							'If you are missing data in your search results or have recently added custom content types to your site, you should run a sync to reflect these changes.',
							'elasticpress',
						)}
					</p>

					{state.lastSyncDateTime ? (
						<>
							<h3>{__('Last Sync', 'elasticpress')}</h3>
							<SyncStatus />
						</>
					) : null}

					{state.isSyncing && !state.isDeleting && !state.isCli ? (
						<SyncControls onPlayPause={onPlayPause} onStop={onStop} />
					) : (
						<SyncButton onClick={onSync} />
					)}

					{!state.isDeleting && (state.isSyncing || state.isComplete) ? (
						<SyncProgress />
					) : null}
				</PanelBody>
				{log.some((m) => !m.isDeleting) && (
					<SyncLog messages={log.filter((m) => !m.isDeleting)} />
				)}
			</Panel>

			<h2>{__('Delete All Data and Sync', 'elasticpress')}</h2>
			<Panel>
				<PanelBody>
					<p>
						{__(
							'If you are still having issues with your search results, you may need to do a completely fresh sync.',
							'elasticpress',
						)}
					</p>

					<SyncButton isDelete onClick={onDelete} />

					{state.isDeleting ? (
						<>
							{state.isSyncing && !state.isCli ? (
								<SyncControls onPlayPause={onPlayPause} onStop={onStop} />
							) : null}

							{state.isSyncing || state.isComplete ? <SyncProgress /> : null}
						</>
					) : null}

					<p>
						{__(
							'All indexed data on ElasticPress will be deleted without affecting anything on your WordPress website. This may take a few hours depending on the amount of content that needs to be synced and indexed. While this is happenening, searches will use the default WordPress results',
							'elasticpress',
						)}
					</p>
				</PanelBody>
				{log.some((m) => m.isDeleting) && (
					<SyncLog messages={log.filter((m) => m.isDeleting)} />
				)}
			</Panel>
		</SyncContext.Provider>
	);
};

render(<App />, document.getElementById('ep-sync'));
