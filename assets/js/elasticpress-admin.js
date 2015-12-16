jQuery( document ).ready( function ( $ ) {

	/**
	 * Update the progress bar every 3 seconds
	 */
	var performIndex = function ( resetBar, button ) {

		$( button ).val( ep.running_index_text ).removeClass( 'button-primary' );

		//Make sure the progress bar is showing
		var bar    = $( '#progressbar' ),
		    status = $( '#progressstats' );

		bar.show();

		if ( resetBar ) {

			var progress = 0;

			if ( parseInt( ep.total_posts ) > 0 ) {

				progress = parseFloat( ep.synced_posts ) / parseFloat( ep.total_posts );
				status.html( ep.synced_posts + '/' + ep.total_posts + 'items' );

			} else {

				status.html( ep.mapping_sites );

			}

			bar.progressbar(
				{
					value : progress * 100
				}
			);

		}

		processIndex( bar, button, status );

	};

	var epSitesRemaining = 0;
	var epTotalToIndex   = 0;
	var epTotalIndexed   = 0;

	// Resets index counts
	var resetIndex = function () {

		epSitesRemaining = 0;
		epTotalToIndex   = 0;
		epTotalIndexed   = 0;

	};

	/**
	 * Send request to server and process response
	 */
	var processIndex = function ( bar, button, status ) {

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

					console.log( response.responseJSON.data );

					if ( 0 == response.responseJSON.data.ep_sync_complete ) { //incomplete

						if ( 0 < response.responseJSON.data.ep_sites_remaining && epSitesRemaining !== response.responseJSON.data.ep_sites_remaining ) {

							epSitesRemaining = response.responseJSON.data.ep_sites_remaining;
							epTotalToIndex += response.responseJSON.data.ep_posts_total;

						} else if ( 0 === response.responseJSON.data.is_network ) {

							epTotalToIndex = response.responseJSON.data.ep_posts_total;

						}

						epTotalIndexed += response.responseJSON.data.ep_posts_synced;

						var progress = parseFloat( epTotalIndexed ) / parseFloat( epTotalToIndex );

						bar.progressbar(
							{
								value : progress * 100
							}
						);

						status.html( epTotalIndexed + '/' + epTotalToIndex + ' ' + ep.items_indexed );

						performIndex( false, button );

					} else { //indexing complete

						bar.progressbar(
							{
								value : 100
							}
						);

						setTimeout( function () {

							$( '#progressbar' ).fadeOut( 'slow' );
							$( '#progressstats' ).html( 'Index complete <a href="">Refresh the stats</a>' );
							$( '#ep_run_index' ).val( ep.index_complete_text ).addClass( 'button-primary' );
							resetIndex();

						}, 1000 );

					}

				}

			}
		);

	};

	// The run index button
	var run_index_button = $( '#ep_run_index' );

	/**
	 * Start the poll if we need it
	 */
	if ( 1 === ep.index_running ) {
		performIndex( true, run_index_button );
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

		performIndex( true, button ); //start the polling

	} );

	// The stats selector
	var selector = $( '#ep_site_select' );

	/**
	 * Process changing site stats.
	 */
	selector.change( function ( event ) {

		event.preventDefault();

		console.log( selector.val() );

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
