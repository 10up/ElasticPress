/**
 * External dependencies.
 */
import { v4 as uuid } from 'uuid';

/**
 * WordPress dependencies.
 */
import { dateI18n } from '@wordpress/date';
import {
	createContext,
	useCallback,
	useContext,
	useEffect,
	useRef,
	useState,
	WPElement,
} from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { useIndex } from './src/hooks';
import {
	clearSyncParam,
	getItemsProcessedFromIndexMeta,
	getItemsTotalFromIndexMeta,
} from './src/utilities';

/**
 * Sync context.
 */
const Context = createContext();

/**
 * App component.
 *
 * @param {object} props Component props.
 * @param {string} props.apiUrl API endpoint URL.
 * @param {Function} props.children Component children
 * @param {Array} props.defaultSyncHistory Sync history.
 * @param {Array} props.defaultSyncTrigger Sync trigger.
 * @param {object|null} props.indexMeta Details of a sync in progress.
 * @param {boolean} props.isEpio Whether ElasticPress.io is in use.
 * @param {string} props.nonce WordPress nonce.
 * @returns {WPElement} App component.
 */
export const SyncProvider = ({
	apiUrl,
	children,
	defaultSyncHistory,
	defaultSyncTrigger,
	indexMeta,
	isEpio,
	nonce,
}) => {
	/**
	 * Indexing methods.
	 */
	const { cancelIndex, index, indexStatus } = useIndex(apiUrl, nonce);

	/**
	 * Message log state.
	 */
	const [log, setLog] = useState([]);

	/**
	 * Sync state.
	 */
	const [state, setState] = useState({
		isCli: false,
		isComplete: false,
		isDeleting: false,
		isFailed: false,
		isPaused: false,
		isSyncing: false,
		itemsProcessed: 0,
		itemsTotal: 100,
		syncStartDateTime: null,
		syncHistory: defaultSyncHistory,
		syncTrigger: defaultSyncTrigger,
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
				setLog((log) => [
					...log,
					{
						message,
						status,
						dateTime: dateI18n('Y-m-d H:i:s', new Date()),
						isDeleting,
						id: uuid(),
					},
				]);
			}
		},
		[],
	);

	const clearLog = useCallback(
		/**
		 * Clear the log.
		 *
		 * @returns {void}
		 */
		() => {
			setLog([]);
		},
		[setLog],
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
				syncHistory: [indexTotals, ...stateRef.current.syncHistory],
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
			 * If the error has totals, add to the sync history.
			 */
			const syncHistory = response.totals
				? [response.totals, ...stateRef.current.syncHistory]
				: stateRef.current.syncHistory;

			/**
			 * Log a final message and update the sync state.
			 */
			logMessage(__('Sync failed', 'elasticpress'), 'error');

			updateState({
				isFailed: true,
				isSyncing: false,
				syncHistory,
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
		[isEpio, logMessage],
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
				isSyncing: true,
				itemsProcessed: getItemsProcessedFromIndexMeta(indexMeta),
				itemsTotal: getItemsTotalFromIndexMeta(indexMeta),
				syncStartDateTime: indexMeta.start_date_time,
				syncTrigger: indexMeta.trigger || null,
			});
		},
		[],
	);

	const syncStopped = useCallback(
		/**
		 * Set state for a stopped sync.
		 *
		 * @param {object} response Cancel request response.
		 * @returns {void}
		 */
		(response) => {
			const syncHistory = response.data
				? [response.data, ...stateRef.current.syncHistory]
				: stateRef.current.syncHistory;

			updateState({ syncHistory });
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
		 * @param {object} response API response.
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

	const doCancelIndex = useCallback(
		/**
		 * Cancel a sync.
		 *
		 * @returns {void}
		 */
		() => {
			cancelIndex()
				.then(syncStopped)
				.catch((error) => {
					if (error?.name !== 'AbortError') {
						throw error;
					}
				});
		},
		[cancelIndex, syncStopped],
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
		 * @param {object} args Sync args.
		 * @returns {void}
		 */
		(args) => {
			index(args)
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
							doIndex(args);
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
		 * @param {object} args Sync args.
		 * @returns {void}
		 */
		(args) => {
			updateState({ isPaused: false, isSyncing: true });
			doIndex(args);
		},
		[doIndex],
	);

	const startSync = useCallback(
		/**
		 * Start syncing.
		 *
		 * @param {object} args Sync args.
		 * @returns {void}
		 */
		(args) => {
			const { syncHistory } = stateRef.current;
			const isInitialSync = !syncHistory.length;

			/**
			 * We should not appear to be deleting if this is the first sync.
			 */
			const isDeleting = !!(isInitialSync || args.put_mapping);

			updateState({
				isComplete: false,
				isFailed: false,
				isDeleting,
				isPaused: false,
				isSyncing: true,
			});

			updateState({
				itemsProcessed: 0,
				syncStartDateTime: Date.now(),
				syncTrigger: args.trigger || null,
			});

			doIndex(args);
		},
		[doIndex],
	);

	const stopSync = useCallback(
		/**
		 * Stop syncing.
		 *
		 * @returns {void}
		 */
		() => {
			updateState({ isPaused: false, isSyncing: false });
			doCancelIndex();
		},
		[doCancelIndex],
	);

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
		}
	};

	/**
	 * Effects.
	 */
	useEffect(init, [doIndexStatus, syncInProgress, indexMeta, logMessage, pauseSync, startSync]);

	/**
	 * Provide state to context.
	 */
	const {
		isCli,
		isComplete,
		isDeleting,
		isFailed,
		isPaused,
		isSyncing,
		itemsProcessed,
		itemsTotal,
		syncHistory,
		syncStartDateTime,
		syncTrigger,
	} = stateRef.current;

	// eslint-disable-next-line react/jsx-no-constructed-context-values
	const contextValue = {
		clearLog,
		isCli,
		isComplete,
		isDeleting,
		isFailed,
		isPaused,
		isSyncing,
		itemsProcessed,
		itemsTotal,
		syncHistory,
		log,
		logMessage,
		pauseSync,
		resumeSync,
		startSync,
		stopSync,
		syncStartDateTime,
		syncTrigger,
	};

	return <Context.Provider value={contextValue}>{children}</Context.Provider>;
};

/**
 * Use the API Search context.
 *
 * @returns {object} API Search Context.
 */
export const useSync = () => {
	return useContext(Context);
};
