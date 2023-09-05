/**
 * External dependencies.
 */
import { v4 as uuid } from 'uuid';

/**
 * WordPress dependencies.
 */
import {
	createRoot,
	render,
	useCallback,
	useEffect,
	useRef,
	useState,
	WPElement,
} from '@wordpress/element';
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
import SyncPage from './components/sync-page';

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
		isFailed: false,
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
		 * @param {Array|string} message Message/s to log.
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

	const stopSync = useCallback(
		/**
		 * Stop syncing.
		 *
		 * @returns {void}
		 */
		() => {
			updateState({ isPaused: false, isSyncing: false });
			cancelIndex();
		},
		[cancelIndex],
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

			/**
			 * Hide the "just need to sync" notice, if it's present.
			 */
			document.querySelector('[data-ep-notice="no_sync"]')?.remove();
		},
		[],
	);

	const syncFailed = useCallback(
		/**
		 * Handle an error in the sync request.
		 *
		 * @param {object|Error} response Request response.
		 * @returns {void}
		 */
		(response) => {
			/**
			 * Any running requests are cancelled when a new request is made.
			 * We can handle this silently.
			 */
			if (response.name === 'AbortError') {
				return;
			}

			/**
			 * Log any error messages.
			 */
			if (response.message) {
				logMessage(response.message, 'error');
			}

			/**
			 * Log a final message and update the sync state.
			 */
			logMessage(__('Sync failed', 'elasticpress'), 'error');

			updateState({
				isFailed: true,
				isSyncing: false,
				lastSyncDateTime: stateRef.current.syncStartDateTime,
				lastSyncFailed: true,
			});
		},
		[logMessage],
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
			const isInitialSync = stateRef.current.lastSyncDateTime === null;

			/**
			 * We should not appear to be deleting if this is the first sync.
			 */
			const isDeleting = isInitialSync ? false : indexMeta.put_mapping;

			updateState({
				isCli: indexMeta.method === 'cli',
				isDeleting,
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
				 * Stop sync if there is an error.
				 */
				if (status === 'error') {
					syncFailed(response.data);
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
		[syncCompleted, syncFailed, syncInProgress, syncInterrupted, logMessage],
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
			indexStatus().then(updateSyncState).then(doIndexStatus).catch(syncFailed);
		},
		[indexStatus, syncFailed, updateSyncState],
	);

	const doIndex = useCallback(
		/**
		 * Start or continue a sync.
		 *
		 * @param {boolean} putMapping Whether to send mapping.
		 * @returns {void}
		 */
		(putMapping) => {
			index(putMapping)
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
							doIndex(putMapping);
						}
					},
				)
				.catch(syncFailed);
		},
		[doIndexStatus, index, syncFailed, updateSyncState],
	);

	const pauseSync = useCallback(
		/**
		 * Stop syncing.
		 *
		 * @returns {void}
		 */
		() => {
			updateState({ isPaused: true, isSyncing: true });
		},
		[],
	);

	const resumeSync = useCallback(
		/**
		 * Resume syncing.
		 *
		 * @returns {void}
		 */
		() => {
			const { isDeleting, lastSyncDateTime } = stateRef.current;
			const isInitialSync = lastSyncDateTime === null;

			/**
			 * Send mapping if we are deleting and syncing or if this is the
			 * first sync.
			 */
			const putMapping = isInitialSync || isDeleting;

			updateState({ isPaused: false, isSyncing: true });
			doIndex(putMapping);
		},
		[doIndex],
	);

	const startSync = useCallback(
		/**
		 * Start syncing.
		 *
		 * @param {boolean} deleteAndSync Whether to delete and sync.
		 * @returns {void}
		 */
		(deleteAndSync) => {
			const { lastSyncDateTime } = stateRef.current;
			const isInitialSync = lastSyncDateTime === null;

			/**
			 * We should not appear to be deleting if this is the first sync.
			 */
			const isDeleting = isInitialSync ? false : deleteAndSync;

			/**
			 * Send mapping if we are deleting and syncing or if this is the
			 * first sync.
			 */
			const putMapping = isInitialSync || deleteAndSync;

			updateState({
				isComplete: false,
				isFailed: false,
				isDeleting,
				isPaused: false,
				isSyncing: true,
			});

			updateState({ itemsProcessed: 0, syncStartDateTime: Date.now() });
			doIndex(putMapping);
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

			/**
			 * If the sync is a CLI sync, start getting its status.
			 */
			if (indexMeta.method === 'cli') {
				doIndexStatus();
				logMessage(__('WP CLI sync in progress', 'elasticpress'), 'info');
			} else {
				pauseSync();
				logMessage(__('Sync paused', 'elasticpress'), 'info');
			}

			return;
		}

		/**
		 * Start an initial index.
		 */
		if (autoIndex) {
			startSync(true);
			logMessage(__('Starting delete and sync…', 'elasticpress'), 'info');
		}
	};

	/**
	 * Effects.
	 */
	useEffect(init, [doIndexStatus, syncInProgress, logMessage, pauseSync, startSync]);

	/**
	 * Render.
	 */
	return (
		<SyncPage
			isEpio={isEpio}
			log={log}
			onDelete={onDelete}
			onPause={onPause}
			onResume={onResume}
			onStop={onStop}
			onSync={onSync}
			{...state}
		/>
	);
};

if (typeof createRoot === 'function') {
	const root = createRoot(document.getElementById('ep-sync'));

	root.render(<App />);
} else {
	render(<App />, document.getElementById('ep-sync'));
}
