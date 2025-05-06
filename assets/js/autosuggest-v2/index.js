import { createRoot } from '@wordpress/element';
import { ApiSearchProvider } from '../api-search';
import { apiEndpoint, apiHost, argsSchema, paramPrefix, requestIdBase } from './src/config';
import { AutosuggestContext } from './src/context';
import AutosuggestUI from './components/AutosuggestUI';

function mountAutosuggestOnInput(input, apiConfig, contextValue = {}) {
	if (input.dataset.epAutosuggestMounted) return;
	input.dataset.epAutosuggestMounted = '1';

	// wrap input + dropdown in a single container
	const wrapper = document.createElement('div');
	wrapper.className = 'ep-autosuggest-wrapper';
	input.parentNode.replaceChild(wrapper, input);
	wrapper.appendChild(input);

	const dropdownContainer = document.createElement('div');
	dropdownContainer.className = 'ep-autosuggest-dropdown-container';
	wrapper.appendChild(dropdownContainer);

	// createRoot once
	const root = createRoot(dropdownContainer);
	root.render(
		<ApiSearchProvider {...apiConfig} useUrlParams={false}>
			<AutosuggestContext.Provider value={contextValue}>
				<AutosuggestUI inputEl={input} />
			</AutosuggestContext.Provider>
		</ApiSearchProvider>,
	);
}

function initialize(apiConfig = {}) {
	const selector = 'input[type="search"], .ep-autosuggest, .search-field';
	const mountAll = (el) => {
		if (el.tagName === 'INPUT') mountAutosuggestOnInput(el, apiConfig);
	};

	document.querySelectorAll(selector).forEach(mountAll);

	const obs = new MutationObserver((ms) => {
		ms.forEach((m) =>
			m.addedNodes.forEach((node) => {
				if (node.matches?.(selector)) mountAll(node);
			}),
		);
	});
	obs.observe(document.body, { childList: true, subtree: true });
	return obs;
}

// auto-init
if (typeof window !== 'undefined') {
	const cfg = {
		apiEndpoint: window.epasApiEndpoint || apiEndpoint,
		apiHost: window.epasApiHost || apiHost,
		argsSchema: window.epasArgsSchema || argsSchema,
		paramPrefix: window.epasParamPrefix || paramPrefix,
		requestIdBase: window.epasRequestIdBase || requestIdBase,
	};
	initialize(cfg);
	window.EPAutosuggest = { initialize, mountAutosuggestOnInput };
	document.dispatchEvent(new CustomEvent('ep_autosuggest_loaded'));
}

export { initialize, mountAutosuggestOnInput };
export default { initialize, mountAutosuggestOnInput };
