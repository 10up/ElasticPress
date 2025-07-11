import { createRoot } from '@wordpress/element';
import { ApiSearchProvider } from '../api-search';
import { apiEndpoint, apiHost, argsSchema, paramPrefix, requestIdBase } from './config';
import AutosuggestUI from './components/AutosuggestUI';

/**
 * Mounts the Autosuggest component onto a given input element.
 *
 * @param {HTMLInputElement} inputElement - The input element to attach the Autosuggest to.
 * @param {object} apiConfig - Configuration for the API search.
 */
function mountAutosuggestOnInput(inputElement, apiConfig) {
	// Prevent re-mounting on the same element
	if (inputElement.dataset.epAutosuggestMounted) {
		return;
	}
	inputElement.dataset.epAutosuggestMounted = 'true';

	// Create a wrapper for the input and the dropdown
	const wrapper = document.createElement('div');
	wrapper.className = 'ep-autosuggest-wrapper';
	if (inputElement.parentNode) {
		inputElement.parentNode.replaceChild(wrapper, inputElement);
	}
	wrapper.appendChild(inputElement);

	// Create a container for the suggestions dropdown
	const dropdownContainer = document.createElement('div');
	dropdownContainer.className = 'ep-autosuggest-dropdown-container';
	wrapper.appendChild(dropdownContainer);

	// Render the AutosuggestUI component
	const root = createRoot(dropdownContainer);
	root.render(
		<ApiSearchProvider {...apiConfig} useUrlParams={false}>
			<AutosuggestUI inputEl={inputElement} />
		</ApiSearchProvider>,
	);
}

/**
 * Initializes the Autosuggest component on all designated input fields.
 *
 * @param {object} apiConfigOverrides - Optional overrides for the default API configuration.
 * @returns {MutationObserver} The MutationObserver instance watching for new elements.
 */
function initialize(apiConfigOverrides = {}) {
	const defaultConfig = {
		apiEndpoint: window.epasApiEndpoint || apiEndpoint,
		apiHost: window.epasApiHost || apiHost,
		argsSchema: window.epasArgsSchema || argsSchema,
		paramPrefix: window.epasParamPrefix || paramPrefix,
		requestIdBase: window.epasRequestIdBase || requestIdBase,
	};

	const finalApiConfig = { ...defaultConfig, ...apiConfigOverrides };

	const autosuggestSelector = 'input[type="search"], .ep-autosuggest, .search-field';

	// Function to mount Autosuggest on a single element
	const mountOnElement = (element) => {
		if (element.matches(autosuggestSelector) && element.tagName === 'INPUT') {
			mountAutosuggestOnInput(element, finalApiConfig);
		}
	};

	// Mount on existing elements
	document.querySelectorAll(autosuggestSelector).forEach(mountOnElement);

	// Observe for dynamically added elements
	const observer = new MutationObserver((mutationsList) => {
		for (const mutation of mutationsList) {
			if (mutation.type === 'childList') {
				mutation.addedNodes.forEach((node) => {
					if (node.nodeType === Node.ELEMENT_NODE) {
						// Check if the node itself matches
						if (node.matches(autosuggestSelector)) {
							mountOnElement(node);
						}
						// Check if any children of the node match (for complex DOM additions)
						node.querySelectorAll(autosuggestSelector).forEach(mountOnElement);
					}
				});
			}
		}
	});

	observer.observe(document.body, { childList: true, subtree: true });

	return observer;
}

// --- Main Execution ---
if (typeof window !== 'undefined') {
	// Initialize Autosuggest with configurations from the window object or defaults.
	const initialApiConfig = {
		apiEndpoint: window.epasApiEndpoint || apiEndpoint,
		apiHost: window.epasApiHost || apiHost,
		argsSchema: window.epasArgsSchema || argsSchema,
		paramPrefix: window.epasParamPrefix || paramPrefix,
		requestIdBase: window.epasRequestIdBase || requestIdBase,
	};
	initialize(initialApiConfig); //

	// Expose public API
	window.EPAutosuggest = { initialize, mountAutosuggestOnInput };

	// Dispatch a custom event to indicate that Autosuggest is loaded
	document.dispatchEvent(new CustomEvent('ep_autosuggest_loaded'));
}

export { initialize, mountAutosuggestOnInput };
export default { initialize, mountAutosuggestOnInput };
