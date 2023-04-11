/* eslint-disable camelcase, no-underscore-dangle, no-use-before-define */

/**
 * WordPress dependencies.
 */
import { applyFilters } from '@wordpress/hooks';

/**
 * Internal dependencies.
 */
import './back-compat';
import {
	findAncestorByClass,
	escapeDoubleQuotes,
	replaceGlobally,
	debounce,
	domReady,
	generateRequestId,
} from '../utils/helpers';

const { epas } = window;

// Ensure we have an endpoint URL, or
// else this shouldn't happen
if (epas.endpointUrl && epas.endpointUrl !== '') {
	init();

	// Publically expose API
	window.epasAPI = {
		hideAutosuggestBox,
		updateAutosuggestBox,
		esSearch,
		buildSearchQuery,
	};
}

/**
 * Submit the search form
 *
 * @param {Node} input - input element
 */
function submitSearchForm(input) {
	input.closest('form').submit();
}

/**
 * Set the expanded aria state on the input
 *
 * @param {boolean} haveOptions - whether or not the autosuggest list contains results
 * @param {Node}    input       - search input
 */
function toggleInputAria(haveOptions, input) {
	input.setAttribute('aria-expanded', haveOptions);
}

/**
 * Set the active descendant aria attribute input
 *
 * @param {string} id    - id of the currently selected element
 * @param {Node}   input - search input
 */
function setInputActiveDescendant(id, input) {
	input.setAttribute('aria-activedescendant', id);
}

/**
 * Take selected item and fill the search input
 *
 * @param {Node}   input - input element
 * @param {string} text  - new input value
 */
function selectAutosuggestItem(input, text) {
	input.value = text; // eslint-disable-line no-param-reassign
}

/**
 * Fires events when autosuggest results are clicked,
 * and if GA tracking is activated
 *
 * @param {object} detail - value to pass on to the Custom Event
 */
function triggerAutosuggestEvent(detail) {
	const event = new CustomEvent('ep-autosuggest-click', { detail });
	window.dispatchEvent(event);

	if (
		detail.searchTerm &&
		parseInt(epas.triggerAnalytics, 10) === 1 &&
		typeof gtag === 'function'
	) {
		const action = `click - ${detail.searchTerm}`;
		// eslint-disable-next-line no-undef
		gtag('event', action, {
			event_category: 'EP :: Autosuggest',
			event_label: detail.url,
			transport_type: 'beacon',
		});
	}
}

/**
 * Navigate to the selected item, and provides
 * event hook for JS customizations, like GA
 *
 * @param {string} searchTerm - user defined search term
 * @param {string} url        - post url from dataset in search result
 */
function goToAutosuggestItem(searchTerm, url) {
	const detail = {
		searchTerm,
		url,
	};

	triggerAutosuggestEvent(detail);
	window.location.href = url;
}

/**
 * Respond to an item selection based on the predefined behavior.
 * If epas.action is set to "navigate" (the default), redirects the browser to the URL of the selected item
 * If epas.action is set to any other value (such as "search"), fill in the value and perform the search
 *
 * @param {Node} input   - search input
 * @param {Node} element - search term result item
 * @returns {Function} calls the submitSearchForm function
 */
function selectItem(input, element) {
	if (epas.action === 'navigate') {
		/**
		 * Allow  to replace the callback function used when navigating an Autosuggest item.
		 *
		 * @filter ep.Autosuggest.navigateCallback
		 * @since 4.5.1
		 *
		 * @param {Function} goToAutosuggestItem Autosuggest Callback.
		 * @returns {Function} Autosuggest Callback
		 */
		const navigateCallback = applyFilters(
			'ep.Autosuggest.navigateCallback',
			goToAutosuggestItem,
		);

		return navigateCallback(input.value, element.dataset.url);
	}

	selectAutosuggestItem(input, element.innerText);
	return submitSearchForm(input);
}

/**
 * Build the search query from the search text - the query is generated in PHP
 * and passed into the front end as window.epas = { "query...
 *
 * @returns {string} json string
 */
function getJsonQuery() {
	if (typeof window.epas === 'undefined') {
		const error = 'No epas object defined';

		// eslint-disable-next-line no-console
		console.warn(error);
		return { error };
	}

	return window.epas;
}

/**
 * Build the search query from the search text
 *
 * @param {string} searchText    - user search string
 * @param {string} placeholder   - placeholder text to replace
 * @param {object} options       - Autosuggest settings
 * @param {string} options.query - JSON query string to pass to ElasticSearch
 * @returns {string} json representation of search query
 */
function buildSearchQuery(searchText, placeholder, { query }) {
	const newQuery = replaceGlobally(query, placeholder, searchText);
	return newQuery;
}

/**
 * Build the ajax request
 *
 * @param {string} query      - json string
 * @param {string} searchTerm - user search term
 * @returns {object} AJAX object request
 */
async function esSearch(query, searchTerm) {
	const fetchOptions = {
		body: query,
		method: 'POST',
		mode: 'cors',
		headers: {
			'Content-Type': 'application/json; charset=utf-8',
		},
	};

	if (epas?.http_headers && typeof epas.http_headers === 'object') {
		Object.keys(epas.http_headers).forEach((name) => {
			fetchOptions.headers[name] = epas.http_headers[name];
		});
	}

	// only applies headers if using ep.io endpoint
	if (epas.addSearchTermHeader) {
		fetchOptions.headers['EP-Search-Term'] = encodeURI(searchTerm);
	}

	// only add a request ID if using ep.io endpoint
	const requestId = generateRequestId(epas?.requestIdBase || '');
	if (requestId) {
		fetchOptions.headers['X-ElasticPress-Request-ID'] = requestId;
	}

	try {
		/**
		 * Filter the Elasticsearch fetch options used for Autosuggest.
		 *
		 * @filter ep.Autosuggest.fetchOptions
		 * @since 4.5.1
		 *
		 * @param {object} fetchOptions Options.
		 * @returns {object} Options.
		 */
		const response = await fetch(
			epas.endpointUrl,
			applyFilters('ep.Autosuggest.fetchOptions', fetchOptions),
		);

		if (!response.ok) {
			throw Error(response.statusText);
		}

		const data = await response.json();

		/**
		 * Filter the Elasticsearch response data used for Autosuggest.
		 *
		 * @filter ep.Autosuggest.data
		 * @since 4.3.1
		 *
		 * @param {object} data Response data.
		 * @param {string} searchTerm Search term.
		 * @returns {object} Response data.
		 */
		return applyFilters('ep.Autosuggest.data', data, searchTerm);
	} catch (error) {
		// eslint-disable-next-line no-console
		console.error(error);
		return error;
	}
}

/**
 * Update the auto suggest box with new options or hide if none
 *
 * @param {Array}  options - search results
 * @param {string} input   - search string
 * @returns {boolean} return true
 */
function updateAutosuggestBox(options, input) {
	let listHTML = '';

	// get the search term for use later on
	const { value: searchText } = input;
	const container = findAncestorByClass(input, 'ep-autosuggest-container');
	const resultsContainer = container.querySelector('.ep-autosuggest');
	const suggestList = resultsContainer.querySelector('.autosuggest-list');

	// empty the the list of all child nodes
	while (suggestList.firstChild) {
		suggestList.removeChild(suggestList.firstChild);
	}

	if (options.length > 0) {
		resultsContainer.style = 'display: block;';
	} else {
		resultsContainer.style = 'display: none;';
	}

	// anticipating the future... a setting where we configure
	// a limit of results to show, and optionally append a
	// link to "all results" or something of that nature
	const resultsLimit = options.length;

	// create markup for list items
	// eslint-disable-next-line
	for ( let index = 0; resultsLimit > index; ++index ) {
		const option = options[index];
		const text = option._source.post_title;
		const url = option._source.permalink;
		const escapedText = escapeDoubleQuotes(text);

		const searchParts = searchText.trim().split(' ');
		let resultsText = escapedText;

		if (epas.highlightingEnabled) {
			// uses some regex magic to match upper/lower/capital case
			const regex = new RegExp(`\\b(${searchParts.join('|')})`, 'gi');
			resultsText = resultsText.replace(
				regex,
				(word) =>
					`<${epas.highlightingTag} class="${epas.highlightingClass} ep-autosuggest-highlight">${word}</${epas.highlightingTag}>`,
			);
		}

		let itemHTML = `<li class="autosuggest-item" role="option" aria-selected="false" id="autosuggest-option-${index}">
				<a href="${url}" class="autosuggest-link" data-search="${escapedText}" data-url="${url}"  tabindex="-1">
					${resultsText}
				</a>
			</li>`;

		/**
		 * Filter the HTML for an Autosuggest suggestion.
		 *
		 * @filter ep.Autosuggest.itemHTML
		 * @since 4.3.1
		 *
		 * @param {string} itemHTML Item HTML.
		 * @param {object} option Elasticsearch record for suggestion.
		 * @param {number} index Suggestion index.
		 * @param {string} searchText Search term.
		 * @returns {string} Item HTML.
		 */
		itemHTML = applyFilters('ep.Autosuggest.itemHTML', itemHTML, option, index, searchText);

		listHTML += itemHTML;
	}

	/**
	 * Filter the HTML for the list of Autosuggest suggestions.
	 *
	 * @filter ep.Autosuggest.listHTML
	 * @since 4.3.1
	 *
	 * @param {string} listHTML List HTML.
	 * @param {object[]} options Elasticsearch records for suggestions.
	 * @param {Element} input Input element that triggered Autosuggest.
	 * @returns {string} List HTML.
	 */
	listHTML = applyFilters('ep.Autosuggest.listHTML', listHTML, options, input);

	// append list items to the list
	suggestList.innerHTML = listHTML;

	suggestList.addEventListener('click', (event) => {
		event.preventDefault();

		const element = event.target.closest('.autosuggest-link');

		if (suggestList.contains(element)) {
			selectItem(input, element);
		}
	});

	return true;
}

/**
 * Hide the auto suggest box
 *
 * @returns {boolean} returns true
 */
function hideAutosuggestBox() {
	const lists = document.querySelectorAll('.autosuggest-list');
	const containers = document.querySelectorAll('.ep-autosuggest');

	// empty all EP results lists
	lists.forEach((list) => {
		while (list.firstChild) {
			list.removeChild(list.firstChild);
		}
	});

	// hide all EP results containers
	containers.forEach((container) => {
		// eslint-disable-next-line
		container.style = 'display: none;';
	});

	return true;
}

/**
 * Checks for any manually ordered posts and puts them in the correct place
 *
 * @param {Array}  hits       - ES results
 * @param {string} searchTerm - user search term
 * @returns {object} formatted hits
 */
function checkForOrderedPosts(hits, searchTerm) {
	const toInsert = {};
	const taxName = 'ep_custom_result';
	const lowerCaseSearchTerm = searchTerm.toLowerCase();

	const filteredHits = hits.filter((hit) => {
		// Should we retain this hit in its current position?
		let retain = true;

		if (undefined !== hit._source.terms && undefined !== hit._source.terms[taxName]) {
			hit._source.terms[taxName].forEach((currentTerm) => {
				if (currentTerm.name.toLowerCase() === lowerCaseSearchTerm) {
					toInsert[currentTerm.term_order] = hit;

					retain = false;
				}
			});
		}

		return retain;
	});

	const orderedInserts = {};

	Object.keys(toInsert)
		.sort()
		.forEach((key) => {
			orderedInserts[key] = toInsert[key];
		});

	if (Object.keys(orderedInserts).length > 0) {
		Object.keys(orderedInserts).forEach((key) => {
			const insertItem = orderedInserts[key];

			filteredHits.splice(key - 1, 0, insertItem);
		});
	}

	return filteredHits;
}

/**
 * Add class to the form element while suggestions are being loaded
 *
 * @param {boolean} isLoading - whether suggestions are loading
 * @param {Node}    input     - search input field
 */
function setFormIsLoading(isLoading, input) {
	const form = input.closest('form');

	if (isLoading) {
		form.classList.add('is-loading');
	} else {
		form.classList.remove('is-loading');
	}
}

/**
 * init method called if the epas endpoint is defined
 */
function init() {
	const selectors = [epas.defaultSelectors, epas.selector].filter(Boolean).join(',');

	if (!selectors) {
		return;
	}

	// For the Autosuggest element that will be cloned.
	let autosuggestElement;

	// to be used by the handleUpDown function
	// to keep track of the currently selected result
	let currentIndex;

	// these are the keycodes we listen for in handleUpDown,
	// and in handleKeyup
	const keyCodes = [
		38, // up
		40, // down
		13, // enter
	];

	/**
	 * Handles keyup event on the search input
	 *
	 * @param {event} event - keyup event
	 */
	const handleUpDown = (event) => {
		if (!keyCodes.includes(event.keyCode)) {
			return;
		}

		const input = event.target;
		const container = findAncestorByClass(input, 'ep-autosuggest-container');
		const suggestList = container.querySelector('.autosuggest-list');
		const results = suggestList.children;

		/**
		 * helper function to get the currently selected result
		 *
		 * @returns {number} index of the selected search result
		 */
		const getSelectedResultIndex = () => {
			const resultsArr = Array.from(results);
			return resultsArr.findIndex((result) => result.classList.contains('selected'));
		};

		/**
		 * helper function to deselect results
		 */
		const deSelectResults = () => {
			Array.from(results).forEach((result) => {
				result.classList.remove('selected');
				result.setAttribute('aria-selected', 'false');
			});
		};

		/**
		 * helper function to selected the next result
		 */
		const selectNextResult = () => {
			if (currentIndex >= 0) {
				const el = results[currentIndex];
				el.classList.add('selected');
				el.setAttribute('aria-selected', 'true');
				setInputActiveDescendant(el.id, input);
			}
		};

		// select next or previous based on keyCode
		// if enter, navigate to that element
		switch (event.keyCode) {
			case 38: // Up
				// don't go less than the 0th index
				currentIndex = currentIndex - 1 >= 0 ? currentIndex - 1 : 0;
				deSelectResults();
				break;
			case 40: // Down
				if (typeof currentIndex === 'undefined') {
					// index is not yet defined, so let's
					// start with the first one
					currentIndex = 0;
				} else {
					const current = getSelectedResultIndex();

					// check for existence of next result
					if (results[current + 1]) {
						currentIndex = current + 1;
						deSelectResults();
					}
				}
				break;
			case 13: // Enter
				if (results[currentIndex]?.classList.contains('selected')) {
					// navigate to the item defined in the span's data-url attribute
					selectItem(input, results[currentIndex].querySelector('.autosuggest-link'));
				}
				break;
			default:
				// No item selected
				break;
		}

		// only check next element if up and down key pressed
		if (results[currentIndex] && results[currentIndex].classList.contains('autosuggest-item')) {
			selectNextResult();
		} else {
			deSelectResults();
		}

		// keep cursor from heading back to the beginning in the input
		if (event.keyCode === 38) {
			// return false;
			event.preventDefault();
		}
	};

	/**
	 * Get the searched post types from the search form.
	 *
	 * @param {HTMLFormElement} form - form containing the search input field
	 * @returns {Array} - post types
	 * @since 3.6.0
	 */
	function getPostTypesFromForm(form) {
		const data = new FormData(form);

		if (data.has('post_type')) {
			return data.getAll('post_type').slice(-1);
		}

		if (data.has('post_type[]')) {
			return data.getAll('post_type[]');
		}

		return [];
	}

	/**
	 * Calls the ajax request, and outputs the results.
	 * Called by the handleKeyup callback, debounced.
	 *
	 * @param {Node} input - search input field
	 */
	const fetchResults = async (input) => {
		// retrieves the PHP-genereated query to pass to ElasticSearch
		const queryJSON = getJsonQuery();

		if (queryJSON.error) {
			return;
		}

		const searchText = input.value;
		const placeholder = 'ep_autosuggest_placeholder';
		const postTypes = getPostTypesFromForm(input.form);

		if (searchText.length >= 2) {
			setFormIsLoading(true, input);

			let query = buildSearchQuery(searchText, placeholder, queryJSON);

			query = JSON.parse(query);

			if (postTypes.length > 0) {
				if (typeof query.post_filter.bool.must !== 'undefined') {
					query.post_filter.bool.must.push({
						terms: {
							'post_type.raw': postTypes,
						},
					});
				}
			}

			/**
			 * Filter the Elasticsearch query used for Autosuggest.
			 *
			 * @filter ep.Autosuggest.query
			 * @since 4.3.1
			 *
			 * @param {object} query Elasticsearch query.
			 * @param {string} searchText Search term.
			 * @param {Element} input Input element that triggered Autosuggest.
			 * @returns {object} Elasticsearch query.
			 */
			query = applyFilters('ep.Autosuggest.query', query, searchText, input);
			query = JSON.stringify(query);

			// fetch the results
			const response = await esSearch(query, searchText);

			if (response && response._shards && response._shards.successful > 0) {
				const hits = checkForOrderedPosts(response.hits.hits, searchText);

				if (hits.length === 0) {
					hideAutosuggestBox();
				} else {
					updateAutosuggestBox(hits, input);
				}
			} else {
				hideAutosuggestBox();
			}

			setFormIsLoading(false, input);
		} else if (searchText.length === 0) {
			hideAutosuggestBox();
		}
	};

	const debounceFetchResults = debounce(fetchResults, 200);

	/**
	 * Callback for keyup in Autosuggest container.
	 *
	 * Calls a debounced function to get the search results via
	 * ajax request.
	 *
	 * @param {event} event - keyup event
	 */
	const handleKeyup = (event) => {
		event.preventDefault();
		const { target, key, keyCode } = event;

		if (key === 'Escape' || key === 'Esc' || keyCode === 27) {
			hideAutosuggestBox();
			toggleInputAria(false, target);
			setInputActiveDescendant('', target);
			return;
		}

		if (keyCodes.includes(keyCode) && target.value !== '') {
			handleUpDown(event);
			return;
		}

		const input = event.target;
		debounceFetchResults(input);
	};

	/**
	 * Wrap an element with an autosuggest container.
	 *
	 * @param {Element} element Element to wrap.
	 * @returns {void}
	 */
	const wrapInAutosuggestContainer = (element) => {
		const epContainer = document.createElement('div');

		epContainer.classList.add('ep-autosuggest-container');

		element.insertAdjacentElement('afterend', epContainer);

		epContainer.appendChild(element);
	};

	/**
	 * Insert an autosuggest list after an element.
	 *
	 * @param {Element} previousElement Element to add the autosuggest list after.
	 * @returns {void}
	 */
	const insertAutosuggestElement = (previousElement) => {
		if (!autosuggestElement) {
			autosuggestElement = document.createElement('div');
			autosuggestElement.classList.add('ep-autosuggest');

			const autosuggestList = document.createElement('ul');

			autosuggestList.classList.add('autosuggest-list');
			autosuggestList.setAttribute('role', 'listbox');

			autosuggestElement.appendChild(autosuggestList);
		}

		let element = autosuggestElement.cloneNode(true);

		/**
		 * Filter the Autosuggest container element before it is inserted.
		 *
		 * @filter ep.Autosuggest.element
		 * @since 4.3.1
		 *
		 * @param {Element} element Autosuggest container element.
		 * @param {Element} previousElement Element the container will be inserted after.
		 * @returns {Element} Autosuggest container element.
		 */
		element = applyFilters('ep.Autosuggest.element', element, previousElement);

		previousElement.insertAdjacentElement('afterend', element);
	};

	/**
	 * Prepare an input for Autosuggest.
	 *
	 * @param {Element} input Input to prepare.
	 * @returns {void}
	 */
	const prepareInputForAutosuggest = (input) => {
		/**
		 * Skip facet widget search fields and instant results.
		 */
		const ignoredClasses = ['facet-search', 'ep-search-input'];
		if (ignoredClasses.some((className) => input.classList.contains(className))) {
			return;
		}

		/**
		 * Disable autocomplete.
		 */
		input.setAttribute('autocomplete', 'off');

		/**
		 * We know the markup of the Search block, so we don't need to add a
		 * wrapper.
		 */
		if (input.classList.contains('wp-block-search__input')) {
			input.form.classList.add('ep-autosuggest-container');
			insertAutosuggestElement(input.parentElement);
		} else {
			wrapInAutosuggestContainer(input);
			insertAutosuggestElement(input);
		}

		/**
		 * Dispatch an event announcing the input has moved.
		 */
		const event = new CustomEvent('elasticpress.input.moved');

		input.dispatchEvent(event);

		/**
		 * Listen for any events:
		 *
		 * keyup
		 * send them for a query to the Elasticsearch server
		 * handle up and down keys to move between results
		 *
		 * blur
		 * hide the autosuggest box
		 */
		input.addEventListener('keyup', handleKeyup);
		input.addEventListener('blur', function () {
			window.setTimeout(hideAutosuggestBox, 200);
		});
	};

	/**
	 * Find inputs within an element and prepare them for Autosuggest.
	 *
	 * @param {Element} element Element to find inputs within.
	 * @returns {void}
	 */
	const findAndPrepareInputsForAutosuggest = (element) => {
		const inputs = element.querySelectorAll(selectors);

		if (inputs) {
			Array.from(inputs).forEach(prepareInputForAutosuggest);
		}
	};

	/**
	 * Observe the document for new potential Autosuggest inputs, and add
	 * Autosuggest to any found inputs.
	 *
	 * @returns {void}
	 */
	const observeDocumentForInputs = () => {
		const target = document.body;
		const config = {
			subtree: true,
			childList: true,
		};

		const observer = new MutationObserver((mutations, observer) => {
			mutations.forEach((mutation) => {
				Array.from(mutation.addedNodes).forEach((node) => {
					if (node.nodeType !== Node.ELEMENT_NODE) {
						return;
					}

					/**
					 * Adding autosuggest to an input moves it in the DOM,
					 * which would trigger our observer, so we need to
					 * stop observing until it's been prepared.
					 */
					observer.disconnect();

					/**
					 * If the node is an input, prepare it for Autosuggest if
					 * it matches the selectors, otherwise search the node for
					 * inputs.
					 */
					if (node.tagName === 'INPUT') {
						if (node.matches(selectors)) {
							prepareInputForAutosuggest(node);
						}
					} else {
						findAndPrepareInputsForAutosuggest(node);
					}

					/**
					 * Resume observing.
					 */
					observer.observe(target, config);
				});
			});
		});

		observer.observe(target, config);
	};

	/**
	 * Add autosuggest to any inputs in the document.
	 */
	findAndPrepareInputsForAutosuggest(document.body);

	/**
	 * When the DOM is ready start observing for new inputs.
	 */
	domReady(observeDocumentForInputs);
}
