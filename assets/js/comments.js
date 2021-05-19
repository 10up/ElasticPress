import { findAncestorByClass, debounce } from './utils/helpers';

const widgetSearchComments = document.querySelectorAll('.ep-widget-search-comments');

let selectedResultIndex;

widgetSearchComments.forEach((element) => {
	const input = document.createElement('input');
	input.setAttribute('autocomplete', 'off');
	input.setAttribute('class', 'ep-widget-search-comments-input');

	const resultList = document.createElement('ul');
	resultList.setAttribute('class', 'ep-widget-search-comments-results');

	element.appendChild(input);
	element.appendChild(resultList);
});

// these are the keycodes we listen for in handleUpDown,
// and in handleKeyup
const keyCodes = [
	38, // up
	40, // down
	13, // enter
];

/**
 * Hide the result list
 *
 * @param {HTMLInputElement} inputElement The input element used in the widget
 */
function hideResultsBox(inputElement) {
	selectedResultIndex = undefined;

	const widget = findAncestorByClass(inputElement, 'ep-widget-search-comments');
	const resultList = widget.querySelector('.ep-widget-search-comments-results');

	while (resultList.firstChild) {
		resultList.removeChild(resultList.firstChild);
	}
}

/**
 * Update the result list
 *
 * @param {object} comments Comments to be showed
 * @param {HTMLInputElement} inputElement The input element used in the widget
 */
const updateResultsBox = (comments, inputElement) => {
	let items = '';
	Object.keys(comments).forEach((id) => {
		if (comments[id]?.content && comments[id]?.link) {
			items += `
				<li class="ep-widget-search-comments-result-item">
					<a href="${comments[id].link}">
						${comments[id].content}
					</a>
				</li>
			`;
		}
	});

	const widget = findAncestorByClass(inputElement, 'ep-widget-search-comments');
	const resultList = widget.querySelector('.ep-widget-search-comments-results');

	resultList.innerHTML = items;
};

/**
 * Update the result list to inform the user that no results were found
 *
 * @param {HTMLInputElement} inputElement The input element used in the widget
 */
const showNotFoundInResultsBox = (inputElement) => {
	const widget = findAncestorByClass(inputElement, 'ep-widget-search-comments');
	const resultList = widget.querySelector('.ep-widget-search-comments-results');

	resultList.innerHTML = `<li class="ep-widget-search-comments-result-item">${window.epc.noResultsFoundText}</li>`;
};

/**
 *	Fetch comments
 *
 * @param {HTMLInputElement} inputElement The input element used in the widget
 * @returns {Promise}
 */
const fetchResults = (inputElement) =>
	fetch(`${window.epc.restApiEndpoint}?s=${inputElement.value.trim()}`)
		.then((response) => {
			if (!response.ok) {
				throw response;
			}

			return response.json();
		})
		.then((comments) => {
			if (Object.keys(comments).length === 0) {
				if (inputElement.value.trim()) {
					showNotFoundInResultsBox(inputElement);
				} else {
					hideResultsBox(inputElement);
				}
			} else {
				updateResultsBox(comments, inputElement);
			}
		})
		.catch(() => {
			hideResultsBox(inputElement);
		});

/**
 * Handle up, down and enter key
 *
 * @param {Event} event keyup event
 */
const handleUpDownEnter = (event) => {
	if (!keyCodes.includes(event.keyCode)) {
		return;
	}

	const widget = findAncestorByClass(event.target, 'ep-widget-search-comments');
	const resultList = widget.querySelector('.ep-widget-search-comments-results');
	const sizeResult = resultList.querySelectorAll('.ep-widget-search-comments-result-item').length;
	const results = resultList.children;

	const previousSelectedResultIndex = selectedResultIndex;

	switch (event.keyCode) {
		case 38: // Up
			selectedResultIndex =
				selectedResultIndex - 1 < 0 || typeof selectedResultIndex === 'undefined'
					? sizeResult - 1
					: selectedResultIndex - 1;

			break;

		case 40: // Down
			if (
				typeof selectedResultIndex === 'undefined' ||
				selectedResultIndex + 1 > sizeResult - 1
			) {
				selectedResultIndex = 0;
			} else {
				selectedResultIndex += 1;
			}

			break;

		case 13: // Enter
			if (results[selectedResultIndex]?.classList.contains('selected')) {
				const linkToComment = results[selectedResultIndex]
					?.querySelector('a')
					?.getAttribute('href');

				window.location.href = linkToComment;
			}

			break;

		default:
			break;
	}

	if (typeof previousSelectedResultIndex === 'number') {
		results[previousSelectedResultIndex].classList.remove('selected');
		results[previousSelectedResultIndex].setAttribute('aria-selected', 'false');
	}

	results[selectedResultIndex].classList.add('selected');
	results[selectedResultIndex].setAttribute('aria-selected', 'true');
};

/**
 * Callback for keyup in Widget Search Comment container.
 *
 * Calls a debounced function to get the search results via
 * api rest request.
 *
 * @param {event} event - keyup event
 */
const handleKeyup = (event) => {
	event.preventDefault();
	const { target, key, keyCode } = event;

	if (key === 'Escape' || key === 'Esc' || keyCode === 27) {
		hideResultsBox(target);
		target.setAttribute('aria-expanded', false);

		return;
	}

	if (keyCodes.includes(keyCode) && target.value !== '') {
		handleUpDownEnter(event);

		return;
	}

	if (target?.value?.trim().length >= 2) {
		const debounceFetchResults = debounce(fetchResults, 500);
		debounceFetchResults(target);
	} else {
		hideResultsBox(target);
	}
};

widgetSearchComments.forEach((element) => {
	const input = element.querySelector('.ep-widget-search-comments-input');

	input.addEventListener('keyup', handleKeyup);
	input.addEventListener('keydown', (event) => {
		if (event.keyCode === 38) {
			event.preventDefault();
		}
	});
	input.addEventListener('blur', function () {
		setTimeout(() => hideResultsBox(input), 200);
	});
});
