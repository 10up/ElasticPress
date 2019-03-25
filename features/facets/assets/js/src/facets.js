( function( $ ) {
	var facetTerms = document.querySelectorAll( '.widget_ep-facet .terms' );

	/**
	 * Drill down facet choices
	 */
	$( facetTerms ).on( 'keyup', '.facet-search', _.debounce( function( event ) {
		if ( 13 === event.keyCode ) {
			return;
		}

		var searchTerm = event.currentTarget.value.replace(/\s/g, '').toLowerCase();

		var terms = event.delegateTarget.querySelectorAll( '.term' );

		terms.forEach( function( term ) {
			var slug = term.getAttribute( 'data-term-slug' );
			var name = term.getAttribute( 'data-term-name' );

			if ( name.includes( searchTerm ) || slug.includes( searchTerm ) ) {
				term.classList.remove( 'hide' );
			} else {
				term.classList.add( 'hide' );
			}
		} );
	}, 200 ) );
} )( jQuery );
