import { createContext } from '@wordpress/element';

import { ep_last_sync_date, ep_last_sync_failed } from './config';

/**
 * Initial state.
 */
export const initialState = {
	isComplete: false,
	isDeleting: false,
	isSyncing: false,
	itemsProcessed: 0,
	itemsTotal: 100,
	lastSyncDateTime: ep_last_sync_date,
	lastSyncFailed: ep_last_sync_failed,
	syncStartDateTime: null,
};

/**
 * Context.
 */
export const SyncContext = createContext(initialState);
