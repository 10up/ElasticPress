( function( $, undefined ) {

	'use strict';

	var NetworkSettings = ( function() {

		var _instance = null;

		function networkSettingsSetup() {

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

							$postTypesHTML.append( '<p><input ' + checked + ' type="checkbox" name="ep_config[' + site_id + '][post_types][]" value="' + element + '"> ' + element + '</p>' );

						} );

						$self.append( $postTypesHTML );
					},
					dataType: 'json'
				} );

			} );
		}

		function getInstance() {
			if ( _instance == null ) {
				_instance = new networkSettingsSetup();
			}

			return _instance;
		}

		return {
			getInstance: getInstance
		}
	} )();

	NetworkSettings.getInstance();

} )( jQuery );