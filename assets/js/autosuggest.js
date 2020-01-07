/* eslint-disable camelcase */
import jQuery from 'jquery';
import { epas } from 'window';

/**
 * Submit the search form
 * @param object $localInput
 */
function submitSearchForm( $localInput ) {
	$localInput.closest( 'form' ).submit();
}

/**
 * Take selected item and fill the search input
 * @param event
 */
function selectAutosuggestItem( $localInput, text ) {
	$localInput.val( text );
}

/**
 * Navigate to the selected item, and provides
 * event hook for JS customizations, like GA
 * @param event
 */
function goToAutosuggestItem( $localInput, url ) {

	const detail = {
		searchTerm: $localInput[0].value,
		url
	};

	triggerEvents( detail );

	window.location.href = url;
}


/**
 * Fires events when autosuggest results are clicked,
 * and if GA tracking is activated
 *
 * @param detail
 */
function triggerEvents( detail ) {
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
 * @param $localInput
 * @param element
 */
function selectItem( $localInput, element ) {

	if ( 'navigate' === epas.action ) {
		return goToAutosuggestItem( $localInput, element.dataset.url );
	}

	selectAutosuggestItem( $localInput, element.innerText );
	submitSearchForm( $localInput );
}

/**
 * Simple throttling function for waiting a set amount of time after the last keypress
 * So we don't overload the server with too many requests at once
 *
 * @param fn
 * @param delay
 * @returns {Function}
 */
function debounce( fn, delay ) {
	let timer = null;

	return () => {
		const context = this,
			args = arguments;

		window.clearTimeout( timer );

		timer = window.setTimeout( () => {
			fn.apply( context, args );
		}, delay );
	};
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
 * Helper function to escape input to be treated as a literal string with a RegEx
 *
 * @param string
 * @returns string
 */
function escapeRegExp( string ){
	return string.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
}


/**
 * Helper function to escape input to be treated as a literal string with a RegEx
 *
 * @param string - string
 * @param term - string
 * @param replacement - string
 * @returns string
 */
function replaceGlobally( string, term, replacement ) {
	return string.replace( new RegExp( escapeRegExp( term ), 'g' ), replacement );
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
function esSearch( query, searchTerm ) {

	// Fixes <=IE9 jQuery AJAX bug that prevents ajax request from firing
	jQuery.support.cors = true;

	const ajaxConfig = {
		url: epas.endpointUrl,
		type: 'post',
		dataType: 'json',
		crossDomain: true,
		contentType: 'application/json; charset=utf-8',
		data: query // no longer need to JSON.stringify
	};

	// only applies headers if using ep.io endpoint
	if( epas.addSearchTermHeader ) {
		ajaxConfig.headers = {
			'EP-Search-Term': searchTerm
		};
	}

	return jQuery.ajax( ajaxConfig );
}

/**
 * Escapes double quotes for specific data-attr
 *
 * @param str
 * @returns string The escaped string
 */
function escapeDoubleQuotes( str ) {
	return str.replace( /\\([\s\S])|(")/g, '&quot;' );
}

/**
 * Update the auto suggest box with new options or hide if none
 *
 * @param options
 * @return void
 */
function updateAutosuggestBox( options, $localInput ) {
	let i,
		itemString = '';

	const $localESContainer = $localInput.closest( '.ep-autosuggest-container' ).find( '.ep-autosuggest' ),
		$localSuggestList = $localESContainer.find( '.autosuggest-list' );

	$localSuggestList.empty();

	// Don't listen to potentially previously set items
	jQuery( '.autosuggest-item' ).off();

	if ( 0 < options.length ) {
		$localESContainer.show();
	} else {
		$localESContainer.hide();
	}

	for ( i = 0; i < options.length; ++i ) {
		const { text, url } = options[i];
		itemString += `<li><span class="autosuggest-item" data-search="${  escapeDoubleQuotes( text )  }" data-url="${  url  }">${  escapeDoubleQuotes( text )  }</span></li>`;
	}
	jQuery( itemString ).appendTo( $localSuggestList );

	// Listen to items to auto-fill search box and submit form
	jQuery( '.autosuggest-item' ).on( 'click', ( event ) => {
		selectItem( $localInput, event.target );
	} );

	$localInput.off( 'keydown' );

	// Listen to the input for up and down navigation between autosuggest items
	$localInput.on( 'keydown', ( event ) => {
		if ( 38 === event.keyCode || 40 === event.keyCode || 13 === event.keyCode ) {
			const $results = $localInput.closest( '.ep-autosuggest-container' ).find( '.autosuggest-list li' );
			const $current = $results.filter( '.selected' );
			let $next;

			switch ( event.keyCode ) {
					case 38: // Up
						$next = $current.prev();
						break;
					case 40: // Down
						if ( ! $results.hasClass( 'selected' ) ) {
							$results.first().addClass( 'selected' );
							$next = $results.first();
						} else {
							$next = $current.next();
						}
						break;
					case 13: // Enter
						if ( $results.hasClass( 'selected' ) ) {
							selectItem( $localInput, $current.children( 'span' ).get( 0 ) );
							return false;
						} else {
						// No item selected
							return;
						}
			}

			// only check next element if up and down key pressed
			if ( $next.is( 'li' ) ) {
				$current.removeClass( 'selected' );
				$next.addClass( 'selected' );
			} else {
				$results.removeClass( 'selected' );
			}

			// keep cursor from heading back to the beginning in the input
			if ( 38 === event.keyCode ) {
				return false;
			}

			return;
		}

	} );
}

/**
 * Hide the auto suggest box
 *
 * @return void
 */
function hideAutosuggestBox() {
	jQuery( '.autosuggest-list' ).empty();
	jQuery( '.ep-autosuggest' ).hide();
}

/**
 * Checks for any manually ordered posts and puts them in the correct place
 *
 * @param hits
 * @param searchTerm
 */
function checkForOrderedPosts( hits, searchTerm ) {
	const taxName = 'ep_custom_result';

	searchTerm = searchTerm.toLowerCase();

	const toInsert = {};

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

// No host/index set
if ( epas.endpointUrl && '' !== epas.endpointUrl ) {
	const $epInput       = jQuery( `.ep-autosuggest, input[type="search"], .search-field, ${  epas.selector}`  );
	const $epAutosuggest = jQuery( '<div class="ep-autosuggest"><ul class="autosuggest-list"></ul></div>' );

	/**
	 * Build the auto-suggest container
	 */
	$epInput.each( ( key, input ) => {
		const $epContainer = jQuery( '<div class="ep-autosuggest-container"></div>' );
		const $input = jQuery( input );

		// Disable autocomplete
		$input.attr( 'autocomplete', 'off' );

		$epContainer.insertAfter( $input );
		const $epLabel = $input.siblings( 'label' );
		$input
			.closest( 'form' )
			.find( '.ep-autosuggest-container' )
			.append( $epLabel )
			.append( $input );

		$epAutosuggest.clone().insertAfter( $input );

		$input.trigger( 'elasticpress.input.moved' );
	} );

	$epAutosuggest.css( {
		top: $epInput.outerHeight() - 1,
		'background-color': $epInput.css( 'background-color' )
	} );

	/**
	 * Singular bindings for up and down to prevent normal actions so we can use them to navigate
	 * our autosuggest list
	 * Listen to the escape key to close the autosuggest box
	 */
	jQuery( $epInput ).each( ( key, value ) => {
		jQuery( value ).on( 'keyup keydown keypress', ( event ) => {
			if ( 38 === event.keyCode || 40 === event.keyCode ) {
				event.preventDefault();
			}
			if ( 27 === event.keyCode ) {
				hideAutosuggestBox();
			}
		} );
	} );

	/**
	 * Listen for any keyup events, throttle them to a min threshold of time
	 * and then send them for a query to the Elasticsearch server
	 *
	 */
	$epInput.each( ( key, localInput ) => {
		const $localInput = jQuery( localInput );
		$localInput.on( 'keyup', debounce( ( event ) => {
			if ( 38 === event.keyCode || 40 === event.keyCode || 13 === event.keyCode || 27 === event.keyCode ) {
				return;
			}

			const searchText = $localInput.val();
			const placeholder = 'ep_autosuggest_placeholder';
			const queryJSON = getJsonQuery();

			if( queryJSON.error ) {
				return;
			}

			let query;
			let request;
			const postTypes = epas.postTypes;
			const postStatus = epas.postStatus;
			const searchFields = epas.searchFields;
			const weightingSettings = Object.assign( {}, epas.weightingDefaults, epas.weighting );

			if ( 2 <= searchText.length ) {
				query = buildSearchQuery( searchText, placeholder, queryJSON );
				request = esSearch( query, searchText );

				request.done( ( response ) => {
					if ( 0 < response._shards.successful ) {
						const usedPosts = {};
						const filteredObjects = [];

						const hits = checkForOrderedPosts( response.hits.hits, searchText );

						jQuery.each( hits, ( index, element ) => {
							const text = element._source.post_title;
							const url = element._source.permalink;
							const postId = element._source.post_id;

							if( ! usedPosts[ postId ] ) {
								usedPosts[ postId ] = true;

								filteredObjects.push( {
									'text': text,
									'url': url
								} );
							}
						} );

						if ( 0 === filteredObjects.length ) {
							hideAutosuggestBox();
						} else {
							updateAutosuggestBox( filteredObjects, $localInput );
						}
					} else {
						hideAutosuggestBox();
					}
				} );
			} else if ( 0 === searchText.length ) {
				hideAutosuggestBox();
			}
		}, 200 ) );
	} );

	// Publically expose API
	window.epasAPI = {
		hideAutosuggestBox: hideAutosuggestBox,
		updateAutosuggestBox: updateAutosuggestBox,
		esSearch: esSearch,
		buildSearchQuery: buildSearchQuery
	};
}
