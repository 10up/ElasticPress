/**
 * WordPress dependencies.
 */
import apiFetch from '@wordpress/api-fetch';
import { ToggleControl } from '@wordpress/components';
import domReady from '@wordpress/dom-ready';
import { render, useState, WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Window dependencies.
 */
const { ajaxurl, epsa } = window;

/**
 * Toggle component.
 *
 * @param {object} props Component props.
 * @param {string} props.blogId Blog ID.
 * @param {boolean} props.isDefaultChecked Whether checked by default.
 * @returns {WPElement} Toggle component.
 */
const ElasticPressToggleControl = ({ blogId, isDefaultChecked }) => {
	const [isChecked, setIsChecked] = useState(isDefaultChecked);
	const [isLoading, setIsLoading] = useState(false);

	/**
	 * Handle toggle change.
	 *
	 * @param {boolean} isChecked New checked state.
	 * @returns {void}
	 */
	const onChange = async (isChecked) => {
		setIsChecked(isChecked);
		setIsLoading(true);

		const formData = new FormData();

		formData.append('action', 'ep_site_admin');
		formData.append('blog_id', blogId);
		formData.append('checked', isChecked ? 'yes' : 'no');
		formData.append('nonce', epsa.nonce);

		await apiFetch({
			method: 'POST',
			url: ajaxurl,
			body: formData,
		});

		setIsLoading(false);
	};

	return (
		<ToggleControl
			checked={isChecked}
			className="index-toggle"
			disabled={isLoading}
			label={isChecked ? __('On', 'elasticpress') : __('Off', 'elasticpress')}
			onChange={onChange}
			__nextHasNoMarginBottom
		/>
	);
};

/**
 * Initialize.
 *
 * @returns {void}
 */
const init = () => {
	const toggles = document.getElementsByClassName('index-toggle');

	for (const toggle of toggles) {
		render(
			<ElasticPressToggleControl
				blogId={toggle.dataset.blogId}
				isDefaultChecked={toggle.checked}
			/>,
			toggle.parentElement,
		);
	}
};

domReady(init);
