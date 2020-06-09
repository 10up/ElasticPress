/* eslint-disable camelcase, import/no-unresolved, no-use-before-define */
import 'promise-polyfill/src/polyfill';
import { ajaxurl, epDash } from 'window';
import 'whatwg-fetch';
import { initSyncFunctions, handleReindexAfterSave } from './es-sync';
import initSettingsPage from './es-settings';
import { findAncestorByClass } from './utils/helpers';

let features;

/**
 * main Dashboard init function. Pulls in sync functions
 * from ./es-sync.js as well
 */
const initDashboard = () => {
	features = document.querySelector('.ep-features');
	if (features) {
		features.addEventListener('click', handleFeatureClicks);
	}
	initSyncFunctions();
	initSettingsPage();
};

document.addEventListener('DOMContentLoaded', initDashboard);

/**
 * Delegates clicks on the feature boxes to the respective
 * handlers
 *
 * @param {event} event - click event
 */
const handleFeatureClicks = (event) => {
	const { target } = event;

	// toggles the "more" extra information inside the feature box
	if (target.matches('.learn-more') || target.matches('.collapse')) {
		toggleClassOnParent(target, 'show-full');
		return;
	}

	// showing/hiding the "settings" part of the feature box
	if (target.matches('.settings-button')) {
		toggleClassOnParent(target, 'show-settings');
		return;
	}

	// calls an async function to save the config
	// to the db
	if (target.matches('.save-settings')) {
		saveFeatureSettings(event);
	}
};

/**
 * Handles the toggling for each Feature section
 *
 * @param {Node} childNode - click target node
 * @param {string} className - class to toggle on the parent
 * @param {string} matchClass - optional class to match on the parent, defaults to 'ep-feature'
 */
const toggleClassOnParent = (childNode, className, matchClass = 'ep-feature') => {
	const parent = findAncestorByClass(childNode, matchClass);
	if (!parent.classList.contains(className)) {
		parent.classList.add(className);
	} else {
		parent.classList.remove(className);
	}
};

/**
 * handles the ajax form submit to save the new settings
 *
 * @param {event} event - click event on the save button
 */

const saveFeatureSettings = (event) => {
	event.preventDefault();
	const { target } = event;

	// don't do anything if this feature is disabled
	if (target.classList.contains('disabled')) {
		return;
	}

	const featureName = event.target.getAttribute('data-feature');
	const currentFeature = features.querySelector(`.ep-feature-${featureName}`);
	const inputFields = currentFeature.querySelectorAll('.setting-field');
	const settings = {};

	// get the current selected settings
	// to pass into the update function
	inputFields.forEach((field) => {
		const type = field.getAttribute('type');
		const name = field.getAttribute('data-field-name');

		if (type === 'radio') {
			if (field.checked) {
				settings[name] = field.value;
			}
		} else {
			settings[name] = field.value;
		}
	});

	updateFeature(currentFeature, settings, featureName);
};

/**
 * Async function to handle ajax submit to update the setting
 *
 * @param {Node} feature - feature box container
 * @param {object} settings - form values
 * @param {string} featureName - name of feature to update
 */
const updateFeature = async (feature, settings, featureName) => {
	const postBody = {
		nonce: epDash.nonce,
		action: 'ep_save_feature',
		feature: featureName,
	};

	// request body requires formatting to read as query string,
	// rather than as JSON - can the back-end handle json to avoid this?
	const formattedBody = new URLSearchParams(postBody).toString();

	// jQuery used to format this part for for free...
	// since the object is nested, the encoding doesn't work
	// on the postBody object as-is...
	const newSettings = Object.keys(settings)
		.map((key) => {
			return `${encodeURIComponent(`settings[${key}]`)}=${encodeURIComponent(settings[key])}`;
		})
		.join('&')
		.replace(/%20/g, '+');

	const fetchConfig = {
		method: 'POST',
		body: `${formattedBody}&${newSettings}`,
		headers: {
			'Content-type': 'application/x-www-form-urlencoded; charset=UTF-8',
		},
	};

	try {
		feature.classList.add('saving');
		const res = await window.fetch(ajaxurl, fetchConfig);
		const response = await res.json();

		// when complete...
		feature.classList.remove('saving');

		// set the current active status on the feature box
		if (settings.active === '1') {
			feature.classList.add('feature-active');
		} else {
			feature.classList.remove('feature-active');
		}

		if (response.data.reindex) {
			handleReindexAfterSave(feature, featureName);
		}
	} catch (error) {
		// eslint-disable-next-line
		console.error(`There was an error updating the settings for ${featureName}`, error);

		// not sure why this has a timeout... keeping the legacy code here.
		setTimeout(() => {
			feature.classList.remove('saving', 'feature-active', 'feature-syncing');
		}, 700);
	}
};
