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

		if ( jQuery( this ).hasClass( 'selected' ) ) {
			jQuery( this ).removeClass( 'selected' );
			jQuery( this ).find( 'input:checkbox' ).attr( 'checked',false );
		} else {
			jQuery( this ).addClass( 'selected' );
			jQuery( this ).find( 'input:checkbox' ).attr( 'checked',true );
		}

		const templateItem = jQuery( '#ep-facet-sample-result' ).html();
		const facetQuery = getFacetQuery();
		const esQuery = epfacets.query;

		if ( !templateItem ) {
			return;
		}

		// apply conditions to filter query
		if ( esQuery.post_filter.bool.must ) {
			esQuery.post_filter.bool.must = jQuery.extend( esQuery.post_filter.bool.must, [facetQuery] );
		} else {
			esQuery.post_filter = {
				bool: {
					must:[
						facetQuery
					]
				}
			};
		}

		// apply same conditions to aggs query
		if( esQuery.aggs.terms.filter.bool.must ){
			esQuery.aggs.terms.filter.bool.must = jQuery.extend( esQuery.aggs.terms.filter.bool.must, [facetQuery] );
		}else{
			esQuery.aggs = {
				terms:{
					filter:{
						bool:{
							must:[
								facetQuery
							]
						}
					}
				}
			};
		}

		const request = esSearch( esQuery );

		request.done( ( response ) => {
			if ( 0 < response._shards.successful ) {
				let htmlResults = '';

				jQuery.each( response.hits.hits, ( index, element ) => {
					const post_title = element._source.post_title;
					const post_content = element._source.post_content;
					const post_content_filtered = element._source.post_content_filtered;
					const post_excerpt = element._source.post_excerpt;
					const permalink = element._source.permalink;
					const postId = element._source.post_id;

					htmlResults += templateItem.replace( /{{POST_TITLE}}/g, post_title )
						.replace( /{{POST_EXCERPT}}/g, post_excerpt )
						.replace( /{{POST_CONTENT}}/g, post_content )
						.replace( /{{POST_CONTENT_FILTERED}}/g, post_content_filtered )
						.replace( /{{PERMALINK}}/g, permalink )
						.replace( /-99999999999/g, postId )
					;
				} );
				jQuery( epfacets.selector ).html( htmlResults );
			}
		} );

	} );

}

/**
 *
 * @returns {[]}
 */
function getFacetQuery() {
	const terms = document.querySelectorAll( '.term.selected' );
	const filter = {
		bool:{
			must:[],
			should:[],
		}
	};

	terms.forEach( ( term ) => {
		const slug = term.getAttribute( 'data-term-slug' );
		const taxonomy = term.getAttribute( 'data-term-taxonomy' );

		const termQuery = { terms:{ [`terms.${taxonomy}.slug`]:[slug] }};
		filter.bool.must.push( termQuery );
		filter.bool.should.push( termQuery );
	} );

	if( 'all' === epfacets.match_type ){
		delete filter.bool.should;
	}else {
		delete filter.bool.must;
	}

	return filter;
}


/**
 * Build the ajax request
 *
 * @param query - json string
 * @returns AJAX object request
 */
function esSearch( query ) {

	// Fixes <=IE9 jQuery AJAX bug that prevents ajax request from firing
	jQuery.support.cors = true;

	const ajaxConfig = {
		url: epfacets.endpointUrl,
		type: 'post',
		dataType: 'json',
		crossDomain: true,
		contentType: 'application/json; charset=utf-8',
		data:JSON.stringify( query )
	};

	return jQuery.ajax( ajaxConfig );
}
