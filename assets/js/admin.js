const { epAdmin, ajaxurl } = window;

jQuery('.notice').on('click', '.notice-dismiss', (event) => {
	const notice = event.delegateTarget.getAttribute('data-ep-notice');

	if (!notice) {
		return;
	}

	jQuery.ajax({
		method: 'post',
		data: {
			nonce: epAdmin.nonce,
			action: 'ep_notice_dismiss',
			notice,
		},
		url: ajaxurl,
	});
});

jQuery('.weighting-settings input[type=range]').on('input', function () {
	const el = jQuery(this);

	el.prev('label').find('.weighting-value').text(el.val());
});

jQuery('.weighting-settings .searchable input[type=checkbox]').change(function () {
	const $checkbox = jQuery(this);
	const $rangeInput = $checkbox.parent().next('.weighting').find('input[type=range]');
	const $weightDisplay = $rangeInput.prev('label').find('.weighting-value');

	// toggle range input
	$rangeInput.prop('disabled', !this.checked);

	// get new weight display value, and set it
	const newWeightDisplay = !this.checked ? '0' : $rangeInput.val();
	$weightDisplay.text(newWeightDisplay);
});
