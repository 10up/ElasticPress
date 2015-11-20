jQuery ( document ).ready ( function ( $ )  {


	/**
	 * Show site stats
	 */
	$( '#ep_site_select' ).change( function()  {

		var siteId = $( this ).val();

		$( '.ep_site' ).hide();

		$( '#ep_' + siteId ).show();

	} );

} );
