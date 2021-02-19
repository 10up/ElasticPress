import { epgl } from 'window';

/**
 * Append the hidden inputs so we can submit lat long with the search form
 */
function appendHiddenInputs( savedLocation = [] ) {
	const buttonHolders = document.querySelectorAll( epgl.selector );
	const latInput = document.createElement( 'input' );
	const longInput = document.createElement( 'input' );

	latInput.setAttribute( 'type', 'hidden' );
	latInput.setAttribute( 'name', 'epgl_latitude' );
	longInput.setAttribute( 'type', 'hidden' );
	longInput.setAttribute( 'name', 'epgl_longitude' );

	if ( savedLocation ) {
		latInput.value = savedLocation[0];
		longInput.value = savedLocation[1];
	}

	buttonHolders.forEach( buttonHolder => {
		buttonHolder.append( latInput );
		buttonHolder.append( longInput );
	} );
}

/**
 * Appends a simple message that the location is set
 */
function appendLocationSetMessage() {
	const buttonHolders  = document.querySelectorAll( epgl.selector );
	const locatedWrapper = document.createElement( 'div' );
	const message        = document.createElement( 'span' );
	const removeButton   = document.createElement( 'button' );

	locatedWrapper.classList.add( 'ep-located' );

	message.innerText = epgl.locationSetMessage;

	removeButton.innerText = epgl.removeButtonText;
	removeButton.addEventListener( 'click', clearCurrentLocation );

	locatedWrapper.append( message );
	locatedWrapper.append( removeButton );

	buttonHolders.forEach( buttonHolder => {
		buttonHolder.append( locatedWrapper );
	} );
}

/**
 * Remove the location set messaging
 */
function removeLocationSetMessage() {
	const messageWrappers = document.getElementsByClassName( 'ep-located' );
	messageWrappers.forEach( messageWrapper => {
		messageWrapper.querySelector( 'button' ).removeEventListener( 'click', clearCurrentLocation );
		messageWrapper.remove();
	} );
}

/**
 * Appends the "Location Me" button to the specified selector
 */
function appendLocationButton() {
	const locationButton = document.createElement( 'button' );
	const buttonHolders = document.querySelectorAll( epgl.selector );

	locationButton.classList.add( 'ep-locate-me' );
	locationButton.innerText = epgl.buttonText;
	locationButton.addEventListener( 'click', setUserLocation );

	buttonHolders.forEach( buttonHolder => {
		buttonHolder.append( locationButton );
	} );
}

/**
 * Remove Locate me button
 */
function removeLocationButton() {
	const locationButtons = document.getElementsByClassName( 'ep-locate-me' );
	locationButtons.forEach( locationButton => {
		locationButton.removeEventListener( 'click', setUserLocation );
		locationButton.remove();
	} );
}

/**
 * Clears the current location lat long information
 */
function clearCurrentLocation( event ) {
	event.preventDefault();

	clearLocationCookie();
	updateInputs( '', '' );
	removeLocationSetMessage();
	appendLocationButton();
}

/**
 * Get the user's location and save it in a cookie
 */
function setUserLocation( event ) {
	event.preventDefault();

	if ( navigator.geolocation ) {
		navigator.geolocation.getCurrentPosition( function ( position ) {
			const lat = position.coords.latitude;
			const long = position.coords.longitude;

			setLocationCookie( lat, long );
			updateInputs( lat, long );
			removeLocationButton();
			appendLocationSetMessage();
		} );
	} else {
		epgl.selector.innerHTML = epgl.getLocationErrorMessage;
	}
}

/**
 * Get location Cookie
 */
function getLocationFromCookie() {
	// Split cookie string and get all individual name=value pairs in an array
	const cookieArr = document.cookie.split( ';' );
	let coords =[];

	// Loop through the array elements
	for ( let i = 0; i < cookieArr.length; i++ ) {
		const cookiePair = cookieArr[i].split( '=' );

		if ( 'epgl' == cookiePair[0].trim() ) {
			coords = cookiePair[1].split( ',' );
			if ( 2 === coords.length ) {
				return [ coords[0], coords[1] ]; // lat, long
			}
		}
	}
	return null;
}

/**
 * Clear location Cookie
 */
function clearLocationCookie() {
	document.cookie = 'epgl=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
}

/**
 * Set location Cookie
 */
function setLocationCookie( lat, long ) {
	document.cookie = `epgl=${lat},${long}; expires=Tue, January 1, 2030 12:00:00 UTC`;
}

/**
 * Updates the hidden inputs with the set coordinates
 */
function updateInputs( lat, long ) {
	const latInputs = document.querySelectorAll( 'input[name="epgl_latitude"' );
	const longInputs = document.querySelectorAll( 'input[name="epgl_longitude"' );

	latInputs.forEach( latInput => {
		latInput.value = lat;
	} );

	longInputs.forEach( longInput => {
		longInput.value = long;
	} );
}

if ( epgl.selector && '' !== epgl.selector && null !== document.querySelector( epgl.selector ) ) {
	const savedLocation = getLocationFromCookie();

	if ( savedLocation ) {
		appendLocationSetMessage();
	} else {
		appendLocationButton();
	}

	appendHiddenInputs( savedLocation );
}
