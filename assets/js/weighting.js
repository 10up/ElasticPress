/**
 * WordPress dependencies.
 */
import domReady from '@wordpress/dom-ready';

/**
 * Initialize.
 *
 * @returns {void}
 */
const init = () => {
	/**
	 * Update the value in the range input label.
	 *
	 * @param {Element} el Range input element.
	 */
	const updateSliderValue = (el) => {
		el.labels[0].querySelector('.weighting-value').textContent = el.disabled ? '0' : el.value;
	};

	/**
	 * Handle range slider input.
	 *
	 * @param {Event} event Input event.
	 */
	const onSliderInput = (event) => {
		updateSliderValue(event.currentTarget);
	};

	/**
	 * Handle checkbox change.
	 *
	 * @param {Event} event Change event.
	 */
	const onCheckboxChange = (event) => {
		const el = event.currentTarget.closest('fieldset').querySelector('input[type="range"]');

		el.disabled = !event.currentTarget.checked;

		updateSliderValue(el);
	};

	/**
	 * Bind events.
	 */
	const sliders = document.querySelectorAll('.weighting-settings input[type=range]');

	for (const slider of sliders) {
		slider.addEventListener('input', onSliderInput);
	}

	const checkboxes = document.querySelectorAll(
		'.weighting-settings .searchable input[type=checkbox]',
	);

	for (const checkbox of checkboxes) {
		checkbox.addEventListener('change', onCheckboxChange);
	}
};

/**
 * Initialize.
 */
domReady(init);
