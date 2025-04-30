import { createRoot } from '@wordpress/element';
import { ApiSearchProvider } from '../api-search';
import { apiEndpoint, apiHost, argsSchema, paramPrefix, requestIdBase } from './src/config';
import { AutosuggestContext } from './src/context';
import AutosuggestUI from './components/AutosuggestUI';
import SuggestionItem from './components/SuggestionItem';
import SuggestionList from './components/SuggestionList';

/**
 * Mounts an Autosuggest component on a search input element.
 *
 * @param {HTMLInputElement} input - The search input element to attach the dropdown to.
 * @param {object} apiConfig - Configuration for the search API.
 * @param {string} apiConfig.apiEndpoint - Path of the API endpoint.
 * @param {string} apiConfig.apiHost - Hostname or base URL of the API.
 * @param {object} apiConfig.argsSchema - Schema defining allowed query arguments.
 * @param {string} apiConfig.paramPrefix - Prefix to use for query parameters.
 * @param {string} apiConfig.requestIdBase - Base string for generating request IDs.
 * @param {object} [contextValue={}] - Optional context value passed into the Autosuggest context.
 *
 * @example
 * const input = document.querySelector('#search-input');
 * mountAutosuggestOnInput(input, {
 *   apiEndpoint: '/wp/v2/search',
 *   apiHost: 'https://example.com',
 *   argsSchema: { term: 'string', per_page: 'number' },
 *   paramPrefix: 's',
 *   requestIdBase: 'autosuggest-'
 * });
 */
function mountAutosuggestOnInput(input, apiConfig, contextValue = {}) {
	if (input.dataset.epAutosuggestMounted) return;
	input.dataset.epAutosuggestMounted = '1';

	const container = document.createElement('div');
	container.className = 'ep-autosuggest-dropdown-container';
	// Insert after the input
	input.parentNode.insertBefore(container, input.nextSibling);

	// Render the dropdown container
	const render = () => {
		createRoot(container).render(
			<ApiSearchProvider
				apiEndpoint={apiEndpoint}
				apiHost={apiHost}
				argsSchema={argsSchema}
				paramPrefix={paramPrefix}
				requestIdBase={requestIdBase}
			>
				<AutosuggestContext.Provider value={contextValue}>
					<AutosuggestUI
						inputEl={input}
						placeholder={input.placeholder}
						ariaLabel={input.getAttribute('aria-label')}
					/>
				</AutosuggestContext.Provider>
			</ApiSearchProvider>,
		);
	};

	// Only render on input/focus, not on page load
	input.addEventListener('input', render);
	input.addEventListener('focus', render);
}

/**
 * Initializes autosuggest on all matching search inputs and observes for dynamically added ones.
 *
 * @param {object} [apiConfig={}] - Configuration passed through to `mountAutosuggestOnInput`.
 * @param {string} [apiConfig.apiEndpoint] - API endpoint path for search.
 * @param {string} [apiConfig.apiHost] - Host or base URL for API requests.
 * @param {object} [apiConfig.argsSchema] - Schema defining allowed query arguments.
 * @param {string} [apiConfig.paramPrefix] - Prefix to use for query parameters.
 * @param {string} [apiConfig.requestIdBase] - Base string for generating request IDs.
 * @returns {MutationObserver} The observer instance used to watch for new search inputs.
 *
 * @example
 * // Start autosuggest on current and future search fields
 * const observer = initialize({
 *   apiEndpoint: '/wp/v2/search',
 *   apiHost: 'https://example.com',
 *   argsSchema: { term: 'string' },
 *   paramPrefix: 's',
 *   requestIdBase: 'autosuggest-',
 * });
 *
 * // Later, to stop observing:
 * observer.disconnect();
 */

function initialize(apiConfig = {}) {
	// Find and mount on existing search inputs
	document
		.querySelectorAll('input[type="search"], .ep-autosuggest, .search-field')
		.forEach((input) => {
			if (input.tagName === 'INPUT' && input.type === 'search') {
				mountAutosuggestOnInput(input, apiConfig);
			} else if (
				input.classList &&
				(input.classList.contains('ep-autosuggest') ||
					input.classList.contains('search-field')) &&
				input.tagName === 'INPUT'
			) {
				mountAutosuggestOnInput(input, apiConfig);
			}
		});

	// Observe for dynamically added search fields
	const observer = new MutationObserver((mutations) => {
		mutations.forEach((mutation) => {
			mutation.addedNodes.forEach((node) => {
				if (
					node.nodeType === 1 &&
					node.tagName === 'INPUT' &&
					(node.type === 'search' ||
						node.classList.contains('ep-autosuggest') ||
						node.classList.contains('search-field'))
				) {
					mountAutosuggestOnInput(node, apiConfig);
				}
			});
		});
	});

	observer.observe(document.body, { childList: true, subtree: true });

	return observer; // Return for potential cleanup
}

// Initialize with default config from window globals
const apiConfig = {
	apiEndpoint: window.epasApiEndpoint || '/api/v1/search/posts/my-index',
	apiHost: window.epasApiHost || '',
	argsSchema: window.epasArgsSchema || {},
	paramPrefix: window.epasParamPrefix || '',
	requestIdBase: window.epasRequestIdBase || '',
};

// Auto-initialize if not in a module environment
if (typeof window !== 'undefined') {
	initialize(apiConfig);

	// Expose for testing or external use
	window.EPAutosuggest = {
		initialize,
		AutosuggestUI,
		AutosuggestContext,
		mountAutosuggestOnInput,
		SuggestionItem,
		SuggestionList,
	};
}

export {
	AutosuggestUI,
	AutosuggestContext,
	mountAutosuggestOnInput,
	SuggestionItem,
	SuggestionList,
	initialize,
};

export default {
	initialize,
	mountAutosuggestOnInput,
};
