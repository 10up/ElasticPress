/**
 * This file handles toggling tabs and fields as related
 * to the actual Elasticsearch service.
 */
import { showElements, hideElements } from './utils/helpers';

const epCredentialsTabs = document.querySelectorAll('.ep-credentials-tab');
const epCredentialsHostLabel = document.querySelector('.ep-host-row label');
const epCredentialsHostLegend = document.querySelector('.ep-host-legend');
const epCredentialsAdditionalFields = document.querySelectorAll('.ep-additional-fields');

const epHostField = document.getElementById('ep_host');
const epHost = epHostField ? epHostField.value : null;
let epHostNewValue = '';

/**
 * Updates the value of the epHostNewValue variable
 * for later reuse when tabs are toggled
 *
 * @param {event} event - input event
 */
const handleInputChange = (event) => {
	epHostNewValue = event.target.value;
};

/**
 * ToggleActiveTab
 *
 * @param e
 * @param {event} event - click event
 */
const toggleActiveTab = (e) => {
	e.preventDefault();

	const { currentTarget } = e;

	// check for EP.io
	const isEpioDefined = currentTarget.getAttribute('data-epio') !== null;
	const isInitial = currentTarget.classList.contains('initial');

	if (isInitial && !epHostField.disabled) {
		epHostField.value = epHost;
	} else {
		epHostField.value = epHostNewValue;
	}

	epCredentialsTabs.forEach((tab) => tab.classList.remove('nav-tab-active'));
	currentTarget.classList.add('nav-tab-active');

	if (isEpioDefined) {
		// show the fields for entering EP.io info
		epCredentialsHostLabel.textContent = 'ElasticPress.io Host URL';
		epCredentialsHostLegend.textContentt = 'Plug in your ElasticPress.io server here!';
		showElements(epCredentialsAdditionalFields);
		epCredentialsAdditionalFields.forEach((field) =>
			field.setAttribute('aria-hidden', 'false'),
		);
	} else {
		// show the fields for entering self-hosted server info
		epCredentialsHostLabel.textContent = 'Elasticsearch Host URL';
		epCredentialsHostLegend.textContent = 'Plug in your Elasticsearch server here!';
		hideElements(epCredentialsAdditionalFields);
		epCredentialsAdditionalFields.forEach((field) => field.setAttribute('aria-hidden', 'true'));
	}
};

const initSettingsPage = () => {
	if (!epHostField) {
		return;
	}

	// tabs appear only if nothing configured in wp-config.php
	if (epCredentialsTabs) {
		epCredentialsTabs.forEach((tab) => tab.addEventListener('click', toggleActiveTab));
	}
	if (epHostField) {
		epHostField.addEventListener('input', handleInputChange);
	}
};

export default initSettingsPage;
