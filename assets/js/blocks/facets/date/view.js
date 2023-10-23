/**
 * WordPress dependencies.
 */
import domReady from '@wordpress/dom-ready';

/**
 * Initializes the date facet functionality.
 *
 */
const initFacet = () => {
	const forms = document.querySelectorAll('.ep-facet-date-form');
	// eslint-disable-next-line no-undef
	const filterName = epFacetDate.dateFilterName;

	forms.forEach(function (form) {
		form.addEventListener('submit', function (event) {
			event.preventDefault();

			const { value } = this.querySelector(`[name="${filterName}"]:checked`);
			const { value: startDateValue } =
				this.querySelector('.ep-date-range-picker')?.querySelector(
					`[name="${filterName}_from"]`,
				) || '';

			const { value: endDateValue } =
				this.querySelector('.ep-date-range-picker')?.querySelector(
					`[name="${filterName}_to"]`,
				) || '';

			const currentURL = window.location.href;
			const newUrl = new URL(currentURL);

			if (value !== 'custom') {
				newUrl.searchParams.set(filterName, value);
			} else {
				newUrl.searchParams.set(filterName, `${startDateValue},${endDateValue}`);
			}

			window.location.href = decodeURIComponent(newUrl);
		});

		const radioButtons = form.querySelectorAll('.ep-radio');
		radioButtons.forEach(function (element) {
			element.addEventListener('change', function () {
				const dateRangePicker = element
					.closest('.ep-facet-date-form')
					.querySelector('.ep-date-range-picker');
				if (element.value === 'custom') {
					dateRangePicker?.classList.remove('is-hidden');
				} else {
					dateRangePicker?.classList.add('is-hidden');
				}
			});
		});
	});
};

domReady(initFacet);
