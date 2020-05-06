/* eslint-disable import/no-unresolved */
const { epAdmin, ajaxurl } = window;

/**
 * Dismiss any plugin admin notices on click
 */
const handleDismissAdminNotices = () => {
	const notices = document.querySelectorAll('.notice');

	/**
	 * Handles click event - checkes for the notice data attribute
	 * to send to the dasboard.php handler for ep_notice_dismiss
	 *
	 * @param {event} event - click event
	 */
	const handleNoticeClick = (event) => {
		const { target } = event;

		// delegate to the el with notice-dismiss class
		if (!target.matches('.notice-dismiss')) {
			return;
		}

		if (!target.hasAttribute('data-ep-notice')) {
			return;
		}

		const notice = target.dataset('ep-notice');

		const postData = {
			nonce: epAdmin.nonce,
			action: 'ep_notice_dismiss',
			notice,
		};

		// set request to the back end to dismiss
		// the current notice
		fetch(ajaxurl, {
			method: 'POST',
			body: JSON.stringify(postData),
		});
	};

	notices.forEach((notice) => notice.addEventListener('click', handleNoticeClick));
};

/**
 * Sets the weight label text based on the value
 * of the range inputs
 */
const setWeightLabelsFromRangeValues = () => {
	const rangeInputs = document.querySelectorAll('.weighting-settings input[type=range]');

	/**
	 * Handles the change event
	 *
	 * @param {event} event - change event from the range sliders
	 */
	const handleRangeChange = (event) => {
		const rangeInput = event.target;
		const label = rangeInput.previousElementSibling.querySelector('.weighting-value');
		label.innerHTML = rangeInput.value;
	};

	rangeInputs.forEach((rangeInput) => rangeInput.addEventListener('change', handleRangeChange));
};

/**
 * Toggles the display of weight field values when the
 * "searchable" checkbox is toggled on and off, as well
 * as disabling on enabling the range slider.
 */
const handleWeightFields = () => {
	const checkboxes = document.querySelectorAll(
		'.weighting-settings .searchable input[type=checkbox]',
	);

	/**
	 * Handles the toggling and disabling
	 *
	 * @param {event} event - click event from the checkboxes
	 */
	const handleCheckboxChange = (event) => {
		const checkbox = event.target;
		const rangeInput = checkbox.parentNode.nextElementSibling.querySelector(
			'input[type=range]',
		);
		const weightDisplay = rangeInput.previousElementSibling.querySelector('.weighting-value');

		// toggle range input
		rangeInput.setAttribute('disabled', !checkbox.checked);

		// get new weight display value, and set it
		const newWeightDisplay = !checkbox.checked ? '0' : rangeInput.value;
		weightDisplay.innerHTML = newWeightDisplay;
	};

	// add listener to each checkbox
	checkboxes.forEach((checkbox) => checkbox.addEventListener('change', handleCheckboxChange));
};

const initAdmin = () => {
	handleDismissAdminNotices();
	setWeightLabelsFromRangeValues();
	handleWeightFields();
};

document.addEventListener('DOMContentLoaded', initAdmin);
