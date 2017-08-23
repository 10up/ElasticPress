/**
 * ElasticPress Autosuggest
 * http://github.com/10up/ElasticPress-Autosuggest
 *
 * Copyright (c) 2014 Aaron Holbrook, 10up
 * Licensed under the GPLv2+ license.
 */
( function( $ ) {
	'use strict';

	// No host/index set
	if ( ! epas.host || '' === epas.host || ! epas.index || '' === epas.index ) {
		return;
	}

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
	 * If epas.action is set to "navigate", redirects the browser to the URL of the selected item
	 * If epas.action is set to any other value (default "search"), fill in the value and perform the search
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
		var timer = null;

		return function() {
			var context = this,
				args = arguments;

			window.clearTimeout(timer);

			timer = window.setTimeout( function () {
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
	function buildSearchQuery( searchText, postType ) {
		if ( postType === 'all' || typeof( postType ) === 'undefined' || postType === '' ) {
			postType = 'all';
		}
		// TODO: check comma separated
		var query = {
			suggest: {
				'post-suggest': {
					text: searchText,
					completion: {
						field: 'post_title.completion'
					}
				},
				'term-suggest': {
					text: searchText,
					completion: {
						field: 'term_suggest'
					}
				}
			}
		};

		return query;
	}

	/**
	 * Build the ajax request
	 *
	 * @param query
	 * @returns AJAX object request
	 */
	function esSearch( query ) {
		// @todo support multiple different post type search boxes on the same page
		var url = epas.host + '/' + epas.index + '/post/_search';

		// Fixes <=IE9 jQuery AJAX bug that prevents ajax request from firing
		jQuery.support.cors = true;

		return $.ajax( {
			url: url,
			type: 'post',
			dataType: 'json',
			crossDomain: true,
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
		$( '.autosuggest-item' ).off();

		if ( options.length > 0 ) {
			$localESContainer.show();
		} else {
			$localESContainer.hide();
		}

		for ( i = 0; i < options.length; ++i ) {
			var text = options[i].text.toLowerCase();
			var url = options[i].url;
			itemString = '<li><span class="autosuggest-item" data-search="' + text + '" data-url="' + url + '">' + text + '</span></li>';
			$( itemString ).appendTo( $localSuggestList );
		}

		// Listen to items to auto-fill search box and submit form
		$( '.autosuggest-item' ).on( 'click', function( event ) {
			selectItem( $localInput, event.srcElement );
		} );

		$localInput.off( 'keydown' );

		// Listen to the input for up and down navigation between autosuggest items
		$localInput.on( 'keydown', function( event ) {
			if ( event.keyCode === 38 || event.keyCode === 40 || event.keyCode === 13 ) {
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
							selectItem( $localInput, $current.get(1) );
							return false;
						} else {
							// No item selected
							return;
						}
						break;
				}

				// only check next element if up and down key pressed
				if ( $next.is( 'li' ) ) {
					$current.removeClass( 'selected' );
					$next.addClass( 'selected' );
				} else {
					$results.removeClass( 'selected' );
				}

				// keep cursor from heading back to the beginning in the input
				if( event.keyCode === 38 ) {
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
		$( '.autosuggest-list' ).empty();
		$( '.ep-autosuggest' ).hide();
	}

	var $epInput       = $( 'input.elasticpress-autosuggest, input[type="search"]' );
	var $epAutosuggest = $( '<div class="ep-autosuggest"><ul class="autosuggest-list"></ul></div>' );

	/**
	 * Build the auto-suggest container
	 */
	$epInput.each( function( key, input ) {
		var $epContainer = $( '<div class="ep-autosuggest-container"></div>' );
		var $input = $( input );

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
	$( $epInput ).each( function( key, value ) {
		$( value ).on( 'keyup keydown keypress', function( event ) {
			if ( event.keyCode === 38 || event.keyCode === 40) {
				event.preventDefault();
			}
			if ( event.keyCode === 27 ) {
				hideAutosuggestBox();
			}
		} );
	} );

	/**
	 * Listen for any keyup events, throttle them to a min threshold of time
	 * and then send them for a query to the Elasticsearch server
	 *
	 */
	$epInput.each( function( key, localInput ) {
		var $localInput = $( localInput );
		$localInput.on( 'keyup', debounce( function( event ) {
			if ( event.keyCode === 38 || event.keyCode === 40 || event.keyCode === 13 || event.keyCode === 27 ) {
				return;
			}

			var val = $localInput.val();
			var query;
			var request;
			var postType = epas.postType;

			if ( val.length >= 2 ) {
				query = buildSearchQuery( val, postType );
				request = esSearch( query );

				request.done( function( response ) {
					if ( response._shards.successful > 0 ) {
						var titleOptions = response.suggest['post-suggest'][0]['options'];
						var termOptions = response.suggest['term-suggest'][0]['options'];

						var options = titleOptions.concat( termOptions );
						var filteredOptions = [];
						var filteredObjects = [];

						$.each( options, function( index, element ){
							if( $.inArray( element.text, filteredOptions ) === -1 ) {
								filteredOptions.push( element.text );
								filteredObjects.push( {
									'text': element.text,
									'url': element._source.permalink
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
		}, 100 ) );
	} );

	// Publically expose API
	window.epasAPI = {
		hideAutosuggestBox: hideAutosuggestBox,
		updateAutosuggestBox: updateAutosuggestBox,
		esSearch: esSearch,
		buildSearchQuery: buildSearchQuery
	};

} )( jQuery );
