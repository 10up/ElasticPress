import jQuery from 'jquery';

const $epCredentialsTab = jQuery(document.getElementsByClassName('ep-credentials-tab'));
const $epCredentialsHostLabel = jQuery('.ep-host-row label');
const $epCredentialsHostLegend = jQuery(document.getElementsByClassName('ep-host-legend'));
const $epCredentialsAdditionalFields = jQuery(
	document.getElementsByClassName('ep-additional-fields'),
);
const epHostField = document.getElementById('ep_host');
const epHost = epHostField ? epHostField.value : null;
let epHostNewValue = '';

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
