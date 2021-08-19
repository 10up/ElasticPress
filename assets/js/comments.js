import { findAncestorByClass, debounce } from './utils/helpers';

const widgetSearchComments = document.querySelectorAll('.ep-widget-search-comments');

let selectedResultIndex;

widgetSearchComments.forEach((element) => {
	const input = document.createElement('input');
	input.setAttribute('autocomplete', 'off');
	input.setAttribute('type', 'search');
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
 * @param {Object} comments Comments to be showed
 * @param {HTMLInputElement} inputElement The input element used in the widget
 */
const updateResultsBox = (comments, inputElement) => {
	let items = '';
	let itemHTML = '';

	Object.keys(comments).forEach((id, index) => {
		if (comments[id]?.content && comments[id]?.link) {
			itemHTML = `
				<li class="ep-widget-search-comments-result-item">
					<a href="${comments[id].link}">
						${comments[id].content}
					</a>
				</li>
			`;

			if (typeof window.epCommentWidgetItemHTMLFilter !== 'undefined') {
				itemHTML = window.epCommentWidgetItemHTMLFilter(
					itemHTML,
					comments[id],
					index,
					inputElement.value,
				);
			}

			items += itemHTML;
		}
	});

	const widget = findAncestorByClass(inputElement, 'ep-widget-search-comments');
	const resultList = widget.querySelector('.ep-widget-search-comments-results');

	if (typeof window.epCommentWidgetItemsHTMLFilter !== 'undefined') {
		items = window.epCommentWidgetItemsHTMLFilter(items, inputElement.value);
	}

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

	let itemHTML = `<li class="ep-widget-search-comments-result-item-not-found">${window.epc.noResultsFoundText}</li>`;

	if (typeof window.epCommentWidgetItemNotFoundHTMLFilter !== 'undefined') {
		itemHTML = window.epCommentWidgetItemNotFoundHTMLFilter(
			itemHTML,
			window.epc.noResultsFoundText,
			inputElement.value,
		);
	}

	resultList.innerHTML = itemHTML;
};

function hasMinimumLength(inputElement) {
	const minimumLength = window.epc.minimumLengthToSearch || 2;
	return inputElement?.value?.trim().length >= minimumLength;
}

/**
 * Add class to the widget element while results are being loaded
 *
 * @param {boolean} isLoading Whether results are loading
 * @param {Node} inputElement Search input field
 */
function setIsLoading(isLoading, inputElement) {
	const widget = findAncestorByClass(inputElement, 'ep-widget-search-comments');

	if (isLoading) {
		widget.classList.add('ep-widget-search-comments-is-loading');
	} else {
		widget.classList.remove('ep-widget-search-comments-is-loading');
	}
}

/**
 *	Fetch comments
 *
 * @param {HTMLInputElement} inputElement The input element used in the widget
 * @return {(false|Promise)} Try to fetch comments
 */
function fetchResults(inputElement) {
	if (hasMinimumLength(inputElement)) {
		const widget = findAncestorByClass(inputElement, 'ep-widget-search-comments');
		const postTypeElement = widget.querySelector('#ep-widget-search-comments-post-type');
		const postTypeQueryParameter = postTypeElement?.value
			? `&post_type=${postTypeElement.value.trim()}`
			: '';

		setIsLoading(true, inputElement);
		return fetch(
			`${window.epc.restApiEndpoint}?s=${inputElement.value.trim()}${postTypeQueryParameter}`,
		)
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
			})
			.finally(() => {
				setIsLoading(false, inputElement);
			});
	}
	return false;
}

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
			if (results[selectedResultIndex]?.classList.contains('selected') || sizeResult === 1) {
				const indexItem = selectedResultIndex || 0;

				if (results[indexItem]) {
					const linkToComment = results[indexItem]
						.querySelector('a')
						?.getAttribute('href');

					window.location.href = linkToComment;
				}
			}

			break;

		default:
			break;
	}

	if (typeof previousSelectedResultIndex === 'number') {
		results[previousSelectedResultIndex].classList.remove('selected');
		results[previousSelectedResultIndex].setAttribute('aria-selected', 'false');
	}

	results[selectedResultIndex]?.classList.add('selected');
	results[selectedResultIndex]?.setAttribute('aria-selected', 'true');
};

const debounceFetchResults = debounce(fetchResults, 500);

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

	if (hasMinimumLength(target)) {
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
