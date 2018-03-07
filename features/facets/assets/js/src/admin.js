( function( $ ) {

	$( 'body' ).on( 'click', '.add-facet', function( event ) {
		var $container = $( this ).parents( '.widget-inside' );
		var $facets = $container.find( '.facets' );

		var newFacet = wp.template( 'ep-facets-widget-facet' );

		$facets.append( newFacet( { fieldId: $facets.length + 1, fieldName: $facets.data( 'field-name' ) } ) );
	} );

	$( 'body' ).on( 'click', '.delete-facet', function() {
		$( this ).parents( 'p' ).remove();
	} );

} )( jQuery );
