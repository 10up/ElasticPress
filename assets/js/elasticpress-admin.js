jQuery( document ).ready( function ( $ ) {

	var pauseIndexing    = false;
	var epSitesRemaining = 0;
	var epTotalToIndex   = 0;
	var epTotalIndexed   = 0;
	var epSitesCompleted = 0;

	// The run index button
	var run_index_button = $( '#ep_run_index' );

	// The pause index button
	var pause_index_button = $( '#ep_pause_index' );

	/**
	 * Update the progress bar every 3 seconds
	 */
	var performIndex = function ( resetBar, button, stopbtn ) {

		if ( pauseIndexing ) {
			return;
		}

		$( button ).val( ep.running_index_text ).removeClass( 'button-primary' ).attr( 'disabled', true );

		$( stopbtn ).removeClass( 'hidden' );

		//Make sure the progress bar is showing
		var bar    = $( '#progressbar' ),
		    status = $( '#progressstats' );

		bar.show();

		if ( resetBar ) {

			var progress = 0;

			if ( parseInt( ep.total_posts ) > 0 ) {

				progress = parseFloat( ep.synced_posts ) / parseFloat( ep.total_posts );
				status.text( ep.synced_posts + '/' + ep.total_posts + ' items' );

			}

			bar.progressbar(
				{
					value : progress * 100
				}
			);

		}

		processIndex( bar, button, stopbtn, status );

	};

	/**
	 * Set our variable to pause indexing
	 */
	var pauseIndex = function( pausebtn, indexbtn ) {

		var btn = $( pausebtn );
		var paused = btn.data( 'paused' );

		if ( paused === 'enabled' ) {

			btn.val( ep.index_pause_text ).data( 'paused', 'disabled' );

			pauseIndexing = false;

			performIndex( false, indexbtn, pausebtn );

		} else {

			var data = {
				action : 'ep_pause_index',
				nonce :  ep.pause_nonce
			};

			// call the ajax request to re-enable ElasticPress
			$.ajax(
				{
					url     : ajaxurl,
					type    : 'POST',
					data    : data,
					complete: function (response) {

						btn.val( ep.index_resume_text ).data( 'paused', 'enabled' );
						$( indexbtn ).val( ep.index_paused_text ).attr( 'disabled', true );

						pauseIndexing = true;

					}
				}
			);

		}

	};

	// Resets index counts
	var resetIndex = function () {

		epSitesRemaining = 0;
		epTotalToIndex   = 0;
		epTotalIndexed   = 0;

	};

	/**
	 * Send request to server and process response
	 */
	var processIndex = function ( bar, button, stopbtn, status ) {

		var data = {
			action : 'ep_launch_index',
			nonce :  ep.nonce
		};

		//call the ajax
		$.ajax(
			{
				url :      ajaxurl,
				type :     'POST',
				data :     data,
				complete : function ( response ) {

					// Handle returned error appropriately.
					if ( 'undefined' === typeof response.responseJSON || 'undefined' === typeof response.responseJSON.data ) {

						$( '#progressstats' ).text( ep.failed_text );
						$( button ).val( ep.index_complete_text ).addClass( 'button-primary' ).attr( 'disabled', false );
						$( stopbtn ).addClass( 'hidden' );
						$( '#progressbar' ).fadeOut( 'slow' );

					} else {

						var sitesCompletedText = '';

						if ( 0 === response.responseJSON.data.is_network ) {

							epTotalToIndex = response.responseJSON.data.ep_posts_total;
							epTotalIndexed = response.responseJSON.data.ep_posts_synced;

						} else {

							if ( epSitesRemaining !== response.responseJSON.data.ep_sites_remaining ) {

								epSitesRemaining = response.responseJSON.data.ep_sites_remaining;
								epTotalToIndex += response.responseJSON.data.ep_posts_total;
								epSitesCompleted ++;

							}

							sitesCompletedText = epSitesCompleted + ep.sites;
							epTotalIndexed += response.responseJSON.data.ep_current_synced;

						}

						var progress = parseFloat( epTotalIndexed ) / parseFloat( epTotalToIndex );

						bar.progressbar(
							{
								value : progress * 100
							}
						);

						status.text( epTotalIndexed + '/' + epTotalToIndex + ' ' + ep.items_indexed + sitesCompletedText );

						if ( 1 == response.responseJSON.data.ep_sync_complete ) { //indexing complete

							bar.progressbar(
								{
									value : 100
								}
							);

							setTimeout( function () {

								$( '#progressbar' ).fadeOut( 'slow' );
								$( '#progressstats' ).html( ep.complete_text );
								$( button ).val( ep.index_complete_text ).addClass( 'button-primary' ).attr( 'disabled', false );
								$( stopbtn ).addClass( 'hidden' );
								resetIndex();

							}, 1000 );

						} else {

							performIndex( false, button, stopbtn );

						}
					}
				}
			}
		);

	};

	/**
	 * Show the progress bar when indexing is paused.
	 */
	var showProgressBar = function() {

		var bar    = $( '#progressbar' ),
			status = $( '#progressstats' );

		bar.show();

		var progress = parseFloat( ep.synced_posts ) / parseFloat( ep.total_posts );

		bar.progressbar(
			{
				value : progress * 100
			}
		);

		status.text( ep.synced_posts + '/' + ep.total_posts + ' ' + ep.items_indexed );

	};

	/**
	 * Start the poll if we need it
	 */
	if ( 1 == ep.index_running && 1 != ep.paused ) {
		performIndex( true, run_index_button, pause_index_button );
	}

	if ( 1 == ep.index_running && 1 == ep.paused ) {
		showProgressBar();
	}

	/**
	 * Process indexing operation
	 */
	run_index_button.click( function ( event ) {

		event.preventDefault();

		resetIndex();

		var button = this;

		if ( ! $( button ).hasClass( 'button-primary' ) ) {
			return;
		}

		$( '#progressstats' ).text( ep.running_index_text );
		performIndex( true, button, pause_index_button ); //start the polling

	} );

	/**
	 * Process the pause index operation
	 */
	pause_index_button.click( function ( event ) {

		event.preventDefault();

		pauseIndex( this, run_index_button );

	} );

	// The stats selector
	var selector = $( '#ep_site_select' );

	/**
	 * Process changing site stats.
	 */
	selector.change( function ( event ) {

		event.preventDefault();

		var data = {
			action : 'ep_get_site_stats',
			nonce :  ep.stats_nonce,
			site :   selector.val()
		};

		//call the ajax
		$.ajax(
			{
				url :      ajaxurl,
				type :     'POST',
				data :     data,
				complete : function ( response ) {

					$( '#ep_site_stats' ).html( response.responseJSON.data );

				}

			}
		);

	} );

} );
