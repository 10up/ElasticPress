jQuery ( document ).ready ( function ( $ )  {


	/**
	 * Show site stats
	 */
	$( '#jovo_site_select' ).change( function()  {

		console.log( 'test' );

		var siteId = $( this ).val();

		$( '.jovo_site' ).hide();

		$( '#jovo_' + siteId ).show();

	} );

} );
