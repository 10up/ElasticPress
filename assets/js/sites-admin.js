/* eslint-disable no-plusplus */
const { epsa } = window;

/**
 * handles toggle click function to activate or
 * deactivate indexing on mulitsite setups
 */
const handleToggleClick = async function handleToggleClick() {
	const checked = this.checked ? 'yes' : 'no';

	const params = {
		action: 'ep_site_admin',
		blog_id: this.dataset.blogid,
		nonce: epsa.nonce,
		checked,
	};

	const encodedParams = new URLSearchParams(params).toString();

	try {
		// fetch doesn't allow a body object in the config for GET requests
		const res = await fetch(`${epsa.ajax_url}?${encodedParams}`, {
			method: 'GET',
		});
		const response = await res.json();

		if (response.success) {
			document.getElementById(`switch-label-${this.dataset.blogid}`).innerHTML = this.checked
				? 'On'
				: 'Off';
		}
	} catch (error) {
		// eslint-disable-next-line no-console
		console.log('There was an error updating ElasticPress on the Multisite Network', error);
	}
};

window.addEventListener('load', () => {
	const toggles = document.getElementsByClassName('index-toggle');
	for (let i = 0; i < toggles.length; i++) {
		toggles[i].addEventListener('click', handleToggleClick);
	}
});
