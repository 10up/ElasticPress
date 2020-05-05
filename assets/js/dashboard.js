/* eslint-disable camelcase, import/no-unresolved, no-use-before-define */
import 'promise-polyfill/src/polyfill';
import { ajaxurl, epDash } from 'window';
import { initSyncFunctions, handleReindexAfterSave } from './es-sync';
import initSettingsPage from './es-settings';
import { findAncestor } from './utils/helpers';

let features;

/**
 * main Dashboard init function. Pulls in sync functions
 * from ./es-sync.js as well
 */
const initDashboard = () => {
	features = document.querySelector('.ep-features');
	if (features) {
		features.addEventListener('click', handleFeatureClicks);
	}
	initSyncFunctions();
	initSettingsPage();
};

document.addEventListener('DOMContentLoaded', initDashboard);

/**
 * Delegates clicks on the feature boxes to the respective
 * handlers
 *
 * @param {event} event - click event
 */
const handleFeatureClicks = (event) => {
	const { target } = event;

	// toggles the "more" extra information inside the feature box
	if (target.matches('.learn-more') || target.matches('.collapse')) {
		toggleClassOnParent(target, 'show-full');
		return;
	}

	// showing/hiding the "settings" part of the feature box
	if (target.matches('.settings-button')) {
		toggleClassOnParent(target, 'show-settings');
		return;
	}

	// calls an async function to save the config
	// to the db
	if (target.matches('.save-settings')) {
		saveFeatureSettings(event);
	}
};

/**
 * Handles the toggling for each Feature section
 *
 * @param {node} childNode - click target node
 * @param {string} className - class to toggle on the parent
 * @param {string} matchClass - optional class to match on the parent, defaults to 'ep-feature'
 */
const toggleClassOnParent = (childNode, className, matchClass = 'ep-feature') => {
	const parent = findAncestor(childNode, matchClass);
	if (!parent.classList.contains(className)) {
		parent.classList.add(className);
	} else {
		parent.classList.remove(className);
	}
};

/**
 * handles the ajax form submit to save the new settings
 *
 * @param {event} event - click event on the save button
 */

const saveFeatureSettings = (event) => {
	event.preventDefault();
	const { target } = event;

	// don't do anything if this feature is disabled
	if (target.classList.contains('disabled')) {
		return;
	}

	const featureName = event.target.getAttribute('data-feature');
	const currentFeature = features.querySelector(`.ep-feature-${featureName}`);
	const inputFields = currentFeature.querySelectorAll('.setting-field');
	const settings = {};

	// get the current selected settings
	// to pass into the update function
	inputFields.forEach((field) => {
		const type = field.getAttribute('type');
		const name = field.getAttribute('data-field-name');

		if (type === 'radio') {
			if (field.checked) {
				settings[name] = field.value;
			}
		} else {
			settings[name] = field.value;
		}
	});

	updateFeature(currentFeature, settings, featureName);
};

/**
 * Async function to handle ajax submit to update the setting
 *
 * @param {node} feature - feature box container
 * @param {object} settings - form values
 * @param {string} featureName - name of feature to update
 */
const updateFeature = async (feature, settings, featureName) => {
	const postBody = {
		nonce: epDash.nonce,
		action: 'ep_save_feature',
		feature: featureName,
	};

	// request body requires formatting to read as query string,
	// rather than as JSON - can the back-end handle json to avoid this?
	const formattedBody = new URLSearchParams(postBody).toString();

	// jQuery used to format this part for for free...
	// since the object is nested, the encoding doesn't work
	// on the postBody object as-is...
	const newSettings = Object.keys(settings)
		.map((key) => {
			return `${encodeURIComponent(`settings[${key}]`)}=${encodeURIComponent(settings[key])}`;
		})
		.join('&')
		.replace(/%20/g, '+');

	const fetchConfig = {
		method: 'POST',
		body: `${formattedBody}&${newSettings}`,
		headers: {
			'Content-type': 'application/x-www-form-urlencoded; charset=UTF-8',
		},
	};

	try {
		feature.classList.add('saving');
		const res = await fetch(ajaxurl, fetchConfig);
		const response = await res.json();

		// when complete...
		feature.classList.remove('saving');

		// set the current active status on the feature box
		if (settings.active === '1') {
			feature.classList.add('feature-active');
		} else {
			feature.classList.remove('feature-active');
		}

		if (response.data.reindex) {
			handleReindexAfterSave(feature, featureName);
		}
	} catch (error) {
		console.error(`There was an error updating the settings for ${featureName}`, error);

		// not sure why this has a timeout... keeping the legacy code here.
		setTimeout(() => {
			feature.classList.remove('saving', 'feature-active', 'feature-syncing');
		}, 700);
	}
};

// $features.on( 'click', '.save-settings', function ( event ) {

// 	event.preventDefault();

// 	if ( jQuery( this ).hasClass( 'disabled' ) ) {
// 		return;
// 	}

// 	const feature = event.target.getAttribute( 'data-feature' );
// 	const $feature = $features.find( `.ep-feature-${feature}` );
// 	const settings = {};
// 	const $settings = $feature.find( '.setting-field' );

// 	$settings.each( function () {
// 		const $this = jQuery( this );
// 		const type = $this.attr( 'type' );
// 		const name = $this.attr( 'data-field-name' );
// 		const value = $this.val();

// 		if ( 'radio' === type ) {
// 			if ( $this.is( ':checked' ) ) {
// 				settings[name] = value;
// 			}
// 		} else {
// 			settings[name] = value;
// 		}
// 	} );

// 	$feature.addClass( 'saving' );

// 	jQuery
// 		.ajax( {
// 			method: 'post',
// 			url: ajaxurl,
// 			data: {
// 				action: 'ep_save_feature',
// 				feature,
// 				nonce: epDash.nonce,
// 				settings,
// 			},
// 		} )
// 		.done( ( response ) => {
// 			setTimeout( () => {
// 				$feature.removeClass( 'saving' );

// 				if ( '1' === settings.active ) {
// 					$feature.addClass( 'feature-active' );
// 				} else {
// 					$feature.removeClass( 'feature-active' );
// 				}

// 				if ( response.data.reindex ) {
// 					syncStatus = 'initialsync';

// 					updateSyncDash();

// 					// On initial sync, remove dashboard warnings that dont make sense
// 					jQuery(
// 						'[data-ep-notice="no-sync"], [data-ep-notice="auto-activate-sync"], [data-ep-notice="upgrade-sync"]',
// 					).remove();

// 					syncStatus = 'sync';

// 					$feature.addClass( 'feature-syncing' );

// 					featureSync = feature;

// 					sync();
// 				}
// 			}, 700 );
// 		} )
// 		.error( () => {
// 			setTimeout( () => {
// 				$feature.removeClass( 'saving' );
// 				$feature.removeClass( 'feature-active' );
// 				$feature.removeClass( 'feature-syncing' );
// 			}, 700 );
// 		} );
// } );

// if ( epDash.index_meta ) {
// 	if ( epDash.index_meta.wpcli_sync ) {
// 		// syncStatus = 'wpcli';
// 		setSyncState( 'status', 'wpcli' );
// 		updateSyncDash();
// 	} else {
// 		processed = epDash.index_meta.offset;
// 		toProcess = epDash.index_meta.found_items;

// 		if ( epDash.index_meta.feature_sync ) {
// 			// featureSync = epDash.index_meta.feature_sync;
// 			setSyncState( 'feature', epDash.index_meta.feature_sync );
// 		}

// 		if ( epDash.index_meta.current_sync_item ) {
// 			currentSyncItem = epDash.index_meta.current_sync_item;
// 		}

// 		if ( epDash.index_meta.site_stack ) {
// 			syncStack = epDash.index_meta.sync_stack;
// 		}

// 		if ( syncStack && syncStack.length ) {
// 			// We are mid sync
// 			if ( epDash.auto_start_index ) {
// 				// syncStatus = 'sync';
// 				setSyncState( 'status', 'sync' );

// 				history.pushState(
// 					{},
// 					document.title,
// 					document.location.pathname + document.location.search.replace( /&do_sync/, '' ),
// 				);

// 				updateSyncDash();
// 				sync();
// 			} else {
// 				// syncStatus = 'pause';
// 				setSyncState( 'status', 'pause' );
// 				updateSyncDash();
// 			}
// 		} else if ( 0 === toProcess && !epDash.index_meta.start ) {
// 			// Sync finished
// 			// syncStatus = 'finished';
// 			setSyncState( 'status', 'finished' );
// 			updateSyncDash();
// 		} else {
// 			// We are mid sync
// 			if ( epDash.auto_start_index ) {
// 				// syncStatus = 'sync';
// 				setSyncState( 'status', 'sync' );

// 				history.pushState(
// 					{},
// 					document.title,
// 					document.location.pathname + document.location.search.replace( /&do_sync/, '' ),
// 				);

// 				updateSyncDash();
// 				sync();
// 			} else {
// 				// syncStatus = 'pause';
// 				setSyncState( 'status', 'pause' );
// 				updateSyncDash();
// 			}
// 		}
// 	}
// } else {
// 	// Start a new sync automatically
// 	if ( epDash.auto_start_index ) {
// 		// syncStatus = 'initialsync';
// 		setSyncState( 'status', 'initialSync' );

// 		updateSyncDash();

// 		// syncStatus = 'sync';
// 		setSyncState( 'status', 'sync' );

// 		history.pushState(
// 			{},
// 			document.title,
// 			document.location.pathname + document.location.search.replace( /&do_sync/, '' ),
// 		);

// 		sync();
// 	}
// }

/**
 * Update dashboard with syncing information
 */
// const updateSyncDash = () => {
// 	let text;

// 	if ( 0 === processed ) {
// 		$progressBar.css( { width: '1%' } );
// 	} else {
// 		const width = ( parseInt( processed ) / parseInt( toProcess ) ) * 100;
// 		$progressBar.css( { width: `${width}%` } );
// 	}

// 	if ( 'initialsync' === syncStatus ) {
// 		text = epDash.sync_initial;

// 		$syncStatusText.text( text );

// 		$syncStatusText.show();
// 		$progressBar.show();
// 		$pauseSyncButton.show();
// 		$errorOverlay.addClass( 'syncing' );

// 		$cancelSyncButton.hide();
// 		$resumeSyncButton.hide();
// 		$startSyncButton.hide();
// 	} else if ( 'sync' === syncStatus ) {
// 		text = epDash.sync_syncing;

// 		if ( currentSyncItem ) {
// 			if ( currentSyncItem.indexable ) {
// 				text += ` ${epDash.sync_indexable_labels[
// 					currentSyncItem.indexable
// 				].plural.toLowerCase()} ${parseInt( processed )}/${parseInt( toProcess )}`;
// 			}

// 			if ( currentSyncItem.url ) {
// 				text += ` (${currentSyncItem.url})`;
// 			}
// 		}

// 		$syncStatusText.text( text );

// 		$syncStatusText.show();
// 		$progressBar.show();
// 		$pauseSyncButton.show();
// 		$errorOverlay.addClass( 'syncing' );

// 		$cancelSyncButton.hide();
// 		$resumeSyncButton.hide();
// 		$startSyncButton.hide();
// 	} else if ( 'pause' === syncStatus ) {
// 		text = epDash.sync_paused;

// 		if ( toProcess && 0 !== toProcess ) {
// 			text += `, ${parseInt( processed )}/${parseInt( toProcess )} ${epDash.sync_indexable_labels[
// 				currentSyncItem.indexable
// 			].plural.toLowerCase()}`;
// 		}

// 		if ( currentSyncItem && currentSyncItem.url ) {
// 			text += ` (${currentSyncItem.url})`;
// 		}

// 		$syncStatusText.text( text );

// 		$syncStatusText.show();
// 		$progressBar.show();
// 		$pauseSyncButton.hide();
// 		$errorOverlay.addClass( 'syncing' );

// 		$cancelSyncButton.show();
// 		$resumeSyncButton.show();
// 		$startSyncButton.hide();
// 	} else if ( 'wpcli' === syncStatus ) {
// 		text = epDash.sync_wpcli;

// 		$syncStatusText.text( text );

// 		$syncStatusText.show();
// 		$progressBar.hide();
// 		$pauseSyncButton.hide();
// 		$errorOverlay.addClass( 'syncing' );

// 		$cancelSyncButton.hide();
// 		$resumeSyncButton.hide();
// 		$startSyncButton.hide();
// 	} else if ( 'error' === syncStatus ) {
// 		$syncStatusText.text( epDash.sync_error );
// 		$syncStatusText.show();
// 		$startSyncButton.show();
// 		$cancelSyncButton.hide();
// 		$resumeSyncButton.hide();
// 		$pauseSyncButton.hide();
// 		$errorOverlay.removeClass( 'syncing' );
// 		$progressBar.hide();

// 		if ( featureSync ) {
// 			$features.find( `.ep-feature-${featureSync}` ).removeClass( 'feature-syncing' );
// 		}

// 		featureSync = null;

// 		setTimeout( () => {
// 			$syncStatusText.hide();
// 		}, 7000 );
// 	} else if ( 'cancel' === syncStatus ) {
// 		$syncStatusText.hide();
// 		$progressBar.hide();
// 		$pauseSyncButton.hide();
// 		$errorOverlay.removeClass( 'syncing' );

// 		$cancelSyncButton.hide();
// 		$resumeSyncButton.hide();
// 		$startSyncButton.show();

// 		if ( featureSync ) {
// 			$features.find( `.ep-feature-${featureSync}` ).removeClass( 'feature-syncing' );
// 		}

// 		featureSync = null;
// 	} else if ( 'finished' === syncStatus ) {
// 		$syncStatusText.text( epDash.sync_complete );

// 		$syncStatusText.show();
// 		$progressBar.hide();
// 		$pauseSyncButton.hide();
// 		$cancelSyncButton.hide();
// 		$resumeSyncButton.hide();
// 		$startSyncButton.show();
// 		$errorOverlay.removeClass( 'syncing' );

// 		if ( featureSync ) {
// 			$features.find( `.ep-feature-${featureSync}` ).removeClass( 'feature-syncing' );
// 		}

// 		featureSync = null;

// 		setTimeout( () => {
// 			$syncStatusText.hide();
// 		}, 7000 );
// 	}
// };
