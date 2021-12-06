/* eslint-disable camelcase, no-use-before-define */
import { __ } from '@wordpress/i18n';

const { ajaxurl, epDash } = window;

const $features = jQuery(document.getElementsByClassName('ep-features'));
const $epCredentialsTab = jQuery(document.getElementsByClassName('ep-credentials-tab'));
const $epCredentialsHostLabel = jQuery('.ep-host-row label');
const $epCredentialsHostLegend = jQuery(document.getElementsByClassName('ep-host-legend'));
const $epCredentialsAdditionalFields = jQuery(
	document.getElementsByClassName('ep-additional-fields'),
);
const epHostField = document.getElementById('ep_host');
const epHost = epHostField ? epHostField.value : null;
let epHostNewValue = '';

$features.on('click', '.learn-more, .collapse', function () {
	jQuery(this).parents('.ep-feature').toggleClass('show-full');
});

$features.on('click', '.settings-button', function () {
	jQuery(this).parents('.ep-feature').toggleClass('show-settings');
});

$features.on('click', '.save-settings', function (event) {
	event.preventDefault();

	if (jQuery(this).hasClass('disabled')) {
		return;
	}

	const { feature, requiresReindex, wasActive } = event.target.dataset;
	const $feature = $features.find(`.ep-feature-${feature}`);
	const settings = {};
	const $settings = $feature.find('.setting-field');

	$settings.each(function () {
		const $this = jQuery(this);
		const type = $this.attr('type');
		const name = $this.attr('data-field-name');
		const value = $this.val();

		if (type === 'radio') {
			if ($this.is(':checked')) {
				settings[name] = value;
			}
		} else {
			settings[name] = value;
		}
	});

	const requiresConfirmation = requiresReindex && wasActive === '0' && settings.active === '1';

	if (requiresConfirmation) {
		// eslint-disable-next-line no-alert
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

	$feature.addClass('saving');

	jQuery
		.ajax({
			method: 'post',
			url: ajaxurl,
			data: {
				action: 'ep_save_feature',
				feature,
				nonce: epDash.nonce,
				settings,
			},
		})
		.done((response) => {
			setTimeout(() => {
				$feature.removeClass('saving');

				if (settings.active === '1') {
					$feature.addClass('feature-active');
				} else {
					$feature.removeClass('feature-active');
				}

				event.target.dataset.wasActive = settings.active;
				event.target
					.closest('.settings')
					.querySelector('.js-toggle-feature').dataset.wasActive = settings.active;

				if (response.data.reindex) {
					window.location = epDash.sync_url;

					/*
					syncStatus = 'initialsync';

					updateSyncDash();

					// On initial sync, remove dashboard warnings that dont make sense
					jQuery(
						'[data-ep-notice="no-sync"], [data-ep-notice="auto-activate-sync"], [data-ep-notice="upgrade-sync"]',
					).remove();

					syncStatus = 'sync';

					$feature.addClass('feature-syncing');

					featureSync = feature;

					sync();
					*/
				}
			}, 700);
		})
		.error(() => {
			setTimeout(() => {
				$feature.removeClass('saving');
				$feature.removeClass('feature-active');
				$feature.removeClass('feature-syncing');
			}, 700);
		});
});

if (epHostField) {
	epHostField.addEventListener('input', (e) => {
		epHostNewValue = e.target.value;
	});
}

$epCredentialsTab.on('click', (e) => {
	const epio = e.currentTarget.getAttribute('data-epio') !== null;
	const $target = jQuery(e.currentTarget);
	const initial = $target.hasClass('initial');

	e.preventDefault();

	if (initial && !epHostField.disabled) {
		epHostField.value = epHost;
	} else {
		epHostField.value = epHostNewValue;
	}

	$epCredentialsTab.removeClass('nav-tab-active');
	$target.addClass('nav-tab-active');

	if (epio) {
		$epCredentialsHostLabel.text('ElasticPress.io Host URL');
		$epCredentialsHostLegend.text('Plug in your ElasticPress.io server here!');
		$epCredentialsAdditionalFields.show();
		$epCredentialsAdditionalFields.attr('aria-hidden', 'false');
	} else {
		$epCredentialsHostLabel.text('Elasticsearch Host URL');
		$epCredentialsHostLegend.text('Plug in your Elasticsearch server here!');
		$epCredentialsAdditionalFields.hide();
		$epCredentialsAdditionalFields.attr('aria-hidden', 'true');
	}
});

$features.on('change', '.js-toggle-feature', function (event) {
	const container = event.currentTarget
		.closest('.settings')
		.querySelector('.requirements-status-notice--reindex');

	const { value } = event.target;
	const { requiresReindex, wasActive } = event.currentTarget.dataset;

	if (requiresReindex && wasActive === '0' && value === '1') {
		container.style.display = 'block';
	} else {
		container.style.display = null;
	}
});
