import jQuery from 'jquery';
import { epfacets } from 'window';
import _ from 'underscores';

const facetTerms = document.querySelectorAll( '.widget_ep-facet .terms' );

/**
 * Drill down facet choices
 */
jQuery( facetTerms ).on( 'keyup', '.facet-search', _.debounce( ( event ) => {
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

/**
 * Ajaxify facets
 */
if( 1 === parseInt( epfacets.ajax_enabled ) && epfacets.selector && 0 < jQuery( epfacets.selector ).length ){
	jQuery( document ).on( 'click', '.term', function ( e ) {
		e.preventDefault();
		const relPath = jQuery( this ).find( 'a' ).attr( 'href' );
		if( relPath ){
			const baseUrl = window.location.origin;
			const targetUrl = baseUrl + relPath;

			jQuery.get( targetUrl, function ( data ) {
				const elem = jQuery( data ).find( epfacets.selector );
				jQuery( epfacets.selector ).replaceWith( elem );
			} );
		}
	} );
}
