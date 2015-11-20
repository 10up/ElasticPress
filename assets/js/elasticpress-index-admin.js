jQuery ( document ).ready ( function ( $ ) {

	/**
	 * Update the progress bar every 3 seconds
	 */
	var performIndex = function ( resetBar, button ) {

		$ ( button ).val ( jovo.running_index_text ).removeClass ( 'button-primary' );

		//Make sure the progress bar is showing
		var bar = $ ( '#progressbar' ),
		    status = $ ( '#progressstats' );

		bar.show ();

		if ( resetBar ) {

			var progress = 0;

			if ( parseInt ( jovo.total_posts ) > 0 ) {

				progress = parseFloat ( jovo.synced_posts ) / parseFloat ( jovo.total_posts );
				status.html ( jovo.synced_posts + '/' + jovo.total_posts + 'items' );

			} else {

				status.html ( jovo.counting_items );

			}

			bar.progressbar (
				{
					value : progress * 100
				}
			);

		}

		processIndex ( bar, button, status );

	};

	/**
	 * Send request to server and process response
	 */
	var processIndex = function ( bar, button, status ) {

		var data = {
			action : 'ep_launch_index',
			nonce  : jovo.nonce
		};

		//call the ajax
		$.ajax (
			{
				url      : ajaxurl,
				type     : 'POST',
				data     : data,
				complete : function ( response ) {

					if ( 0 == response.responseJSON.data.ep_sync_complete ) { //incomplete

						var progress = parseFloat ( response.responseJSON.data.ep_posts_synced ) / parseFloat ( response.responseJSON.data.ep_posts_total );

						bar.progressbar (
							{
								value : progress * 100
							}
						);

						status.html ( response.responseJSON.data.ep_posts_synced + '/' + response.responseJSON.data.ep_posts_total + ' ' + jovo.items_indexed );

						performIndex ( false, button );

					} else { //indexing complete

						bar.progressbar (
							{
								value : 100
							}
						);

						setTimeout ( function () {

							$ ( '#progressbar' ).fadeOut ( 'slow' );
							$ ( '#progressstats' ).html ( 'Index complete <a href="">Refresh the stats</a>' );
							$ ( '#jovo_run_index' ).val ( jovo.index_complete_text ).addClass ( 'button-primary' );

						}, 1000 );

					}

				}

			}
		);

	};

	// The run index button
	var run_index_button = $ ( '#jovo_run_index' );

	/**
	 * Start the poll if we need it
	 */
	if ( 1 === jovo.index_running ) {
		performIndex ( true, run_index_button );
	}

	/**
	 * Process indexing operation
	 */
	run_index_button.click ( function ( event ) {

		event.preventDefault ();

		var button = this;

		if ( ! $ ( button ).hasClass ( 'button-primary' ) ) {
			return;
		}

		performIndex ( true, button ); //start the polling

	} );

} );
