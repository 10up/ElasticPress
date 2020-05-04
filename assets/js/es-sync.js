/**
 * Handles the syncing functions for the ES indeces.
 *
 */

import { ajaxurl, epDash } from 'window';
import { showElements, hideElements } from './utils/helpers';

const featuresContainer = document.querySelector( '.ep-features' );
// const $features = jQuery( document.getElementsByClassName( 'ep-features' ) );
// const $errorOverlay = jQuery( document.getElementsByClassName( 'error-overlay' ) );
// const $progressBar = jQuery( document.getElementsByClassName( 'progress-bar' ) );
// const $syncStatusText = jQuery( document.getElementsByClassName( 'sync-status' ) );

const errorOverlay = document.querySelector( '.error-overlay' );
const progressBar = document.querySelector( '.progress-bar' );
const syncStatusText = document.querySelector( '.sync-status');

// const $startSyncButton = jQuery( document.getElementsByClassName( 'start-sync' ) );
// const $resumeSyncButton = jQuery( document.getElementsByClassName( 'resume-sync' ) );
// const $pauseSyncButton = jQuery( document.getElementsByClassName( 'pause-sync' ) );
// const $cancelSyncButton = jQuery( document.getElementsByClassName( 'cancel-sync' ) );

const startSyncButton = document.querySelector( '.start-sync' );
const resumeSyncButton = document.querySelector( '.resume-sync' );
const pauseSyncButton = document.querySelector( '.pause-sync' );
const cancelSyncButton = document.querySelector( '.cancel-sync' );

const $epCredentialsTab = jQuery( document.getElementsByClassName( 'ep-credentials-tab' ) );
const $epCredentialsHostLabel = jQuery( '.ep-host-row label' );
const $epCredentialsHostLegend = jQuery( document.getElementsByClassName( 'ep-host-legend' ) );
const $epCredentialsAdditionalFields = jQuery(
	document.getElementsByClassName( 'ep-additional-fields' ),
);
const epHostField = document.getElementById( 'ep_host' );
const epHost = epHostField ? epHostField.value : null;
const epHostNewValue = '';

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
	toProcess: 0
};


/**
 * TODO - make this better by accepting and object, and update only those keys?
 *
 * @param {string} key - object key
 * @param {string} value - new object value
 */
export const setSyncState = ( key, value ) => {
	if( syncState.hasOwnProperty( key ) ) {
		syncState[key] = value;
		return console.info( syncState );
	} else {
		return console.error( `no such property ${key} on syncState object.` );
	}
};

export const initSyncFunctions = () => {
	 checkInitialSyncStatus();
};


/**
 * On each page load for the dashboard, we detect whether or not
 * a sync has been started, so that we can update the progress
 * bar on the front end
 */
const checkInitialSyncStatus = () => {

	// a sync has been started
	if(  epDash.index_meta ) {

		if ( epDash.index_meta.wpcli_sync ) {
			// this sync was done via CLI
			// so we go ahead and update the progress bar
			// syncStatus = 'wpcli';
			setSyncState( 'status', 'wpcli' );
			updateSyncDash();
		} else {
			// this sync was done from the UI, so we
			// need to grab data from the index_meta object
			// for updating the progress bar
			updateProgressBarFromUserTriggeredSync();
		}

	} else{
		// Start a new sync automatically if needed
		checkStartAutomaticSync( epDash.auto_start_index  );
	}
};


const updateProgressBarFromUserTriggeredSync = () => {
	// how much of the sync is done,
	// and how much is remaining yet to be sync'd
	setSyncState( 'processed', epDash.index_meta.offset );
	setSyncState( 'toProcess', epDash.index_meta.found_items );

	if ( epDash.index_meta.feature_sync ) {
		setSyncState( 'feature', epDash.index_meta.feature_sync );
	}

	if ( epDash.index_meta.current_sync_item ) {
		// currentSyncItem = epDash.index_meta.current_sync_item;
		setSyncState( 'currentItem', epDash.index_meta.current_sync_item );
	}

	if ( epDash.index_meta.site_stack ) {
		// syncStack = epDash.index_meta.sync_stack;
		setSyncState( 'stack', epDash.index_meta.sync_stack );
	}

	if ( syncState.stack && syncState.stack.length ) {
		// We are mid sync
		if ( epDash.auto_start_index ) {
			// syncStatus = 'sync';
			setSyncState( 'status', 'sync' );
			updateHistory();
			updateSyncDash();
			sync();
		} else {
			// syncStatus = 'pause';
			setSyncState( 'status', 'pause' );
			updateSyncDash();
		}
	} else if ( 0 === syncState.toProcess && !epDash.index_meta.start ) {
		// Sync finished
		setSyncState( 'status', 'finished' );
		updateSyncDash();
	} else {
		// We are mid sync, so we update the info
		// and call the sync again to get the
		// updated progress
		if ( epDash.auto_start_index ) {
			setSyncState( 'status', 'sync' );
			updateHistory();
			updateSyncDash();
			sync();
		} else {
			setSyncState( 'status', 'pause' );
			updateSyncDash();
		}
	}
};


/**
 * Check to see if an index should be triggered automatically,
 * and start sync if true
 */
const checkStartAutomaticSync = ( startSync ) => {
	if ( startSync === true ) {
		// start new sync
		setSyncState( 'status', 'initialsync' );
		updateSyncDash();

		// trigger the sync
		setSyncState( 'status', 'sync' );
		updateHistory();
		sync();
	}
};


/**
 * update browser history
 */
export const updateHistory = () => {
	history.pushState( {},
		document.title, document.location.pathname + document.location.search.replace( /&do_sync/, '' )
	);
};



/**
 * Update dashboard with syncing information
 */
export const updateSyncDash = () => {

	// start styling the progress bar
	if ( 0 === syncState.processed ) {
		progressBar.style.width = 'finished' !== syncState.status ? '1%' : 0;
	} else {
		const width = ( parseInt( syncState.processed ) / parseInt( syncState.toProcess ) ) * 100;
		progressBar.style.width = `${width}%`;
	}

	// update the dashboard view, depending on
	// what the current syncStatus is
	updateDashboardView( syncState.status );
};


/**
 * Helper function to call the proper UI updates
 *
 * @param {string} status from the syncStatus var
 */
const updateDashboardView = status => {
	switch ( status ) {
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
	showElements( syncStatusText );

	// we are done syncing, so we hide all the things
	hideElements( [progressBar, pauseSyncButton, cancelSyncButton, resumeSyncButton] );

	// show the start button again
	showElements( startSyncButton );
	errorOverlay.classList.remove( 'syncing' );

	if ( syncState.feature ) {
		resetSyncStyles( syncState.feature, syncStatusText);
	}
};


/**
 * Handle the UI updates for when the sync is paused
 */
const setSyncStatePause = () => {
	let text = epDash.sync_paused;

	// get the current state of the sync, and format it for
	// display to the user
	if ( syncState.toProcess && 0 !== syncState.toProcess ) {
		text += `, ${parseInt( syncState.processed )}/${parseInt( syncState.toProcess )} ${epDash.sync_indexable_labels[
			syncState.currentItem.indexable
		].plural.toLowerCase()}`;
	}

	if ( syncState.currentItem && syncState.currentItem.url ) {
		text += ` (${syncState.currentItem.url})`;
	}
	syncStatusText.textContent = text;


	showElements( [syncStatusText, progressBar] );
	hideElements( pauseSyncButton );
	errorOverlay.classList.add( 'syncing' );

	showElements( [cancelSyncButton, resumeSyncButton ]);
	hideElements( startSyncButton );
};


/**
 * Handle the UI updates for when the sync is in progress
 */
const setSyncStateSync = () => {
	let text = epDash.sync_syncing;

	// get the current state of the sync, and format it for
	// display to the user
	if ( syncState.currentItem ) {
		if ( syncState.currentItem.indexable ) {
			text += ` ${epDash.sync_indexable_labels[
				syncState.currentItem.indexable
			].plural.toLowerCase()} ${parseInt( syncState.processed )}/${parseInt( syncState.toProcess )}`;
		}

		if ( syncState.currentItem.url ) {
			text += ` (${syncState.currentItem.url})`;
		}
	}
	syncStatusText.textContent = text;


	showElements( [syncStatusText, progressBar, pauseSyncButton ] );
	errorOverlay.classList.add( 'syncing' );
	hideElements( [cancelSyncButton, resumeSyncButton, startSyncButton])
};


/**
 * Handle the UI updates for when the sync is about to start
 */
const setSyncStateInitial = () => {
	const text = epDash.sync_initial;
	syncStatusText.textContent = text;

	showElements( [syncStatusText, progressBar, pauseSyncButton] );
	errorOverlay.classList.add( 'syncing' );

	// we're not sycning yet, so we hide this stuff
	hideElements( [cancelSyncButton, resumeSyncButton, startSyncButton] );
};


/**
 * Handle the UI updates for when the sync is already in progress
 * when the page loads, e.g. after it has been paused by the user
 */
const setSyncStateCLI = () => {
	const text = epDash.sync_wpcli;
	syncStatusText.textContent = text;

	showElements( syncStatusText );

	// sync already paused, don't show this stuff
	hideElements( [progressBar, pauseSyncButton] );
	errorOverlay.classList.add( 'syncing' );
	hideElements( [cancelSyncButton, resumeSyncButton, startSyncButton] );
};


/**
 * Handle the UI updates for when there is an error
 * with the sync
 */
const setSyncStateError = () => {
	const text = epDash.sync_error;
	syncStatusText.textContent = text;

	showElements( [syncStatusText, startSyncButton] );

	// sync is no longer in progress, hide these
	hideElements( [cancelSyncButton, resumeSyncButton, pauseSyncButton] );
	errorOverlay.classList.remove( 'syncing' );
	hideElements( progressBar );

	if ( syncState.feature ) {
		resetSyncStyles( syncState.feature, syncStatusText);
	}
};


/**
 * Handle the UI updates for when sync is cancelled
 */
const setSyncStateCancel = () => {

	hideElements( [syncStatusText, progressBar, pauseSyncButton] );
	errorOverlay.classList.remove( 'syncing' );
	hideElements( [cancelSyncButton, resumeSyncButton] );

	// since no sync is happening, we show the start button
	showElements( startSyncButton );

	if ( syncState.feature ) {
		resetSyncStyles( syncState.feature );
	}
};


/**
 * Reset sync UI - called after cancel, error, and finish
 *
 * @param {node} feature - feature box to change
 * @param {node} elsToHide - optional node to hide
 */
const resetSyncStyles = ( feature, elsToHide = null ) => {
	const featureBox = featuresContainer.querySelector( `.ep-feature-${feature}` );
	featureBox.classList.remove( 'feature-syncing' );

	// reset the feature, since we are no longer syncing
	setSyncState( 'feature', null );

	if ( elsToHide ){
		setTimeout( () => {
			hideElements( elsToHide );
		}, 7000 );
	}
}


/**
 * Cancel a sync
 */
const cancelSync = () => {
	jQuery.ajax( {
		method: 'post',
		url: ajaxurl,
		data: {
			action: 'ep_cancel_index',
			nonce: epDash.nonce,
		},
	} );


	// WIP - still need to test this
	// const postBody = {
	// 	action: 'ep_cancel_index',
	// 	nonce: epDash.nonce,
	// };

	// const formattedBody = new URLSearchParams( postBody ).toString();

	// const fetchConfig = {
	// 	method: 'post',
	// 	body: formattedBody
	// };

	// fetch( ajax, fetchConfig );
};

/**
 * Perform an elasticpress sync
 */
export const sync = async () => {
	jQuery
		.ajax( {
			method: 'post',
			url: ajaxurl,
			data: {
				action: 'ep_index',
				feature_sync: syncState.feature,
				nonce: epDash.nonce,
			},
		} )
		.done( ( response ) => {
			if ( 'sync' !== syncState.status ) {
				return;
			}

			// toProcess = response.data.found_items;
			// processed = response.data.offset;
			setSyncState( 'toProcess', response.data.found_items );
			setSyncState( 'processed', response.data.offset );

			if ( response.data.sync_stack ) {
				// syncStack = response.data.sync_stack;
				setSyncState( 'stack', response.data.sync_stack );
			}

			if ( response.data.current_sync_item ) {
				// currentSyncItem = response.data.current_sync_item;
				setSyncState( 'currentItem', response.data.current_sync_item );
			}

			if ( syncState.stack && syncState.stack.length ) {
				// We are mid multisite sync
				// syncStatus = 'sync';
				setSyncState( 'status', 'sync' );
				updateSyncDash();

				// this is recursive... calling this same function again
				// to keep updating the UI
				return sync();
			}

			if ( 0 === response.data.found_items && !response.data.start ) {
				// Sync finished
				// syncStatus = 'finished';
				setSyncState( 'status', 'finished' );
				updateSyncDash();

				if ( epDash.install_sync ) {
					document.location.replace( epDash.install_complete_url );
				}
			} else {
				// We are starting a sync
				// syncStatus = 'sync';
				setSyncState( 'status', 'sync' );
				updateSyncDash();

				sync();
			}
		} )
		.error( ( response ) => {
			if (
				response &&
				response.status &&
				400 <= parseInt( response.status ) &&
				600 > parseInt( response.status )
			) {
				// syncStatus = 'error';
				setSyncState( 'status', 'error' );
				updateSyncDash();

				cancelSync();
			}
		} );


	// WIP here
	const postBody = {
		action: 'ep_index',
		feature_sync: syncState.feature,
		nonce: epDash.nonce,
	};

	const fetchConfig = {
		method: 'post',
		body: new URLSearchParams( postBody ).toString()
	};

	console.log( 'do sync!', fetchConfig );

	// try {
	// 	const res = await fetch( ajaxurl, fetchConfig );
	// 	const response = res.json();

	// 	if ( 'sync' !== syncState.status ) {
	// 		return;
	// 	}

	// 	// toProcess = response.data.found_items;
	// 	// processed = response.data.offset;
	// 	setSyncState( 'toProcess', response.data.found_items );
	// 	setSyncState( 'processed', response.data.offset );

	// 	if ( response.data.sync_stack ) {
	// 		// syncStack = response.data.sync_stack;
	// 		setSyncState( 'stack', response.data.sync_stack );
	// 	}

	// 	if ( response.data.current_sync_item ) {
	// 		// currentSyncItem = response.data.current_sync_item;
	// 		setSyncState( 'currentItem', response.data.current_sync_item );
	// 	}

	// 	if ( syncState.stack && syncState.stack.length ) {
	// 		// We are mid multisite sync
	// 		// syncStatus = 'sync';
	// 		setSyncState( 'status', 'sync' );
	// 		updateSyncDash();

	// 		// this is recursive... calling this same function again
	// 		// to keep updating the UI
	// 		return sync();
	// 	}

	// 	if ( 0 === response.data.found_items && !response.data.start ) {
	// 		// Sync finished
	// 		// syncStatus = 'finished';
	// 		setSyncState( 'status', 'finished' );
	// 		updateSyncDash();

	// 		if ( epDash.install_sync ) {
	// 			document.location.replace( epDash.install_complete_url );
	// 		}
	// 	} else {
	// 		// We are starting a sync
	// 		// syncStatus = 'sync';
	// 		setSyncState( 'status', 'sync' );
	// 		updateSyncDash();

	// 		return sync();
	// 	}

	// } catch ( error ) {
	// 	console.error( 'Error syncing: ', error );

	// 	if (
	// 		error &&
	// 		error.status &&
	// 		400 <= parseInt( error.status ) &&
	// 		600 > parseInt( error.status )
	// 	) {
	// 		syncStatus = 'error';
	// 		updateSyncDash();
	// 		cancelSync();
	// 	}
	// }
};


/**
 *
 * @param {node} feature - feature box container
 */
export const handleReindexAfterSave = ( feature, featureName ) => {
	// syncStatus = 'initialsync';
	setSyncState( 'status', 'initialsync' );
	updateSyncDash();

	// On initial sync, remove dashboard warnings that dont make sense
	const nodesToRemove = document.querySelectorAll(
		'[data-ep-notice="no-sync"], [data-ep-notice="auto-activate-sync"], [data-ep-notice="upgrade-sync"]'
	 );
	nodesToRemove && nodesToRemove.forEach( el => el.remove() );

	// syncStatus = 'sync';
	setSyncState( 'status', 'sync' );
	feature.classList.add( 'feature-syncing' );
	// featureSync = feature;
	setSyncState( 'feature', featureName );
	sync();
};

startSyncButton && startSyncButton.addEventListener( 'click', () => {

	setSyncState( 'status', 'initialsync' );

	updateSyncDash();

	// On initial sync, remove dashboard warnings that dont make sense
	const nodesToRemove = document.querySelectorAll(
		'[data-ep-notice="no-sync"], [data-ep-notice="auto-activate-sync"], [data-ep-notice="upgrade-sync"]'
	 );
	nodesToRemove && nodesToRemove.forEach( el => el.remove() );

	setSyncState( 'status', 'sync' );
	sync();
} );

pauseSyncButton.addEventListener( 'click', () => {
// $pauseSyncButton.on( 'click', () => {
	// syncStatus = 'pause';
	setSyncState( 'status', 'pause' );

	updateSyncDash();
} );

resumeSyncButton.addEventListener( 'click', () => {
// $resumeSyncButton.on( 'click', () => {
	// syncStatus = 'sync';
	setSyncState( 'status', 'sync' );

	updateSyncDash();
	sync();
} );

cancelSyncButton.addEventListener( 'click', () => {
// $cancelSyncButton.on( 'click', () => {
	// syncStatus = 'cancel';
	setSyncState( 'status', 'cancel' );
	updateSyncDash();

	cancelSync();
} );
