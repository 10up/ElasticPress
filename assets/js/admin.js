import jQuery from 'jquery';
import { epAdmin, ajaxurl } from 'window';

jQuery( '.notice' ).on( 'click', '.notice-dismiss', ( event ) => {
	const notice = event.delegateTarget.getAttribute( 'data-ep-notice' );

	if ( ! notice ) {
		return;
	}

	jQuery.ajax( {
		method: 'post',
		data: {
			nonce: epAdmin.nonce,
			action: 'ep_notice_dismiss',
			notice: notice
		},
		url: ajaxurl
	} );
} );

jQuery( '.weighting-settings input[type=range]' ).change( function( event ) {
	const el = jQuery( this );

	el.prev( 'label' ).find( '.weighting-value' ).text( el.val() );
} );

jQuery( '.weighting-settings .searchable input[type=checkbox]' ).change( function( event ) {
	jQuery( this ).parent().next( '.weighting' ).find( 'input[type=range]' ).prop( 'disabled', ! this.checked ).val( 0 ).prev( 'label' ).find( '.weighting-value' ).text( '0' );
} );
