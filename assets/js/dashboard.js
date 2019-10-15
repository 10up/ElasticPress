/* eslint-disable camelcase */
import jQuery from 'jquery';
import { ajaxurl, epDash } from 'window';

const $features = jQuery( document.getElementsByClassName( 'ep-features' ) );
const $errorOverlay = jQuery( document.getElementsByClassName( 'error-overlay' ) );
const $progressBar = jQuery( document.getElementsByClassName( 'progress-bar' ) );
const $syncStatusText = jQuery( document.getElementsByClassName( 'sync-status' ) );
const $startSyncButton = jQuery( document.getElementsByClassName( 'start-sync' ) );
const $resumeSyncButton = jQuery( document.getElementsByClassName( 'resume-sync' ) );
const $pauseSyncButton = jQuery( document.getElementsByClassName( 'pause-sync' ) );
const $cancelSyncButton = jQuery( document.getElementsByClassName( 'cancel-sync' ) );
const $epCredentialsTab = jQuery( document.getElementsByClassName( 'ep-credentials-tab' ) );
const $epCredentialsHostLabel = jQuery( '.ep-host-row label' );
const $epCredentialsHostLegend = jQuery( document.getElementsByClassName( 'ep-host-legend' ) );
const $epCredentialsAdditionalFields = jQuery( document.getElementsByClassName( 'ep-additional-fields' ) );
const epHostField = document.getElementById( 'ep_host' );
const epHost = epHostField ? epHostField.value : null;
let epHostNewValue = '';

let syncStatus = 'sync',
	featureSync = false,
	currentSyncItem,
	syncStack,
	processed = 0,
	toProcess = 0;

$features.on( 'click', '.learn-more, .collapse', function() {
	jQuery( this ).parents( '.ep-feature' ).toggleClass( 'show-full' );
} );

$features.on( 'click', '.settings-button', function() {
	jQuery( this ).parents( '.ep-feature' ).toggleClass( 'show-settings' );
} );

$features.on( 'click', '.save-settings', function( event ) {
	event.preventDefault();

	if ( jQuery( this ).hasClass( 'disabled' ) ) {
		return;
	}

	const feature = event.target.getAttribute( 'data-feature' );
	const $feature = $features.find( `.ep-feature-${  feature}` );
	const settings = {};
	const $settings = $feature.find( '.setting-field' );

	$settings.each( function() {
		const type = jQuery( this ).attr( 'type' );
		const name = jQuery( this ).attr( 'data-field-name' );
		const value = jQuery( this ).attr( 'value' );

		if ( 'radio' === type ) {
			if ( jQuery( this ).attr( 'checked' ) ) {
				settings[ name ] = value;
			}
		} else {
			settings[ name ] = value;
		}
	} );

	$feature.addClass( 'saving' );

	jQuery.ajax( {
		method: 'post',
		url: ajaxurl,
		data: {
			action: 'ep_save_feature',
			feature: feature,
			nonce: epDash.nonce,
			settings: settings
		}
	} ).done( ( response ) => {
		setTimeout( () => {
			$feature.removeClass( 'saving' );

			if ( '1' === settings.active ) {
				$feature.addClass( 'feature-active' );
			} else {
				$feature.removeClass( 'feature-active' );
			}

			if ( response.data.reindex ) {
				syncStatus = 'initialsync';

				updateSyncDash();

				// On initial sync, remove dashboard warnings that dont make sense
				jQuery( '[data-ep-notice="no-sync"], [data-ep-notice="auto-activate-sync"], [data-ep-notice="upgrade-sync"]' ).remove();

				syncStatus = 'sync';

				$feature.addClass( 'feature-syncing' );

				featureSync = feature;

				sync();
			}
		}, 700 );
	} ).error( () => {
		setTimeout( () => {
			$feature.removeClass( 'saving' );
			$feature.removeClass( 'feature-active' );
			$feature.removeClass( 'feature-syncing' );
		}, 700 );
	} );
} );

if ( epDash.index_meta ) {
	if ( epDash.index_meta.wpcli_sync ) {
		syncStatus = 'wpcli';
		updateSyncDash();
	} else {
		processed = epDash.index_meta.offset;
		toProcess = epDash.index_meta['found_items'];

		if ( epDash.index_meta.feature_sync ) {
			featureSync = epDash.index_meta.feature_sync;
		}

		if ( epDash.index_meta.current_sync_item ) {
			currentSyncItem = epDash.index_meta.current_sync_item;
		}

		if ( epDash.index_meta.site_stack ) {
			syncStack = epDash.index_meta.sync_stack;
		}

		if ( syncStack && syncStack.length ) {
			// We are mid sync
			if ( epDash.auto_start_index ) {
				syncStatus = 'sync';

				history.pushState( {}, document.title, document.location.pathname + document.location.search.replace( /&do_sync/, '' ) );

				updateSyncDash();
				sync();
			} else {
				syncStatus = 'pause';
				updateSyncDash();
			}
		} else {
			if ( 0 === toProcess && ! epDash.index_meta.start ) {
				// Sync finished
				syncStatus = 'finished';
				updateSyncDash();
			} else {
				// We are mid sync
				if ( epDash.auto_start_index ) {
					syncStatus = 'sync';

					history.pushState( {}, document.title, document.location.pathname + document.location.search.replace( /&do_sync/, '' ) );

					updateSyncDash();
					sync();
				} else {
					syncStatus = 'pause';
					updateSyncDash();
				}
			}
		}
	}
} else {
	// Start a new sync automatically
	if ( epDash.auto_start_index ) {
		syncStatus = 'initialsync';

		updateSyncDash();

		syncStatus = 'sync';

		history.pushState( {}, document.title, document.location.pathname + document.location.search.replace( /&do_sync/, '' ) );

		sync();
	}
}

/**
 * Update dashboard with syncing information
 */
function updateSyncDash() {
	let text;

	if ( 0 === processed ) {
		$progressBar.css( { width: '1%' } );
	} else {
		const width = parseInt( processed ) / parseInt( toProcess ) * 100;
		$progressBar.css( { width: `${width  }%` } );
	}

	if ( 'initialsync' === syncStatus ) {
		text = epDash.sync_initial;

		$syncStatusText.text( text );

		$syncStatusText.show();
		$progressBar.show();
		$pauseSyncButton.show();
		$errorOverlay.addClass( 'syncing' );

		$cancelSyncButton.hide();
		$resumeSyncButton.hide();
		$startSyncButton.hide();
	} else if ( 'sync' === syncStatus ) {
		text = epDash.sync_syncing;

		if ( currentSyncItem ) {
			if ( currentSyncItem.indexable ) {
				text += ` ${  epDash.sync_indexable_labels[ currentSyncItem.indexable ].plural.toLowerCase()  } ${  parseInt( processed )  }/${  parseInt( toProcess )}`;
			}

			if ( currentSyncItem.url ) {
				text += ` (${  currentSyncItem.url  })`;
			}
		}

		$syncStatusText.text( text );

		$syncStatusText.show();
		$progressBar.show();
		$pauseSyncButton.show();
		$errorOverlay.addClass( 'syncing' );

		$cancelSyncButton.hide();
		$resumeSyncButton.hide();
		$startSyncButton.hide();
	} else if ( 'pause' === syncStatus ) {
		text = epDash.sync_paused;

		if ( toProcess && 0 !== toProcess ) {
			text += `, ${  parseInt( processed )  }/${  parseInt( toProcess )  } ${  epDash.sync_indexable_labels[ currentSyncItem.indexable ].plural.toLowerCase()}`;
		}

		if ( currentSyncItem && currentSyncItem.url ) {
			text += ` (${  currentSyncItem.url  })`;
		}

		$syncStatusText.text( text );

		$syncStatusText.show();
		$progressBar.show();
		$pauseSyncButton.hide();
		$errorOverlay.addClass( 'syncing' );

		$cancelSyncButton.show();
		$resumeSyncButton.show();
		$startSyncButton.hide();
	} else if ( 'wpcli' === syncStatus ) {
		text = epDash.sync_wpcli;

		$syncStatusText.text( text );

		$syncStatusText.show();
		$progressBar.hide();
		$pauseSyncButton.hide();
		$errorOverlay.addClass( 'syncing' );

		$cancelSyncButton.hide();
		$resumeSyncButton.hide();
		$startSyncButton.hide();
	} else if ( 'error' === syncStatus ) {
		$syncStatusText.text( epDash.sync_error );
		$syncStatusText.show();
		$startSyncButton.show();
		$cancelSyncButton.hide();
		$resumeSyncButton.hide();
		$pauseSyncButton.hide();
		$errorOverlay.removeClass( 'syncing' );
		$progressBar.hide();

		if ( featureSync ) {
			$features.find( `.ep-feature-${  featureSync}` ).removeClass( 'feature-syncing' );
		}

		featureSync = null;

		setTimeout( () => {
			$syncStatusText.hide();
		}, 7000 );
	} else if ( 'cancel' === syncStatus ) {
		$syncStatusText.hide();
		$progressBar.hide();
		$pauseSyncButton.hide();
		$errorOverlay.removeClass( 'syncing' );

		$cancelSyncButton.hide();
		$resumeSyncButton.hide();
		$startSyncButton.show();

		if ( featureSync ) {
			$features.find( `.ep-feature-${  featureSync}` ).removeClass( 'feature-syncing' );
		}

		featureSync = null;
	} else if ( 'finished' === syncStatus ) {
		$syncStatusText.text( epDash.sync_complete );

		$syncStatusText.show();
		$progressBar.hide();
		$pauseSyncButton.hide();
		$cancelSyncButton.hide();
		$resumeSyncButton.hide();
		$startSyncButton.show();
		$errorOverlay.removeClass( 'syncing' );

		if ( featureSync ) {
			$features.find( `.ep-feature-${  featureSync}` ).removeClass( 'feature-syncing' );
		}

		featureSync = null;

		setTimeout( () => {
			$syncStatusText.hide();
		}, 7000 );
	}
}

/**
 * Cancel a sync
 */
function cancelSync() {
	jQuery.ajax( {
		method: 'post',
		url: ajaxurl,
		data: {
			action: 'ep_cancel_index',
			nonce: epDash.nonce
		}
	} );
}

/**
 * Perform an elasticpress sync
 */
function sync() {
	jQuery.ajax( {
		method: 'post',
		url: ajaxurl,
		data: {
			action: 'ep_index',
			feature_sync: featureSync,
			nonce: epDash.nonce
		}
	} ).done( ( response ) => {
		if ( 'sync' !== syncStatus ) {
			return;
		}

		toProcess = response.data.found_items;
		processed = response.data.offset;

		if ( response.data.sync_stack ) {
			syncStack = response.data.sync_stack;
		}

		if ( response.data.current_sync_item ) {
			currentSyncItem = response.data.current_sync_item;
		}

		if ( syncStack && syncStack.length ) {
			// We are mid multisite sync
			syncStatus = 'sync';
			updateSyncDash();

			sync();
			return;
		}

		if ( 0 === response.data.found_items && ! response.data.start ) {
			// Sync finished
			syncStatus = 'finished';
			updateSyncDash();

			if ( epDash.install_sync ) {
				document.location.replace( epDash.install_complete_url );
			}

		} else {
			// We are starting a sync
			syncStatus = 'sync';
			updateSyncDash();

			sync();
		}
	} ).error( ( response ) => {
		if ( response && response.status && 400 <= parseInt( response.status ) && 600 > parseInt( response.status ) ) {
			syncStatus = 'error';
			updateSyncDash();

			cancelSync();
		}
	} );

}

$startSyncButton.on( 'click', () => {
	syncStatus = 'initialsync';

	updateSyncDash();

	// On initial sync, remove dashboard warnings that dont make sense
	jQuery( '[data-ep-notice="no-sync"], [data-ep-notice="auto-activate-sync"], [data-ep-notice="upgrade-sync"]' ).remove();

	syncStatus = 'sync';
	sync();
} );

$pauseSyncButton.on( 'click', () => {
	syncStatus = 'pause';

	updateSyncDash();
} );

$resumeSyncButton.on( 'click', () => {
	syncStatus = 'sync';

	updateSyncDash();

	sync();
} );

$cancelSyncButton.on( 'click', () => {
	syncStatus = 'cancel';

	updateSyncDash();

	cancelSync();
} );

if ( epHostField ) {
	epHostField.addEventListener( 'input', ( e ) => {
		epHostNewValue = e.target.value;
	} );
}

$epCredentialsTab.on( 'click', ( e ) => {
	const epio = null !== e.currentTarget.getAttribute( 'data-epio' );
	const $target = jQuery( e.currentTarget );
	const initial = $target.hasClass( 'initial' );

	e.preventDefault();

	if ( initial && !epHostField.disabled ) {
		epHostField.value = epHost;
	} else {
		epHostField.value = epHostNewValue;
	}

	$epCredentialsTab.removeClass( 'nav-tab-active' );
	$target.addClass( 'nav-tab-active' );

	if ( epio ) {
		$epCredentialsHostLabel.text( 'ElasticPress.io Host URL' );
		$epCredentialsHostLegend.text( 'Plug in your ElasticPress.io server here!' );
		$epCredentialsAdditionalFields.show();
		$epCredentialsAdditionalFields.attr( 'aria-hidden', 'false' );
	} else {
		$epCredentialsHostLabel.text( 'Elasticsearch Host URL' );
		$epCredentialsHostLegend.text( 'Plug in your Elasticsearch server here!' );
		$epCredentialsAdditionalFields.hide();
		$epCredentialsAdditionalFields.attr( 'aria-hidden', 'true' );
	}
} );
