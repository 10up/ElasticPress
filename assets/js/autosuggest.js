/* eslint-disable camelcase */
import {
	findAncestor,
	escapeDoubleQuotes,
	replaceGlobally,
	debounce
} from './utils/helpers';
import 'element-closest';
import 'promise-polyfill/src/polyfill';
// import 'whatwg-fetch';
import { epas, fetch } from 'window';

/**
 * Submit the search form
 * @param object input
 */
function submitSearchForm( input ) {
	input.closest( 'form' ).submit();
}

/**
 * Take selected item and fill the search input
 * @param event
 */
function selectAutosuggestItem( input, text ) {
	input.value = text;
}

/**
 * Navigate to the selected item, and provides
 * event hook for JS customizations, like GA
 * @param event
 */
function goToAutosuggestItem( input, url ) {
	const detail = {
		searchTerm: input.value,
		url
	};

	triggerAutosuggestEvent( detail );
	window.location.href = url;
}


/**
 * Fires events when autosuggest results are clicked,
 * and if GA tracking is activated
 *
 * @param detail
 */
function triggerAutosuggestEvent( detail ) {
	const event = new CustomEvent( 'ep-autosuggest-click', { detail } );
	window.dispatchEvent( event );

	if( detail.searchTerm && 1 === parseInt( epas.triggerAnalytics ) && 'function' == typeof gtag ) {
		const action = `click - ${detail.searchTerm}`;
		// eslint-disable-next-line no-undef
		gtag( 'event', action, {
			'event_category' : 'EP :: Autosuggest',
			'event_label' : detail.url,
			'transport_type' : 'beacon',
		} );
	}
}

/**
 * Respond to an item selection based on the predefined behavior.
 * If epas.action is set to "navigate" (the default), redirects the browser to the URL of the selected item
 * If epas.action is set to any other value (such as "search"), fill in the value and perform the search
 *
 * @param input
 * @param element
 */
function selectItem( input, element ) {

	if ( 'navigate' === epas.action ) {
		return goToAutosuggestItem( input, element.dataset.url );
	}

	selectAutosuggestItem( input, element.innerText );
	submitSearchForm( input );
}


/**
 * Build the search query from the search text - the query is generated in PHP
 * and passed into the front end as window.epas = { "query...
 *
 * @returns json
 */
function getJsonQuery() {

	if( 'undefined' == typeof window.epas ) {
		const error = 'No epas object defined';

		// eslint-disable-next-line no-console
		console.warn( error );
		return { error };
	}

	return window.epas;
}


/**
 * Build the search query from the search text
 *
 * @param searchText - string
 * @param placeholder - string
 * @param { query } - desructured json query string
 * @returns json string
 */
function buildSearchQuery( searchText, placeholder, { query } ) {
	const newQuery = replaceGlobally( query, placeholder, searchText );
	return newQuery;
}


/**
 * Build the ajax request
 *
 * @param query - json string
 * @returns AJAX object request
 */
async function esSearch( query, searchTerm ) {

	const fetchConfig = {
		body: query,
		method: 'POST',
		mode: 'cors',
		headers: {
			'Content-Type': 'application/json; charset=utf-8',
		}
	};

	// only applies headers if using ep.io endpoint
	if( epas.addSearchTermHeader ) {
		fetchConfig.headers['EP-Search-Term'] = searchTerm;
	}

	try {
		const response = await window.fetch( epas.endpointUrl, fetchConfig );
		if ( !response.ok ) {
			throw Error( response.statusText );
		}

		let data = await response.json();

		// allow for filtered data before returning it to
		// be output on the front end
		if( 'undefined' !== typeof window.epDataFilter ) {
			data = window.epDataFilter( data, searchTerm );
		}

		return data;
	} catch( error ) {
		// eslint-disable-next-line no-console
		console.error( error );
		return error;
	}
}


/**
 * Update the auto suggest box with new options or hide if none
 *
 * @param options
 * @return void
 */
function updateAutosuggestBox( options, input ) {
	let i,
		itemString = '';

	// get the search term for use later on
	const { value } = input;
	const container = findAncestor( input, 'ep-autosuggest-container' );
	const resultsContainer = container.querySelector( '.ep-autosuggest' );
	const suggestList = resultsContainer.querySelector( '.autosuggest-list' );

	// empty the the list of all child nodes
	while( suggestList.firstChild )
		suggestList.removeChild( suggestList.firstChild );


	if ( 0 < options.length ) {
		resultsContainer.style = 'display: block;';
	} else {
		resultsContainer.style = 'display: none;';
	}

	// anticipating the future... a setting where we configure
	// a limit of results to show, and optionally append a
	// link to "all results" or something of that nature
	const resultsLimit = options.length;

	// create markup for list items
	for ( i = 0; resultsLimit > i; ++i ) {
		const { text, url } = options[i];
		const escapedText = escapeDoubleQuotes( text );

		// TODO: use some regex magic to match upper/lower/capital case??
		const highlightedText = escapedText.replace( value, `<span class="ep-autosuggest-highlight">${value}</span>` );
		itemString +=
			`<li class="autosuggest-list-item">
				<a href="${url}" class="autosuggest-item" data-search="${escapedText}" data-url="${url}">
					${highlightedText}
				</a>
			</li>`;
	}

	// append list items to the list
	suggestList.innerHTML = itemString;

	const autosuggestItems = Array.from( document.querySelectorAll( '.autosuggest-item' ) );
	autosuggestItems.forEach( item => {
		item.addEventListener( 'click', event => {
			selectItem( input, event.target );
		} );
	} );
}


/**
 * Hide the auto suggest box
 *
 * @return void
 */
function hideAutosuggestBox() {
	const lists = document.querySelectorAll( '.ep-autosuggest-list' );
	const containers = document.querySelectorAll( '.ep-autosuggest' );

	// empty all EP results lists
	[].forEach.call( lists, list => {
		while( list.firstChild )
			list.removeChild( list.firstChild );
	} );

	// hide all EP results containers
	[].forEach.call( containers, container => container.style = 'display: none;' );
}


/**
 * Checks for any manually ordered posts and puts them in the correct place
 *
 * @param hits
 * @param searchTerm
 */
function checkForOrderedPosts( hits, searchTerm ) {

	const toInsert = {};
	const taxName = 'ep_custom_result';
	searchTerm = searchTerm.toLowerCase();

	hits = hits.filter( ( hit ) => {
		// Should we retain this hit in its current position?
		let retain = true;

		if ( undefined !== hit._source.terms && undefined !== hit._source.terms[ taxName ] ) {
			hit._source.terms[ taxName ].map( currentTerm => {
				if ( currentTerm.name.toLowerCase() === searchTerm ) {
					toInsert[ currentTerm.term_order ] = hit;

					retain = false;
				}
			} );
		}

		return retain;
	} );

	const orderedInserts = {};

	Object.keys( toInsert ).sort().map( key => {
		orderedInserts[ key ] = toInsert[ key ];
	} );

	if ( 0 < Object.keys( orderedInserts ).length ) {
		Object.keys( orderedInserts ).map( key => {
			const insertItem = orderedInserts[ key ];

			hits.splice( key - 1, 0, insertItem );
		} );
	}

	return hits;
}

/**
 * init method called if the epas endpoint is defined
 */
function init() {
	const epInputNodes = document.querySelectorAll( `.ep-autosuggest, input[type="search"], .search-field, ${epas.selector}` );

	// build the container into which we place the search results.
	// These will be cloned later for each instance
	// of autosuggest inputs
	const epAutosuggest = document.createElement( 'div' );
	epAutosuggest.classList.add( 'ep-autosuggest' );
	const autosuggestList = document.createElement( 'ul' );
	autosuggestList.classList.add( 'autosuggest-list' );
	epAutosuggest.appendChild( autosuggestList );

	// Build the auto-suggest containers
	const epInputs = Array.from( epInputNodes );
	epInputs.forEach( input => {

		const epContainer = document.createElement( 'div' );
		epContainer.classList.add( 'ep-autosuggest-container' );

		// Disable autocomplete
		input.setAttribute( 'autocomplete', 'off' );

		// insert the container - later we will place
		// the input inside this container
		input.insertAdjacentElement( 'afterend', epContainer );

		// move the input inside the container
		const form = input.closest( 'form' );
		const container = form.querySelector( '.ep-autosuggest-container' );
		container.appendChild( input );

		const clonedContainer = epAutosuggest.cloneNode( true );
		input.insertAdjacentElement( 'afterend', clonedContainer );

		// announce that this is has been done
		const event = new CustomEvent( 'elasticpress.input.moved' );
		input.dispatchEvent( event );
	} );


	epAutosuggest.style = `
		top: ${epInputNodes[0].offsetHeight - 1};
		background-color: ${getComputedStyle( epInputNodes[0], 'background-color' )}
	`;


	/**
	 * Helper function to format search results for consumption
	 * by the updateAutosuggestBox function
	 *
	 * @param {hits} - object
	 */
	const formatSearchResults = hits => {
		const usedPosts = [];

		return hits.map( hit => {
			const text = hit._source.post_title;
			const url = hit._source.permalink;
			const postId = hit._source.post_id;

			if( ! usedPosts[ postId ] ) {
				usedPosts[ postId ] = true;
				return { text, url };
			}
		} );
	};


	// to be used by the handleUpdDown function
	// to keep track of the currently selected result
	let $currentIndex;

	/**
	 *
	 * @param {*} event
	 */
	const handleUpdDown = event => {
		const keyCodes = [
			38, // up
			40, // down
			13 // enter
		];

		if ( 27 === event.keyCode ) {
			hideAutosuggestBox();
			return;
		}

		if ( !keyCodes.includes( event.keyCode ) ) {
			return;
		}

		const input = event.target;
		const container = findAncestor( input, 'ep-autosuggest-container' );
		const suggestList = container.querySelector( '.autosuggest-list' );
		const results = suggestList.querySelectorAll( '.autosuggest-list-item' );

		/**
		 * helper function to get the currently selected result
		 */
		const getSelectedResultIndex = () => {
			return [].findIndex.call( results, result => result.classList.contains( 'selected' ) );
		};

		/**
		 * helper function to deselect results
		 */
		const deSelectResults = () => {
			[].forEach.call( results, result => result.classList.remove( 'selected' ) );
		};

		/**
		 * helper function to selected the next result
		 */
		const selectNextResult = () => {
			if( 0 <= $currentIndex ) {
				const el = results[$currentIndex];
				el.classList.add( 'selected' );
			}
		};

		// select next or previous based on keyCode
		// if enter, navigate to that element
		switch ( event.keyCode ) {
				case 38: // Up
					// don't go less than the 0th index
					$currentIndex = ( 0 <= $currentIndex - 1 ) ? $currentIndex - 1 : 0;
					deSelectResults();
					break;
				case 40: // Down
					if ( 'undefined' === typeof $currentIndex ) {
						// index is not yet defined, so let's
						// start with the first one
						$currentIndex = 0;
					} else {
						const $current = getSelectedResultIndex();

						// check for existence of next result
						if( results[$current + 1] ) {
							$currentIndex = $current + 1;
							deSelectResults();
						}
					}
					break;
				case 13: // Enter
					if ( results[$currentIndex].classList.contains( 'selected' ) ) {
						// navigate to the item defined in the span's data-url attribute
						selectItem( input, results[$currentIndex].querySelector( '.autosuggest-item' ) );
						return false;
					} else {
						// No item selected
						return;
					}
		}

		// only check next element if up and down key pressed
		if ( results[$currentIndex] && results[$currentIndex].classList.contains( 'autosuggest-list-item' ) ) {
			selectNextResult();
		} else {
			deSelectResults();
		}

		// keep cursor from heading back to the beginning in the input
		if ( 38 === event.keyCode ) {
			return false;
		}

		return;
	};


	/**
	 * Callback for keyup in Autosuggest container.
	 *
	 * Calls a debounced function to get the search results via
	 * ajax request.
	 *
	 * @param {*} event
	 */
	const handleKeyup = event => {
		event.preventDefault();

		const input = event.target;
		const keyCodes = [
			38, // up
			40, // down
			13, // enter
			27 // esc
		];

		if( keyCodes.includes( event.keyCode ) ) {
			handleUpdDown( event );
			return;
		}

		const debounceFetchResults = debounce( fetchResults, 200 );
		debounceFetchResults( input );
	};


	/**
	 * Calls the ajax request, and outputs the results.
	 * Called by the handleKeyup callback, debounced.
	 *
	 * @param {*} input
	 */
	const fetchResults = async input => {
		const searchText = input.value;
		const placeholder = 'ep_autosuggest_placeholder';

		// retrieves the PHP-genereated query to pass to ElasticSearch
		const queryJSON = getJsonQuery();

		if( queryJSON.error ) {
			return;
		}

		if ( 2 <= searchText.length ) {
			const query = buildSearchQuery( searchText, placeholder, queryJSON );

			// fetch the results
			const response = await esSearch( query, searchText );

			if ( response && 0 < response._shards.successful ) {
				const hits = checkForOrderedPosts( response.hits.hits, searchText );
				const formattedResults = formatSearchResults( hits );

				if ( 0 === formattedResults.length ) {
					hideAutosuggestBox();
				} else {
					updateAutosuggestBox( formattedResults, input );
				}
			} else {
				hideAutosuggestBox();
			}

		} else if ( 0 === searchText.length ) {
			hideAutosuggestBox();
		}
	};

	/**
	 * Listen for any events:
	 *
	 * keyup
	 * 		- send them for a query to the Elasticsearch server
	 * 		- handle up and down keys to move between results
	 * blur
	 * 		- hide the autosuggest box
	 */
	epInputs.forEach( input => {
		input.addEventListener( 'keyup', handleKeyup );
		input.addEventListener( 'blur', hideAutosuggestBox );
	} );

}

// Ensure we have an endpoint URL, or
// else this shouldn't happen
if ( epas.endpointUrl && '' !== epas.endpointUrl ) {

	init();

	// Publically expose API
	window.epasAPI = {
		hideAutosuggestBox: hideAutosuggestBox,
		updateAutosuggestBox: updateAutosuggestBox,
		esSearch: esSearch,
		buildSearchQuery: buildSearchQuery
	};
}
