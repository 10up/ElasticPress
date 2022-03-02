/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';

/**
 * Window dependencies.
 */
const {
	ajaxurl,
	epDash: { syncUrl },
} = window;

/**
 * Determine whether a Feature's new settings will require a reindex.
 *
 * @param {FormData} data Form data.
 * @return {boolean} Whether a reindex will need to occur when saved.
 */
const willChangeTriggerReindex = (data) => {
	return (
		data.get('requires_reindex') === '1' &&
		data.get('was_active') === '0' &&
		data.get('settings[active]') === '1'
	);
};

/**
 * Handle Feature settings being submitted.
 *
 * @param {Event} event Submit event.
 */
const onSubmit = async (event) => {
	event.preventDefault();

	const form = event.target;
	const data = new FormData(form);
	const requiresConfirmation = willChangeTriggerReindex(data);

	if (requiresConfirmation) {
		/* eslint-disable no-alert */
		const isConfirmed = window.confirm(
			__(
				'Enabling this feature will begin re-indexing your content. Do you wish to proceed?',
				'elasticpress',
			),
		);

		if (!isConfirmed) {
			return;
		}
	}

	const feature = form.closest('.ep-feature');

	feature.classList.add('saving');
	form.submit.disabled = true;

	const request = await fetch(ajaxurl, { method: 'POST', body: data });
	const response = await request.json();

	feature.classList.toggle('feature-active', response.data.active);

	if (response.data.reindex) {
		window.location = syncUrl;
	} else {
		feature.classList.remove('saving');
		form.submit.disabled = false;
		form.was_active.value = response.data.active ? '1' : '0';
	}
};

/**
 * Handle a Feature being set to be turned on or off.
 *
 * @param {Event} event Change event.
 */
const onToggle = (event) => {
	const { form } = event.target;
	const data = new FormData(form);

	const notice = form.querySelector('.requirements-status-notice--reindex');
	const requiresConfirmation = willChangeTriggerReindex(data);

	if (notice) {
		notice.style.display = requiresConfirmation ? 'block' : null;
	}
};

/**
 * Handle click events within a Feature.
 *
 * @param {Event} event Click event.
 */
const onClick = (event) => {
	const { target } = event;

	/**
	 * Handle toggling settings.
	 */
	if (target.classList.contains('settings-button')) {
		const feature = target.closest('.ep-feature');

		feature.classList.toggle('show-settings');
		target.setAttribute('aria-expanded', feature.classList.contains('show-settings'));
	}

	/**
	 * Handle toggling description.
	 */
	if (target.classList.contains('learn-more') || target.classList.contains('collapse')) {
		target.closest('.ep-feature').classList.toggle('show-full');
	}
};

/**
 * Bind events.
 */
const featuresEl = document.querySelector('.ep-features');

featuresEl.addEventListener('change', onToggle);
featuresEl.addEventListener('submit', onSubmit);
featuresEl.addEventListener('click', onClick);
