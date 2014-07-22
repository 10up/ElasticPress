( function( $, undefined ) {

	'use strict';

	var $postTypeChooser = $( '#ep-post-type-chooser' );

	$postTypeChooser.find( '.post-types' ).each( function() {

		var $self = $( this );

		var url = $self.attr( 'data-ajax-url' );
		var site_id = $self.attr( 'data-site-id' );
		var selectedPostTypes = $self.attr( 'data-selected' ).split( ',' );

		$.ajax( {
			type: 'GET',
			url: url,
			success: function( data ) {
				var $postTypesHTML = $( '<div>' );

				$.each( data['post_types'], function( element ) {

					var checked = '';
					if ( $.inArray( element, selectedPostTypes ) !== -1 ) {
						checked = 'checked';
					}

					$postTypesHTML.append( '<p><label><input ' + checked + ' type="checkbox" name="ep_config[' + site_id + '][post_types][]" value="' + element + '"> ' + element + '</label></p>' );

				} );

				$self.append( $postTypesHTML );
			},
			dataType: 'json'
		} );

	} );

} )( jQuery );