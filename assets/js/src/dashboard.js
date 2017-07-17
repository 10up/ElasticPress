( function( $ ) {
	var $features = $( document.getElementsByClassName( 'ep-features' ) );
	var $errorOverlay = $( document.getElementsByClassName( 'error-overlay' ) );

	var $progressBar = $(document.getElementsByClassName( 'progress-bar' ) );
	var $syncStatusText = $(document.getElementsByClassName( 'sync-status' ) );
	var $startSyncButton = $(document.getElementsByClassName( 'start-sync' ) );
	var $resumeSyncButton = $(document.getElementsByClassName( 'resume-sync' ) );
	var $pauseSyncButton = $(document.getElementsByClassName( 'pause-sync' ) );
	var $cancelSyncButton = $(document.getElementsByClassName( 'cancel-sync' ) );

	var syncStatus = 'sync';
	var featureSync = false;
	var currentSite;
	var siteStack;
	var processed = 0;
	var toProcess = 0;

	$features.on( 'click', '.learn-more, .collapse', function( event ) {
		$feature = $( this ).parents( '.ep-feature' );
		$feature.toggleClass( 'show-full' );
	} );

	$features.on( 'click', '.settings-button', function( event ) {
		$feature = $( this ).parents( '.ep-feature' );
		$feature.toggleClass( 'show-settings' );
	} );

	$features.on( 'click', '.save-settings', function( event ) {
		event.preventDefault();

		if ( $( this ).hasClass( 'disabled' ) ) {
			return;
		}

		var feature = event.target.getAttribute( 'data-feature' );
		var $feature = $features.find( '.ep-feature-' + feature );

		var settings = {};

		var $settings = $feature.find('.setting-field');

		$settings.each(function() {
			var type = $( this ).attr( 'type' );
			var name = $( this ).attr( 'data-field-name' );
			var value = $( this ).attr( 'value' );

			if ( 'radio' === type ) {
				if ( $( this ).attr( 'checked' ) ) {
					settings[ name ] = value;
				}
			}
		});

		$feature.addClass( 'saving' );

		$.ajax( {
			method: 'post',
			url: ajaxurl,
			data: {
				action: 'ep_save_feature',
				feature: feature,
				nonce: epDash.nonce,
				settings: settings
			}
		} ).done( function( response ) {
			setTimeout( function() {
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
					$( '[data-ep-notice="no-sync"], [data-ep-notice="auto-activate-sync"], [data-ep-notice="upgrade-sync"]').remove();
					
					syncStatus = 'sync';

					$feature.addClass( 'feature-syncing' );

					featureSync = feature;

					sync();
				}
			}, 700 );
		} ).error( function() {
			setTimeout( function() {
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
			toProcess = epDash.index_meta['found_posts'];

			if ( epDash.index_meta.feature_sync ) {
				featureSync = epDash.index_meta.feature_sync;
			}

			if ( epDash.index_meta.current_site ) {
				currentSite = epDash.index_meta.current_site;
			}

			if ( epDash.index_meta.site_stack ) {
				siteStack = epDash.index_meta.site_stack;
			}

			if ( siteStack && siteStack.length ) {
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

	function updateSyncDash() {
		if ( 0 === processed ) {
			$progressBar.css( { width: '1%' } );
		} else {
			var width = parseInt( processed ) / parseInt( toProcess ) * 100;
			$progressBar.css( { width: width + '%' } );
		}

		if ( 'initialsync' === syncStatus ) {
			var text = epDash.sync_initial;

			$syncStatusText.text( text );

			$syncStatusText.show();
			$progressBar.show();
			$pauseSyncButton.show();
			$errorOverlay.addClass( 'syncing' );

			$cancelSyncButton.hide();
			$resumeSyncButton.hide();
			$startSyncButton.hide();
		} else if ( 'sync' === syncStatus ) {
			var text = epDash.sync_syncing + ' ' + parseInt( processed ) + '/' + parseInt( toProcess );

			if ( currentSite ) {
				text += ' (' + currentSite.url + ')'
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
			var text = epDash.sync_paused;

			if ( toProcess && 0 !== toProcess ) {
				text += ' ' + parseInt( processed ) + '/' + parseInt( toProcess );
			}

			if ( currentSite ) {
				text += ' (' + currentSite.url + ')'
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
			var text = epDash.sync_wpcli;

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
				var $feature = $features.find( '.ep-feature-' + featureSync );
				$feature.removeClass( 'feature-syncing' );
			}

			featureSync = null;

			setTimeout( function() {
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
				var $feature = $features.find( '.ep-feature-' + featureSync );
				$feature.removeClass( 'feature-syncing' );
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
				var $feature = $features.find( '.ep-feature-' + featureSync );
				$feature.removeClass( 'feature-syncing' );
			}

			featureSync = null;

			setTimeout( function() {
				$syncStatusText.hide();
			}, 7000 );
		}
	}

	function cancelSync() {
		$.ajax( {
			method: 'post',
			url: ajaxurl,
			data: {
				action: 'ep_cancel_index',
				nonce: epDash.nonce
			}
		} );
	}

	function sync() {
		$.ajax( {
			method: 'post',
			url: ajaxurl,
			data: {
				action: 'ep_index',
				feature_sync: featureSync,
				nonce: epDash.nonce
			}
		} ).done( function( response ) {
			if ( 'sync' !== syncStatus ) {
				return;
			}

			toProcess = response.data.found_posts;
			processed = response.data.offset;

			if ( response.data.site_stack ) {
				siteStack = response.data.site_stack;
			}

			if ( response.data.current_site ) {
				currentSite = response.data.current_site;
			}

			if ( siteStack && siteStack.length ) {
				// We are mid multisite sync
				syncStatus = 'sync';
				updateSyncDash();

				sync();
				return;
			}

			if ( 0 === response.data.found_posts && ! response.data.start ) {
				// Sync finished
				syncStatus = 'finished';
				updateSyncDash();
			} else {
				// We are starting a sync
				syncStatus = 'sync';
				updateSyncDash();

				sync();
			}
		} ).error( function( response ) {
			if ( response && response.status && parseInt( response.status ) >= 400 && parseInt( response.status ) < 600 ) {
				syncStatus = 'error';
				updateSyncDash();

				cancelSync();
			}
		});
	}

	$startSyncButton.on( 'click', function() {
		syncStatus = 'initialsync';

		updateSyncDash();

		// On initial sync, remove dashboard warnings that dont make sense
		$( '[data-ep-notice="no-sync"], [data-ep-notice="auto-activate-sync"], [data-ep-notice="upgrade-sync"]').remove();

		syncStatus = 'sync';
		sync();
	} );

	$pauseSyncButton.on( 'click', function() {
		syncStatus = 'pause';

		updateSyncDash();
	} );

	$resumeSyncButton.on( 'click', function() {
		syncStatus = 'sync';

		updateSyncDash();

		sync();
	} );

	$cancelSyncButton.on( 'click', function() {
		syncStatus = 'cancel';

		updateSyncDash();

		cancelSync();
	} );
	
} )( jQuery );
