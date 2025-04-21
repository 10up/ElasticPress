/**
 * WordPress dependencies.
 */
import domReady from '@wordpress/dom-ready';
import { __ } from '@wordpress/i18n';

/**
 * Initialize.
 *
 * @returns {void}
 */
const init = () => {
	const tabs = document.querySelectorAll('.ep-credentials-tab');
	const host = document.getElementById('ep_host');
	const hostLabel = host.labels[0];
	const hostDescription = host.nextElementSibling;
	const additionalFields = document.getElementsByClassName('ep-additional-fields');

	let activeTab = document.querySelector('.nav-tab-active');

	/**
	 * Is the current tab the ElasticPress.io tab?
	 *
	 * @returns {boolean} Whether the current tab is for ElasticPress.io.
	 */
	const isEpio = () => {
		return activeTab && 'epio' in activeTab.dataset;
	};

	let epioHost = isEpio() ? host.value : '';
	let esHost = isEpio() ? '' : host.value;

	/**
	 * Handle input on the host field.
	 *
	 * @param {Event} event Input event.
	 * @returns {void}
	 */
	const onInput = (event) => {
		if (isEpio()) {
			epioHost = event.currentTarget.value;
		} else {
			esHost = event.currentTarget.value;
		}
	};

	/**
	 * Handle clicking on a tab.
	 *
	 * @param {Event} event Click event.
	 * @returns {void}
	 */
	const onClick = (event) => {
		activeTab = event.currentTarget;

		/**
		 * Set active tab.
		 */
		for (const tab of tabs) {
			tab.classList.toggle('nav-tab-active', tab === activeTab);
		}

		/**
		 * Hide or show additional fields.
		 */
		for (const additionalField of additionalFields) {
			additionalField.classList.toggle('hidden', !isEpio());
		}

		/**
		 * Update field label.
		 */
		hostLabel.innerText = isEpio()
			? __('ElasticPress.io Host URL', 'elasticpress')
			: __('Elasticsearch Host URL', 'elasticpress');

		/**
		 * If the host field is disabled, we're done.
		 */
		if (host.disabled) {
			return;
		}

		/**
		 * Restore field value for the current tab.
		 */
		host.value = isEpio() ? epioHost : esHost;

		/**
		 * Update host field description.
		 */
		hostDescription.innerText = isEpio()
			? __('Plug in your ElasticPress.io server here!', 'elasticpress')
			: __('Plug in your Elasticsearch server here!', 'elasticpress');
	};

	/**
	 * Bind input event to host field.
	 */
	if (host) {
		host.addEventListener('input', onInput);
	}

	/**
	 * Bind click events to tabs.
	 */
	for (const tab of tabs) {
		tab.addEventListener('click', onClick);
	}
};

/**
 * Initialize.
 */
domReady(init);
