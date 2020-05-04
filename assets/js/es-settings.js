import { showElements, hideElements } from './utils/helpers';

// const $epCredentialsTab = jQuery( document.getElementsByClassName( 'ep-credentials-tab' ) );
const epCredentialsTabs = document.querySelectorAll( '.ep-credentials-tab' );
// const $epCredentialsHostLabel = jQuery( '.ep-host-row label' );
const epCredentialsHostLabel = document.querySelector( '.ep-host-row label' );
// const $epCredentialsHostLegend = jQuery( document.getElementsByClassName( 'ep-host-legend' ) );
const epCredentialsHostLegend = document.querySelector( '.ep-host-legend' );
// const $epCredentialsAdditionalFields = jQuery(
// 	document.getElementsByClassName( 'ep-additional-fields' ),
// );
const epCredentialsAdditionalFields = document.querySelectorAll( '.ep-additional-fields' );

const epHostField = document.getElementById( 'ep_host' );
const epHost = epHostField ? epHostField.value : null;
const epHostNewValue = '';


export const initSettingsPage = () => {
	if( ! epHostField ) {
		return;
	}

	// appears only if nothing configured in wp-config.php
	epCredentialsTabs && epCredentialsTabs.forEach( tab => tab.addEventListener( 'click', toggleActiveTab ) );
	epHostField && epHostField.addEventListener( 'input', handleInputChange );
}


/**
 * Updates the value of the epHostNewValue variable
 * for later reuse when tabs are toggled
 *
 * @param {event} event - input event
 */
const handleInputChange = event => {
	epHostNewValue = event.target.value;
}



/**
 *
 * @param {event} event - click event
 */
const toggleActiveTab = e => {
	e.preventDefault();

	const { currentTarget } = e;

	// check for EP.io
	const isEpioDefined = null !== currentTarget.getAttribute( 'data-epio' );
	const isInitial = currentTarget.classList.contains( 'initial' );

	if ( isInitial && !epHostField.disabled ) {
		epHostField.value = epHost;
	} else {
		epHostField.value = epHostNewValue;
	}

	epCredentialsTabs.forEach( tab => tab.classList.remove( 'nav-tab-active' ) );
	currentTarget.classList.add( 'nav-tab-active' );

	if ( isEpioDefined ) {
		// show the fields for entering EP.io info
		epCredentialsHostLabel.textContent = 'ElasticPress.io Host URL';
		epCredentialsHostLegend.textContentt = 'Plug in your ElasticPress.io server here!';
		showElements( epCredentialsAdditionalFields );
		epCredentialsAdditionalFields.forEach( field => field.setAttribute( 'aria-hidden', 'false') )
	} else {

		// show the fields for entering self-hosted server info
		epCredentialsHostLabel.textContent = 'Elasticsearch Host URL';
		epCredentialsHostLegend.textContent = 'Plug in your Elasticsearch server here!';
		hideElements( epCredentialsAdditionalFields );
		epCredentialsAdditionalFields.forEach( field => field.setAttribute( 'aria-hidden', 'true') )
	}
}
