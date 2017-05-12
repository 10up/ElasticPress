( function( $ ) {
	'use strict';

	$( '.notice' ).on( 'click', '.notice-dismiss', function( event ) {
		var notice = event.delegateTarget.getAttribute( 'data-ep-notice' );
		if ( ! notice ) {
			return;
		}

		$.ajax( {
			method: 'post',
			data: {
				nonce: epAdmin.nonce,
				action: 'ep_notice_dismiss',
				notice: notice
			},
			url: ajaxurl
		} );
	} );

} )( jQuery );
