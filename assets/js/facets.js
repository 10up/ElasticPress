import jQuery from 'jquery';

/**
 * Debounce execution
 */
const debounce = function( func, wait, immediate ) {
	let timeout;

	return function executedFunction() {
		const context = this;
		const args = arguments;

		/**
		 * Function to execute later
		 */
		const later = function() {
			timeout = null;

			if ( ! immediate ) {
				func.apply( context, args );
			}
		};

		const callNow = immediate && ! timeout;

		clearTimeout( timeout );

		timeout = setTimeout( later, wait );

		if ( callNow ) {
			func.apply( context, args );
		}
	};
};

const facetTerms = document.querySelectorAll( '.widget_ep-facet .terms' );

/**
 * Drill down facet choices
 */
jQuery( facetTerms ).on( 'keyup', '.facet-search', debounce( ( event ) => {
	if ( 13 === event.keyCode ) {
		return;
	}

	const searchTerm = event.currentTarget.value.replace( /\s/g, '' ).toLowerCase();
	const terms = event.delegateTarget.querySelectorAll( '.term' );

	terms.forEach( ( term ) => {
		const slug = term.getAttribute( 'data-term-slug' );
		const name = term.getAttribute( 'data-term-name' );

		if ( name.includes( searchTerm ) || slug.includes( searchTerm ) ) {
			term.classList.remove( 'hide' );
		} else {
			term.classList.add( 'hide' );
		}
	} );
}, 200 ) );
