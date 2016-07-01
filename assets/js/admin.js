( function( $ ) {
	var $modules = $( document.getElementsByClassName( 'ep-modules' ) );
	var $errorOverlay = $( '.error-overlay' );

	var $progressBar = $( '.progress-bar' );
	var $syncStatusText = $( '.sync-status' );
	var $startSyncButton = $( '.start-sync' );
	var $resumeSyncButton = $( '.resume-sync' );
	var $pauseSyncButton = $( '.pause-sync' );
	var $cancelSyncButton = $( '.cancel-sync' );

	var syncStatus = 'sync';
	var moduleSync = false;
	var currentSite;
	var siteStack;
	var processed = 0;
	var toProcess = 0;

	$modules.on( 'click', '.learn-more, .collapse', function( event ) {
		$module = $( this ).parents( '.ep-module' );
		$module.toggleClass( 'show-all' );
	} );

	$modules.on( 'click', '.js-toggle-module', function( event ) {
		event.preventDefault();

		var module = event.target.getAttribute( 'data-module' );

		var $button = $( this );
		$button.addClass( 'processing' );
		var $module = $modules.find( '.ep-module-' + module );

		$.ajax( {
			method: 'post',
			url: ajaxurl,
			data: {
				action: 'ep_toggle_module',
				module: module,
				nonce: ep.nonce
			}
		} ).done( function( response ) {
			setTimeout( function() {
				$button.removeClass( 'processing' );

				$module.toggleClass( 'module-active' );
				
				if ( response.data.active && response.data.reindex ) {
					syncStatus = 'sync';

					$module.addClass( 'module-syncing' );

					moduleSync = module;

					sync();
				}
			}, 700 );
		} ).error( function() {
			setTimeout( function() {
				$button.removeClass( 'processing' );
				$module.removeClass( 'module-active' );
				$module.removeClass( 'module-syncing' );
			}, 700 );
		} );
	} );

	if ( ep.index_meta ) {
		if ( ep.index_meta.wpcli ) {
			syncStatus = 'wpcli';
			updateSyncDash();
		} else {
			processed = ep.index_meta.offset;
			toProcess = ep.index_meta['found_posts'];

			if ( ep.index_meta.module_sync ) {
				moduleSync = ep.index_meta.module_sync;
			}

			if ( ep.index_meta.current_site ) {
				currentSite = ep.index_meta.current_site;
			}

			if ( ep.index_meta.site_stack ) {
				siteStack = ep.index_meta.site_stack;
			}

			if ( 0 === toProcess ) {
				if ( response.data.start ) {
					// No posts to sync
					syncStatus = 'noposts';
					updateSyncDash();
				} else {
					// Sync finished
					syncStatus = 'finished';
					updateSyncDash();
				}
			} else {
				// We are mid sync
				if ( ep.auto_start_index ) {
					syncStatus = 'sync';
					sync();
				} else {
					syncStatus = 'pause';
					updateSyncDash();
				}
			}
		}
	}

	function updateSyncDash() {
		if ( 0 === processed ) {
			$progressBar.css( { width: '1%' } );
		} else {
			var width = parseInt( processed ) / parseInt( toProcess ) * 100;
			$progressBar.css( { width: width + '%' } );
		}

		if ( 'sync' === syncStatus ) {
			var text = 'Syncing ' + parseInt( processed ) + '/' + parseInt( toProcess );

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
			var text = 'Syncing paused ' + parseInt( processed ) + '/' + parseInt( toProcess );

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
			var text = "WP CLI sync is occuring. Refresh the page to see if it's finished";

			$syncStatusText.text( text );

			$syncStatusText.show();
			$progressBar.hide();
			$pauseSyncButton.hide();
			$errorOverlay.addClass( 'syncing' );

			$cancelSyncButton.hide();
			$resumeSyncButton.hide();
			$startSyncButton.hide();
		} else if ( 'error' === syncStatus ) {
			$syncStatusText.text( 'An error occured while syncing' );
			$syncStatusText.show();
			$startSyncButton.show();
			$cancelSyncButton.hide();
			$resumeSyncButton.hide();
			$pauseSyncButton.hide();
			$errorOverlay.removeClass( 'syncing' );
			$progressBar.hide();

			if ( moduleSync ) {
				var $module = $modules.find( '.ep-module-' + moduleSync );
				$module.removeClass( 'module-syncing' );
			}

			moduleSync = null;

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

			if ( moduleSync ) {
				var $module = $modules.find( '.ep-module-' + moduleSync );
				$module.removeClass( 'module-syncing' );
			}

			moduleSync = null;
		} else if ( 'finished' === syncStatus || 'noposts' === syncStatus ) {
			if ( 'noposts' === syncStatus ) {
				$syncStatusText.text( 'No posts to sync' );
			} else {
				$syncStatusText.text( 'Sync complete' );
			}

			$progressBar.hide();
			$pauseSyncButton.hide();
			$cancelSyncButton.hide();
			$resumeSyncButton.hide();
			$startSyncButton.show();
			$errorOverlay.removeClass( 'syncing' );

			if ( moduleSync ) {
				var $module = $modules.find( '.ep-module-' + moduleSync );
				$module.removeClass( 'module-syncing' );
			}

			moduleSync = null;

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
				nonce: ep.nonce
			}
		} );
	}

	function sync() {
		$.ajax( {
			method: 'post',
			url: ajaxurl,
			data: {
				action: 'ep_index',
				module_sync: moduleSync,
				nonce: ep.nonce
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

			if ( 0 === response.data.found_posts ) {
				if ( response.data.start ) {
					// No posts to sync
					syncStatus = 'noposts';
					updateSyncDash();
				} else {
					// Sync finished
					syncStatus = 'finished';
					updateSyncDash();
				}
			} else {
				// We are starting a sync
				syncStatus = 'sync';
				updateSyncDash();

				sync();
			}
		} ).error( function() {
			syncStatus = 'error';
			updateSyncDash();

			cancelSync();
		});
	}

	$startSyncButton.on( 'click', function() {
		syncStatus = 'sync';

		sync();
	} );

	$pauseSyncButton.on( 'click', function() {
		syncStatus = 'pause';

		updateSyncDash();
	} );

	$resumeSyncButton.on( 'click', function() {
		syncStatus = 'sync';

		sync();
	} );

	$cancelSyncButton.on( 'click', function() {
		syncStatus = 'cancel';

		updateSyncDash();

		cancelSync();
	} );
	
} )( jQuery );
