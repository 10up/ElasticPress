/* eslint-disable no-use-before-define, no-lonely-if, no-restricted-globals */
/**
 * This file handles the syncing functions for the ES indeces.
 */

import { showElements, hideElements } from './utils/helpers';

const { ajaxurl, epDash } = window;

// DOM nodes
const featuresContainer = document.querySelector('.ep-features');
const errorOverlay = document.querySelector('.error-overlay');
const progressBar = document.querySelector('.progress-bar');
const syncStatusText = document.querySelector('.sync-status');
const startSyncButton = document.querySelector('.start-sync');
const resumeSyncButton = document.querySelector('.resume-sync');
const pauseSyncButton = document.querySelector('.pause-sync');
const cancelSyncButton = document.querySelector('.cancel-sync');

/**
 * status - what is the status of the current sync
 * feature - which feature triggered the sync (e.g. autosuggest)
 * currentItem - which EP indexable is currently being sync'd
 * stack - info about the website: blog url, etc.
 * processed - how much content has been processed during this sync
 * toProcess - how much content still needs to be sync'd
 */
const syncState = {
	status: 'sync',
	feature: null,
	currentItem: null,
	stack: null,
	processed: 0,
	toProcess: 0,
};

/**
 * TODO - make this better by accepting and object, and update only those keys?
 *
 * @param {string} key - object key
 * @param {string} value - new object value
 * @returns {null} - no return value
 */
export const setSyncState = (key, value) => {
	// eslint-disable-next-line
	if (syncState.hasOwnProperty(key)) {
		syncState[key] = value;
		return null;
	}
	// eslint-disable-next-line
	console.error(`no such property ${key} on syncState object.`);
	return null;
};

/**
 * Update dashboard progress bar, and
 * update the rest of the sync UI by
 * calling updateDashboardView
 */
export const updateSyncDash = () => {
	// start styling the progress bar
	if (syncState.processed === 0) {
		progressBar.style.width = syncState.status !== 'finished' ? '1%' : 0;
	} else {
		const width = (parseInt(syncState.processed, 10) / parseInt(syncState.toProcess, 10)) * 100;
		progressBar.style.width = `${width}%`;
	}

	// update the dashboard view, depending on
	// what the current syncStatus is
	updateDashboardView(syncState.status);
};

/**
 * On each page load for the dashboard, we detect whether or not
 * a sync has been started, so that we can update the progress
 * bar on the front end
 */
const checkInitialSyncStatus = () => {
	// a sync has been started
	if (epDash.index_meta) {
		if (epDash.index_meta.wpcli_sync) {
			// this sync was done via CLI
			// so we go ahead and update the progress bar
			// syncStatus = 'wpcli';
			setSyncState('status', 'wpcli');
			updateSyncDash();
		} else {
			// this sync was done from the UI, so we
			// need to grab data from the index_meta object
			// for updating the progress bar
			updateProgressBarFromUserTriggeredSync();
		}
	} else {
		// Start a new sync automatically if needed
		checkStartAutomaticSync(epDash.auto_start_index);
	}
};

/**
 * Not sure if the name of this is 100% correct...
 * Sets the styles on the progress bar if a sync
 * was already in progress on page load
 */
const updateProgressBarFromUserTriggeredSync = () => {
	// how much of the sync is done,
	// and how much is remaining yet to be sync'd
	setSyncState('processed', epDash.index_meta.offset);
	setSyncState('toProcess', epDash.index_meta.found_items);

	if (epDash.index_meta.feature_sync) {
		setSyncState('feature', epDash.index_meta.feature_sync);
	}

	if (epDash.index_meta.current_sync_item) {
		// currentSyncItem = epDash.index_meta.current_sync_item;
		setSyncState('currentItem', epDash.index_meta.current_sync_item);
	}

	if (epDash.index_meta.site_stack) {
		// syncStack = epDash.index_meta.sync_stack;
		setSyncState('stack', epDash.index_meta.sync_stack);
	}

	if (syncState.stack && syncState.stack.length) {
		// We are mid sync
		if (epDash.auto_start_index) {
			// syncStatus = 'sync';
			setSyncState('status', 'sync');
			updateHistory();
			updateSyncDash();
			sync();
		} else {
			// syncStatus = 'pause';
			setSyncState('status', 'pause');
			updateSyncDash();
		}
	} else if (syncState.toProcess === 0 && !epDash.index_meta.start) {
		// Sync finished
		setSyncState('status', 'finished');
		updateSyncDash();
	} else {
		// We are mid sync, so we update the info
		// and call the sync again to get the
		// updated progress
		if (epDash.auto_start_index) {
			setSyncState('status', 'sync');
			updateHistory();
			updateSyncDash();
			sync();
		} else {
			setSyncState('status', 'pause');
			updateSyncDash();
		}
	}
};

/**
 * Check to see if an index should be triggered automatically,
 * and start sync if true
 *
 * @param {boolean} startSync - boolean from epDash object to check whether a sync should be started
 */
const checkStartAutomaticSync = (startSync) => {
	if (startSync === true) {
		// start new sync
		setSyncState('status', 'initialsync');
		updateSyncDash();

		// trigger the sync
		setSyncState('status', 'sync');
		updateHistory();
		sync();
	}
};

/**
 * Update browser history
 */
export const updateHistory = () => {
	history.pushState(
		{},
		document.title,
		document.location.pathname + document.location.search.replace(/&do_sync/, ''),
	);
};

/**
 * Helper function to call the proper UI updates,
 * depending on the value of the sync status
 *
 * @param {string} status from the syncStatus var
 */
const updateDashboardView = (status) => {
	switch (status) {
		case 'initialsync':
			setSyncStateInitial();
			break;
		case 'sync':
			setSyncStateSync();
			break;
		case 'pause':
			setSyncStatePause();
			break;
		case 'wpcli':
			setSyncStateCLI();
			break;
		case 'error':
			setSyncStateError();
			break;
		case 'cancel':
			setSyncStateCancel();
			break;
		case 'finished':
			setSyncStateFinished();
			break;
		default:
			break;
	}
};

/**
 * Handle the UI updates for when the sync is finished
 */
const setSyncStateFinished = () => {
	const text = epDash.sync_complete;

	// update status text and show it
	syncStatusText.textContent = text;
	showElements(syncStatusText);

	// we are done syncing, so we hide all the things
	hideElements([progressBar, pauseSyncButton, cancelSyncButton, resumeSyncButton]);

	// show the start button again
	showElements(startSyncButton);
	errorOverlay.classList.remove('syncing');

	if (syncState.feature) {
		resetSyncStyles(syncState.feature, syncStatusText);
	}
};

/**
 * Handle the UI updates for when the sync is paused
 */
const setSyncStatePause = () => {
	let text = epDash.sync_paused;

	// get the current state of the sync, and format it for
	// display to the user
	if (syncState.toProcess && syncState.toProcess !== 0) {
		text += `, ${parseInt(syncState.processed, 10)}/${parseInt(
			syncState.toProcess,
			10,
		)} ${epDash.sync_indexable_labels[syncState.currentItem.indexable].plural.toLowerCase()}`;
	}

	if (syncState.currentItem && syncState.currentItem.url) {
		text += ` (${syncState.currentItem.url})`;
	}
	syncStatusText.textContent = text;

	showElements([syncStatusText, progressBar]);
	hideElements(pauseSyncButton);
	errorOverlay.classList.add('syncing');

	showElements([cancelSyncButton, resumeSyncButton]);
	hideElements(startSyncButton);
};

/**
 * Handle the UI updates for when the sync is in progress
 */
const setSyncStateSync = () => {
	let text = epDash.sync_syncing;

	// get the current state of the sync, and format it for
	// display to the user
	if (syncState.currentItem) {
		if (syncState.currentItem.indexable) {
			text += ` ${epDash.sync_indexable_labels[
				syncState.currentItem.indexable
			].plural.toLowerCase()} ${parseInt(syncState.processed, 10)}/${parseInt(
				syncState.toProcess,
				10,
			)}`;
		}

		if (syncState.currentItem.url) {
			text += ` (${syncState.currentItem.url})`;
		}
	}
	syncStatusText.textContent = text;

	showElements([syncStatusText, progressBar, pauseSyncButton]);
	errorOverlay.classList.add('syncing');
	hideElements([cancelSyncButton, resumeSyncButton, startSyncButton]);
};

/**
 * Handle the UI updates for when the sync is about to start
 */
const setSyncStateInitial = () => {
	const text = epDash.sync_initial;
	syncStatusText.textContent = text;

	showElements([syncStatusText, progressBar, pauseSyncButton]);
	errorOverlay.classList.add('syncing');

	// we're not sycning yet, so we hide this stuff
	hideElements([cancelSyncButton, resumeSyncButton, startSyncButton]);
};

/**
 * Handle the UI updates for when the sync is already in progress
 * when the page loads, e.g. after it has been paused by the user
 */
const setSyncStateCLI = () => {
	const text = epDash.sync_wpcli;
	syncStatusText.textContent = text;

	showElements(syncStatusText);

	// sync already paused, don't show this stuff
	hideElements([progressBar, pauseSyncButton]);
	errorOverlay.classList.add('syncing');
	hideElements([cancelSyncButton, resumeSyncButton, startSyncButton]);
};

/**
 * Handle the UI updates for when there is an error
 * with the sync
 */
const setSyncStateError = () => {
	const text = epDash.sync_error;
	syncStatusText.textContent = text;

	showElements([syncStatusText, startSyncButton]);

	// sync is no longer in progress, hide these
	hideElements([cancelSyncButton, resumeSyncButton, pauseSyncButton]);
	errorOverlay.classList.remove('syncing');
	hideElements(progressBar);

	if (syncState.feature) {
		resetSyncStyles(syncState.feature, syncStatusText);
	}
};

/**
 * Handle the UI updates for when sync is cancelled
 */
const setSyncStateCancel = () => {
	hideElements([syncStatusText, progressBar, pauseSyncButton]);
	errorOverlay.classList.remove('syncing');
	hideElements([cancelSyncButton, resumeSyncButton]);

	// since no sync is happening, we show the start button
	showElements(startSyncButton);

	if (syncState.feature) {
		resetSyncStyles(syncState.feature);
	}
};

/**
 * resetSyncStyles - Resets the sync UI,
 * called after cancel, error, and finish
 *
 * @param {Node} feature - feature box to change
 * @param {Node} elsToHide - optional node to hide
 */
const resetSyncStyles = (feature, elsToHide = null) => {
	const featureBox = featuresContainer.querySelector(`.ep-feature-${feature}`);
	featureBox.classList.remove('feature-syncing');

	// reset the feature, since we are no longer syncing
	setSyncState('feature', null);

	// in this case, a sync has completed, and the
	// timeout is to hide the "sync completed" text
	if (elsToHide) {
		setTimeout(() => {
			hideElements(elsToHide);
		}, 7000);
	}
};

/**
 * Cancel a sync - not an async function because we
 * don't need to await the fetch call
 */
const cancelSync = () => {
	const postBody = {
		action: 'ep_cancel_index',
		nonce: epDash.nonce,
	};

	const fetchConfig = {
		method: 'POST',
		body: new URLSearchParams(postBody).toString(),
		headers: {
			'Content-type': 'application/x-www-form-urlencoded; charset=UTF-8',
		},
	};

	fetch(ajaxurl, fetchConfig);
};

/**
 * Perform an elasticpress sync. It is async because we
 * await the fetch request
 */
export const sync = async () => {
	const postBody = {
		action: 'ep_index',
		feature_sync: syncState.feature,
		nonce: epDash.nonce,
	};

	const fetchConfig = {
		method: 'POST',
		body: new URLSearchParams(postBody).toString(),
		headers: {
			'Content-type': 'application/x-www-form-urlencoded; charset=UTF-8',
		},
	};

	try {
		const res = await fetch(ajaxurl, fetchConfig);
		const response = await res.json();

		if (syncState.status !== 'sync') {
			return;
		}

		setSyncState('toProcess', response.data.found_items);
		setSyncState('processed', response.data.offset);

		if (response.data.sync_stack) {
			setSyncState('stack', response.data.sync_stack);
		}

		if (response.data.current_sync_item) {
			setSyncState('currentItem', response.data.current_sync_item);
		}

		if (syncState.stack && syncState.stack.length) {
			// We are mid multisite sync
			// syncStatus = 'sync';
			setSyncState('status', 'sync');
			updateSyncDash();

			// this is recursive... calling this same function again
			// to keep updating the UI
			// eslint-disable-next-line
			return sync();
		}

		if (response.data.found_items === 0 && !response.data.start) {
			// Sync finished
			setSyncState('status', 'finished');
			updateSyncDash();

			if (epDash.install_sync) {
				document.location.replace(epDash.install_complete_url);
			}
		} else {
			// We are starting a sync
			setSyncState('status', 'sync');
			updateSyncDash();

			sync();
			return;
		}
	} catch (error) {
		// eslint-disable-next-line
		console.error('Error syncing: ', error);

		if (
			error &&
			error.status &&
			parseInt(error.status, 10) >= 400 &&
			parseInt(error.status, 10) < 600
		) {
			setSyncState('status', 'error');
			updateSyncDash();
			cancelSync();
		}
	}
};

/**
 * Helper method, called when a feature is updated,
 * and calls the initializeSync method, passing along
 * the true boolean to tell the sync it is for a feature
 *
 * @param {Node} feature - feature box container
 * @param {string} featureName - string name of the ep feature to index
 */
export const handleReindexAfterSave = (feature, featureName) =>
	initializeSync(feature, featureName, true);

/**
 * InitializeSync - prepares UI and sets up data to perform the sync
 *
 * @param {Node} feature - feature box where we trigger the sync from
 * @param {string} featureName - string name of feature to sync
 * @param {boolean} isFeature - specifies whether it is a generic or feature-specific sync
 */
const initializeSync = (feature, featureName, isFeature = false) => {
	setSyncState('status', 'initialsync');
	updateSyncDash();

	// On initial sync, remove dashboard warnings that dont make sense
	const nodesToRemove = document.querySelectorAll(
		'[data-ep-notice="no-sync"], [data-ep-notice="auto-activate-sync"], [data-ep-notice="upgrade-sync"]',
	);
	if (nodesToRemove) {
		nodesToRemove.forEach((el) => el.remove());
	}

	setSyncState('status', 'sync');

	if (isFeature) {
		feature.classList.add('feature-syncing');
		setSyncState('feature', featureName);
	}
	sync();
};

/**
 * Event handlers for sync buttons, called on init
 */
const addSyncButtonEventHandlers = () => {
	if (startSyncButton) {
		startSyncButton.addEventListener('click', initializeSync);
	}

	if (pauseSyncButton) {
		pauseSyncButton.addEventListener('click', () => {
			setSyncState('status', 'pause');
			updateSyncDash();
		});
	}

	if (resumeSyncButton) {
		resumeSyncButton.addEventListener('click', () => {
			setSyncState('status', 'sync');
			updateSyncDash();
			sync();
		});
	}

	if (cancelSyncButton) {
		cancelSyncButton.addEventListener('click', () => {
			setSyncState('status', 'cancel');
			updateSyncDash();
			cancelSync();
		});
	}
};

/**
 * Init method, imported by dashboard
 */
export const initSyncFunctions = () => {
	checkInitialSyncStatus();
	addSyncButtonEventHandlers();
};
