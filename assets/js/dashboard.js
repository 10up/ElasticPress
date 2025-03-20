/**
 * Internal dependencies.
 */
import Tooltip from '@10up/component-tooltip';

/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';

/**
 * Window dependencies.
 */
const {
	ajaxurl,
	epDash: { skipUrl, syncUrl },
} = window;

/**
 * Determine whether a Feature's new settings will require a reindex.
 *
 * @param {FormData} data Form data.
 * @returns {boolean} Whether a reindex will need to occur when saved.
 */
const willChangeTriggerReindex = (data) => {
	const isActive = data.get('settings[active]') === '1';
	const settingRequiresReindex = data.get('setting_requires_reindex');

	if (settingRequiresReindex) {
		const settingIs = data.get(`settings[${settingRequiresReindex}]`);
		const settingWas = data.get(`setting_requires_reindex_was`);

		// eslint-disable-next-line eqeqeq
		if (isActive && settingIs == true && settingIs !== settingWas) {
			return true;
		}
	}

	const requiresReindex = data.get('requires_reindex') === '1';
	const wasActive = data.get('was_active') === '1';

	return requiresReindex && isActive && !wasActive;
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
				'Enabling this feature will begin re-syncing your content. Do you wish to proceed?',
				'elasticpress',
			),
		);

		if (!isConfirmed) {
			return;
		}
	}

	const feature = form.closest('.ep-feature');
	const syncingNotice = feature.querySelector('.requirements-status-notice--syncing');

	feature.classList.add('saving');
	form.submit.disabled = true;
	syncingNotice.style.display = null;

	const request = await fetch(ajaxurl, { method: 'POST', body: data });
	const response = await request.json();

	if (response.success === false) {
		const { data = [] } = response;

		if (data) {
			for (const { code = '' } of data) {
				if (code === 'is_indexing') {
					syncingNotice.style.display = 'block';
				}
			}
		}

		form.submit.disabled = false;
		feature.classList.remove('saving');
		return;
	}

	feature.classList.toggle('feature-active', response.data.active);

	if (typeof response.data.setting !== 'undefined') {
		form.setting_requires_reindex_was.value = response.data.setting;
	}

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
 * Handle setup form submission.
 *
 * Asks for confirmation if the user doesn't select any features to activate.
 * If the user wants to continue then skip installation.
 *
 * @param {Event} event Submit event.
 * @returns {void}
 */
const onSubmitSetup = (event) => {
	const features = new FormData(event.target).getAll('features[]');

	/**
	 * If any features are selected continue as normal...
	 */
	if (features.length > 0) {
		return;
	}

	/**
	 * ...otherwise stop submission and ask for confirmation.
	 */
	event.preventDefault();

	const confirm = window.confirm(
		__(
			'It looks like you’re trying to use ElasticPress’s advanced features only. If you’d like to activate basic search, please select Cancel and activate the Post Search Feature. Otherwise, please click Ok to configure advanced features.',
			'elasticpress',
		),
	);

	/**
	 * If the user wants to proceed, skip installation.
	 */
	if (confirm) {
		window.location = skipUrl;
	}
};

/**
 * Bind events.
 */
const featuresEl = document.querySelector('.ep-features');

if (featuresEl) {
	featuresEl.addEventListener('change', onToggle);
	featuresEl.addEventListener('submit', onSubmit);
	featuresEl.addEventListener('click', onClick);
}

const submitEl = document.querySelector('button.setup-button');

if (submitEl) {
	submitEl.form.addEventListener('submit', onSubmitSetup);
}

/**
 * Tooltips.
 */
// eslint-disable-next-line no-new
new Tooltip('.a11y-tip');
