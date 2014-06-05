( function( $, ES_Settings, undefined ) {

	'use strict';

	var NetworkSettings = ( function() {

		var _instance = null;

		function networkSettingsSetup() {

			var $postTypeChooser = $( '#es-post-type-chooser' );

			$postTypeChooser.find( '.post-types' ).each( function() {

				var $self = $( this );

				var url = $self.attr( 'data-ajax-url' );
				var site_id = $self.attr( 'data-site-id' );
				var selectedPostTypes = $self.attr( 'data-selected' ).split( ',' );

				$.ajax( {
					type: 'POST',
					url: url,
					data: {
						action: 'get_post_types',
						nonce: ES_Settings.post_types_nonce
					},
					success: function( data ) {
						var $postTypesHTML = $( '<div>' );

						$.each( data['post_types'], function( element ) {

							var checked = '';
							if ( $.inArray( element, selectedPostTypes ) !== -1 ) {
								checked = 'checked';
							}

							$postTypesHTML.append( '<p><input ' + checked + ' type="checkbox" name="es_config[' + site_id + '][post_types][]" value="' + element + '"> ' + element + '</p>' );

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

} )( jQuery, ES_Settings );