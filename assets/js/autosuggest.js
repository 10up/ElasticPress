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
 * Navigate to the selected item
 * @param event
 */
function goToAutosuggestItem( $localInput, url ) {
	return window.location.href = url;
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
 * Build the search query from the search text
 *
 * @param searchText
 * @returns object
 */
function buildSearchQuery( searchText, postType, postStatus, searchFields ) {
	if ( 'all' === postType || 'undefined' === typeof( postType ) || '' === postType ) {
		postType = 'all';
	}

	if ( '' === postStatus ) {
		postStatus = 'publish';
	}

	var query = {
		sort: [
			{
				_score: {
					order: 'desc'
				}
			}
		],
		query: {
			multi_match: {
				query: searchText,
				fields: searchFields
			}
		}
	};

	// If we're specifying post types/statuses, do it in an array
	if ( 'string' === typeof postType && 'all' !== postType ) {
		postType = postType.split( ',' );
	}

	if ( 'string' === typeof postStatus ) {
		postStatus = postStatus.split( ',' );
	}

	// Then add it as a filter to the end of the query
	query.post_filter = {
		bool: {
			must: [
				{
					terms: { post_status: postStatus }
				}
			]
		}
	};

	if ( 'all' !== postType ) {
		query.post_filter.bool.must.push( {
			terms: { 'post_type.raw': postType }
		} );
	}

	return query;
}

/**
 * Build the ajax request
 *
 * @param query
 * @returns AJAX object request
 */
function esSearch( query ) {

	// Fixes <=IE9 jQuery AJAX bug that prevents ajax request from firing
	jQuery.support.cors = true;

	return jQuery.ajax( {
		url: epas.endpointUrl,
		type: 'post',
		dataType: 'json',
		crossDomain: true,
		contentType: 'application/json; charset=utf-8',
		data: JSON.stringify( query )
	} );
}

/**
 * Update the auto suggest box with new options or hide if none
 *
 * @param options
 * @return void
 */
function updateAutosuggestBox( options, $localInput ) {
	var i,
		itemString,
		$localESContainer = $localInput.closest( '.ep-autosuggest-container' ).find( '.ep-autosuggest' ),
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
		var text = options[i].text;
		var url = options[i].url;
		itemString = '<li><span class="autosuggest-item" data-search="' + text + '" data-url="' + url + '">' + text + '</span></li>';
		jQuery( itemString ).appendTo( $localSuggestList );
	}

	// Listen to items to auto-fill search box and submit form
	jQuery( '.autosuggest-item' ).on( 'click', ( event ) => {
		selectItem( $localInput, event.target );
	} );

	$localInput.off( 'keydown' );

	// Listen to the input for up and down navigation between autosuggest items
	$localInput.on( 'keydown', ( event ) => {
		if ( 38 === event.keyCode || 40 === event.keyCode || 13 === event.keyCode ) {
			var $results = $localInput.closest( '.ep-autosuggest-container' ).find( '.autosuggest-list li' );
			var $current = $results.filter( '.selected' );
			var $next;

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


// No host/index set
if ( epas.endpointUrl && '' !== epas.endpointUrl ) {
	const $epInput       = jQuery( '.ep-autosuggest, input[type="search"], .search-field, ' + epas.selector  );
	const $epAutosuggest = jQuery( '<div class="ep-autosuggest"><ul class="autosuggest-list"></ul></div>' );

	/**
	 * Build the auto-suggest container
	 */
	$epInput.each( ( key, input ) => {
		var $epContainer = jQuery( '<div class="ep-autosuggest-container"></div>' );
		var $input = jQuery( input );

		// Disable autocomplete
		$input.attr( 'autocomplete', 'off' );

		$epContainer.insertAfter( $input );
		var $epLabel = $input.siblings( 'label' );
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
		var $localInput = jQuery( localInput );
		$localInput.on( 'keyup', debounce( ( event ) => {
			if ( 38 === event.keyCode || 40 === event.keyCode || 13 === event.keyCode || 27 === event.keyCode ) {
				return;
			}

			var val = $localInput.val();
			var query;
			var request;
			var postType = epas.postType;
			var postStatus = epas.postStatus;
			var searchFields = epas.searchFields;

			if ( 2 <= val.length ) {
				query = buildSearchQuery( val, postType, postStatus, searchFields );
				request = esSearch( query );

				request.done( ( response ) => {
					if ( 0 < response._shards.successful ) {
						var usedPosts = {};
						var filteredObjects = [];

						jQuery.each( response.hits.hits, ( index, element ) =>{
							var text = element._source.post_title;
							var url = element._source.permalink;
							var postId = element._source.post_id;

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
			} else if ( 0 === val.length ) {
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
