( function( $ ) {
	var facetForm = document.querySelector( '.ep-facet-form' );
	var facetSearches = document.querySelectorAll( '.facet-search' );
	var facetTerms = facetForm.querySelectorAll( '.terms' );

	/**
	 * Parse query string in object
	 */
	function queryStringToObject() {
		var queryString = window.location.search;

		if ( ! queryString ) {
			return {};
		}

		var queryArray = queryString.replace( '?', '' ).split( '&' ),
			queryParams = [];

		for ( var i = 0; i < queryArray.length; i++ ) {
			var queryArg = queryArray[i].split( '=' );
			queryParams[ queryArg[0] ] = queryArg[1];
		}

		return queryParams;
	}

	/**
	 * Drill down facet choices
	 */
	$( facetTerms ).on( 'keyup', '.facet-search', _.debounce( function( event ) {
		if ( 13 === event.keyCode ) {
			return;
		}

		var searchTerm = event.currentTarget.value.replace(/\s/g, '');

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

	/**
	 * Reset all checkboxes on clear button press
	 */
	$( facetForm ).on( 'click', '.clear-facets', function( event ) {
		var checkboxes = event.delegateTarget.querySelectorAll( '.facet-input' );

		checkboxes.forEach( function( node ) {
			node.checked = false;
		} );
	} );

	/**
	 * We catch form submissions and tailor the GET vars to make them look nicer for the
	 * user.
	 */
	$( facetForm ).on( 'submit', function( event ) {
		event.preventDefault();

		var fields = $( this ).serializeArray(),
			preparedFacets = {};

		fields.forEach( function( fieldArray ) {
			if ( ! preparedFacets[ fieldArray.name ] ) {
				preparedFacets[ fieldArray.name ] = [];
			}

			preparedFacets[ fieldArray.name ].push( fieldArray.value );
		} );

		/**
		 * Take current query string and strip out existing facets
		 */
		var queryString = '?',
			queryArgs = queryStringToObject();

		for ( key in queryArgs ) {
			if ( '?' !== queryString ) {
				queryString += '&';
			}

			if ( ! key.match( /filter_taxonomy_/i ) ) {
				queryString += key + '=' + queryArgs[ key ];
			}
		}

		/**
		 * Append facets to query string
		 */
		for ( facet in preparedFacets ) {
			if ( '?' !== queryString ) {
				queryString += '&';
			}

			queryString += facet + '=' + preparedFacets[ facet ].join( ',' );
		}

		if ( '?' === queryString ) {
			queryString = '';
		}

		document.location = document.location.origin + document.location.pathname + queryString;
	} );
} )( jQuery );
