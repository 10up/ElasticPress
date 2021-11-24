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
