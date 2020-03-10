import jQuery from 'jquery';
import { epfacets } from 'window';
// import _ from 'underscores';
import { debounce } from './utils/debounce';

// const facetTerms = document.querySelectorAll( '.widget_ep-facet .terms' );
const templateItem = document.getElementById( 'ep-facet-sample-result' ).innerHTML;
const loadMoreButton = `
	<button id="ep-load-more">
		Load More
	</button>`;
let currentQuery;
/**
 * keyup event callback - Drill down facet choices
 *
 * @param {*} event
 */
const handleTermsKeyup = event => {
	// enter
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
};

document.addEventListener( 'keyup', event => {
	if( event.target.matches( '.facet-search' ) ) {
		debounce( handleTermsKeyup, 200 );
	}
} );


/**
 * helper function
 *
 * @param {node} parentEl
 */
const getCheckboxChild = parentEl => {
	return parentEl.querySelector( 'input[type=checkbox]' );
};


/**
 * Facet click callback function
 *
 * @param {node} termEl
 */
const handleFacetCheckStatus = termEl => {
	const selectedClass = 'selected';

	const checkBox = getCheckboxChild( termEl );

	if( termEl.classList.contains( selectedClass ) ) {
		// uncheck this item
		termEl.classList.remove( selectedClass );
		checkBox.checked = false;
	} else {
		termEl.classList.add( selectedClass );
		checkBox.checked = true;
	}
};

/**
 *  build facet query
 */
const buildFacetQuery = () => {
	const facetQuery = getFacetQuery();
	const esQuery = epfacets.query;

	// apply conditions to filter query
	if ( esQuery.post_filter.bool.must ) {
		// esQuery.post_filter.bool.must = jQuery.extend( esQuery.post_filter.bool.must, [facetQuery] );
		esQuery.post_filter.bool.must = Object.assign( esQuery.post_filter.bool.must, [facetQuery] );
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
		// esQuery.aggs.terms.filter.bool.must = jQuery.extend( esQuery.aggs.terms.filter.bool.must, [facetQuery] );
		esQuery.aggs.terms.filter.bool.must = Object.assign( esQuery.aggs.terms.filter.bool.must, [facetQuery] );
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

	return esQuery;
};

/**
 * Helper function to check for more posts
 *
 * @param {*} startIndex
 * @param {*} totalResults
 * @param {*} size
 * @returns {boolean}
 */
const checkMorePosts = ( startIndex, totalResults, size ) => {
	return totalResults - size > startIndex ? true : false;
};


/**
 * Fact click callback function
 *
 * @param {*} event
 */
const handleFacetClick = async ( termEl ) => {

	if( termEl ) {
		handleFacetCheckStatus( termEl );
		// reset this, for any new clicks,
		// else the check below will retain
		// the current query
		if( currentQuery ) {
			currentQuery.from = 0;
		}
	}

	if( ! templateItem ) {
		return;
	}

	const newQuery = buildFacetQuery();
	// for deep object comparison:
	currentQuery = JSON.stringify( newQuery ) === JSON.stringify( currentQuery ) ? currentQuery : newQuery;
	const response = await esSearch( currentQuery );
	const morePosts = checkMorePosts( currentQuery.from, response.hits.total, currentQuery.size );

	if ( response && ( 0 < response._shards.successful ) ) {
		const htmlResults = response.hits.hits.map( element => {
			const {
				post_title,
				post_content,
				post_content_filtered,
				post_excerpt,
				post_date,
				permalink,
				terms,
				post_id
			} = element._source;

			const categories = terms.category && terms.category.map( cat => cat.name ).join( ', ' );
			const tags = terms.post_tag && terms.post_tag.map( tag => tag.name ).join( ', ' );

			const date = new Date( post_date );
			const dateString = `${date.toLocaleDateString( 'default', {
				month: 'long',
				weekday: 'long',
				year: 'numeric',
				day: 'numeric' } )
			}`;

			return templateItem.replace( /{{POST_TITLE}}/g, post_title )
				.replace( /{{POST_EXCERPT}}/g, post_excerpt )
				.replace( /{{POST_CONTENT}}/g, post_content )
				.replace( /{{POST_CONTENT_FILTERED}}/g, post_content_filtered )
				.replace( /{{PERMALINK}}/g, permalink )
				.replace( /{{DATE}}/g, dateString )
				.replace( /{{CATEGORIES}}/g, categories )
				.replace( /{{TAGS}}/g, tags )
				.replace( /http:\/\/PERMALINK/g, permalink )
				.replace( /-99999999999/g, post_id );
		} ).join( '' );

		render( {
			htmlResults,
			currentQuery,
			morePosts
		} );
	}
};


/**
 * Render function to display ajax results
 *
 * @param {*} params
 */
const render = ( { htmlResults, currentQuery, morePosts } ) => {
	const results = document.querySelector( epfacets.selector );

	if( 0 == currentQuery.from ) {
		results.innerHTML = htmlResults;

		if( morePosts ) {
			console.log( 'adding button!' );
			results.innerHTML += loadMoreButton;

			// bump the starting point of the query;
			currentQuery.from = currentQuery.from + currentQuery.size;
		}
	} else {
		const loadMoreButton = document.getElementById( 'ep-load-more' );
		loadMoreButton.insertAdjacentHTML( 'beforebegin', htmlResults );

		if( !morePosts ) {
			loadMoreButton.remove();
			currentQuery = null;
		} else {
			// bump the starting point of the query;
			currentQuery.from = currentQuery.from + currentQuery.size;
		}
	}
};

/**
 * Ajaxify facets
 */
if( 1 === parseInt( epfacets.ajax_enabled ) && epfacets.selector && 0 < jQuery( epfacets.selector ).length ){

	document.addEventListener( 'click', ( event ) => {
		const termEl = event.target.closest( '.terms .term' );
		if( termEl ) {
			event.preventDefault();
			handleFacetClick( termEl );
		}

		if( 'ep-load-more' == event.target.id ) {
			console.log( 'load more posts' );
			handleFacetClick();
		}
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
		const slug = term.dataset.termSlug;
		const taxonomy = term.dataset.termTaxonomy;

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
const esSearch = async ( query ) => {

	const fetchConfig = {
		body: JSON.stringify( query ),
		method: 'POST',
		mode: 'cors',
		headers: {
			'Content-Type': 'application/json; charset=utf-8',
		}
	};

	// do we need some sort of header to allow CORS for _search endpoint?
	// if( epfacets.addFacetsHeader ) {
	// 	ajaxConfig.headers = {
	// 		'EP-Facets': 'something'
	// 	};
	// }

	try {
		const response = await window.fetch( epfacets.endpointUrl, fetchConfig );
		if ( !response.ok ) {
			throw Error( response.statusText );
		}

		return await response.json();
	} catch( error ) {
		// eslint-disable-next-line no-console
		console.error( error );
		return error;
	}
};
