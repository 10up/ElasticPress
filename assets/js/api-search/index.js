/**
 * WordPress dependencies.
 */
import {
	createContext,
	useCallback,
	useContext,
	useEffect,
	useMemo,
	useReducer,
	useRef,
	WPElement,
} from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { useFetchResults } from './src/hooks';
import reducer from './src/reducer';
import {
	getArgsFromUrlParams,
	getDefaultArgsFromSchema,
	getUrlParamsFromArgs,
	getUrlWithParams,
} from './src/utilities';

import { applyResultsFilter } from '../autosuggest-v2/hooks';

/**
 * Instant Results context.
 */
const Context = createContext();

/**
 * Instant Results provider component.
 *
 * @param {object} props Component props.
 * @param {string} props.apiEndpoint API endpoint.
 * @param {string} props.apiHost API Host.
 * @param {object} props.argsSchema Schema describing supported args.
 * @param {string} props.authorization Authorization header.
 * @param {string} props.requestIdBase Base of Requests IDs.
 * @param {WPElement} props.children Component children.
 * @param {string} props.paramPrefix Prefix used to set and parse URL parameters.
 * @param {Function} props.onAuthError Function to run when request authentication fails.
 * @param {boolean} props.useUrlParams Whether to use URL parameters for state (default true).
 * @returns {WPElement} Component.
 */
export const ApiSearchProvider = ({
	apiEndpoint,
	apiHost,
	authorization,
	requestIdBase,
	argsSchema,
	children,
	paramPrefix,
	onAuthError,
	useUrlParams = true,
}) => {
	/**
	 * Any default args from the URL.
	 */
	const defaultArgsFromUrl = useMemo(() => {
		if (!paramPrefix || !useUrlParams) {
			return {};
		}

		return getArgsFromUrlParams(argsSchema, paramPrefix);
	}, [argsSchema, paramPrefix, useUrlParams]);

	/**
	 * All default args including defaults from the schema.
	 */
	const defaultArgs = useMemo(() => {
		const defaultArgsFromSchema = getDefaultArgsFromSchema(argsSchema);

		return {
			...defaultArgsFromSchema,
			...defaultArgsFromUrl,
		};
	}, [argsSchema, defaultArgsFromUrl]);

	/**
	 * Whether the provider is "on" by default.
	 */
	const defaultIsOn = useMemo(() => {
		return useUrlParams ? Object.keys(defaultArgsFromUrl).length > 0 : false;
	}, [defaultArgsFromUrl, useUrlParams]);

	/**
	 * Set up fetch method.
	 */
	const fetchResults = useFetchResults(
		apiHost,
		apiEndpoint,
		authorization,
		onAuthError,
		requestIdBase,
	);

	/**
	 * Set up the reducer.
	 */
	const [state, dispatch] = useReducer(reducer, {
		aggregations: {},
		args: defaultArgs,
		argsSchema,
		isLoading: false,
		isOn: defaultIsOn,
		isPoppingState: false,
		searchResults: [],
		totalResults: 0,
		suggestedTerms: [],
		isFirstSearch: true,
		searchTerm: '',
	});

	/**
	 * Create state ref.
	 *
	 * Helps to avoid dependency hell.
	 */
	const stateRef = useRef(state);

	stateRef.current = state;

	/**
	 * Clear facet constraints.
	 *
	 * @returns {void}
	 */
	const clearConstraints = useCallback(() => {
		dispatch({ type: 'CLEAR_CONSTRAINTS' });
	}, []);

	/**
	 * Clear search results.
	 *
	 * @returns {void}
	 */
	const clearResults = useCallback(() => {
		dispatch({ type: 'CLEAR_RESULTS' });
	}, []);

	/**
	 * Update the search query args, triggering a search.
	 *
	 * @param {object} args Search args.
	 * @returns {void}
	 */
	const search = useCallback((args) => {
		dispatch({ type: 'SEARCH', args });
	}, []);

	/**
	 * Update the search term, triggering a search and resetting facet
	 * constraints.
	 *
	 * @param {string} searchTerm Search term.
	 * @param {object} filters Optional filters to apply
	 * @returns {void}
	 */
	const searchFor = (searchTerm) => {
		dispatch({ type: 'SEARCH_FOR', searchTerm });
	};

	/**
	 * Set loading state.
	 *
	 * @param {boolean} isLoading Is loading?
	 * @returns {void}
	 */
	const setIsLoading = (isLoading) => {
		dispatch({ type: 'SET_IS_LOADING', isLoading });
	};

	/**
	 * Set search results based on an Elasticsearch response.
	 *
	 * @param {object} response Elasticsearch response.
	 * @returns {void}
	 */
	const setResults = (response) => {
		dispatch({ type: 'SET_RESULTS', response });
	};

	/**
	 * Go to the next page of search results.
	 *
	 * @returns {void}
	 */
	const nextPage = () => {
		dispatch({ type: 'NEXT_PAGE' });
	};

	/**
	 * Go to the previous page of search results.
	 *
	 * @returns {void}
	 */
	const previousPage = () => {
		dispatch({ type: 'PREVIOUS_PAGE' });
	};

	/**
	 * Set search args from popped history state.
	 *
	 * @param {object} args Search args.
	 */
	const popState = (args) => {
		dispatch({ type: 'POP_STATE', args });
	};

	/**
	 * Turn off the provider.
	 *
	 * @returns {void}
	 */
	const turnOff = () => {
		dispatch({ type: 'TURN_OFF' });
	};

	/**
	 * Push search args to browser history.
	 *
	 * @returns {void}
	 */
	const pushState = useCallback(() => {
		if (typeof paramPrefix === 'undefined' || !useUrlParams) {
			return;
		}

		const { args, isOn } = stateRef.current;
		const state = { args, isOn };

		if (window.history.state) {
			if (isOn) {
				const params = getUrlParamsFromArgs(args, argsSchema, paramPrefix);
				const url = getUrlWithParams(paramPrefix, params);

				window.history.pushState(state, document.title, url);
			} else {
				const url = getUrlWithParams(paramPrefix);

				window.history.pushState(state, document.title, url);
			}
		} else {
			window.history.replaceState(state, document.title, window.location.href);
		}
	}, [argsSchema, paramPrefix, useUrlParams]);

	/**
	 * Handle popstate event.
	 *
	 * @param {Event} event popstate event.
	 */
	const onPopState = useCallback(
		(event) => {
			if (typeof paramPrefix === 'undefined' || !useUrlParams) {
				return;
			}

			const hasState = event.state && Object.keys(event.state).length > 0;

			if (hasState) {
				popState(event.state);
			}
		},
		[paramPrefix, useUrlParams],
	);

	/**
	 * Handle initialization.
	 *
	 * @returns {Function} A cleanup function.
	 */
	const handleInit = useCallback(() => {
		if (useUrlParams) {
			window.addEventListener('popstate', onPopState);
			return () => {
				window.removeEventListener('popstate', onPopState);
			};
		}
		return () => {};
	}, [onPopState, useUrlParams]);

	/**
	 * Handle a change to search args.
	 *
	 * @returns {void}
	 */
	const handleSearch = useCallback(() => {
		const handle = async () => {
			const { args, isOn, isPoppingState, searchTerm } = stateRef.current;

			if (!isPoppingState && useUrlParams) {
				pushState();
			}

			if (!isOn) {
				return;
			}

			const urlParams = getUrlParamsFromArgs(args, argsSchema);

			setIsLoading(true);

			try {
				const response = await fetchResults(urlParams);

				if (!response) {
					return;
				}

				// Apply filters to search results if hooks are available
				if (response.hits && response.hits.hits && !useUrlParams) {
					response.hits.hits = applyResultsFilter(response.hits.hits, searchTerm);
				}

				setResults(response);
			} catch (e) {
				const errorMessage = sprintf(
					/* translators: Error message */
					__('ElasticPress: Unable to fetch results. %s', 'elasticpress'),
					e.message,
				);

				console.error(errorMessage); // eslint-disable-line no-console
			}

			setIsLoading(false);
		};

		handle();
	}, [argsSchema, fetchResults, pushState, useUrlParams]);

	/**
	 * Effects.
	 */
	useEffect(handleInit, [handleInit]);
	useEffect(handleSearch, [
		handleSearch,
		state.args,
		state.args.orderby,
		state.args.order,
		state.args.offset,
		state.args.search,
	]);

	/**
	 * Provide state to context.
	 */
	const {
		aggregations,
		args,
		isLoading,
		isOn,
		searchResults,
		searchTerm,
		totalResults,
		suggestedTerms,
		isFirstSearch,
	} = stateRef.current;

	// eslint-disable-next-line react/jsx-no-constructed-context-values
	const contextValue = {
		aggregations,
		args,
		clearConstraints,
		clearResults,
		getUrlParamsFromArgs,
		getUrlWithParams,
		isLoading,
		isOn,
		searchResults,
		searchTerm,
		search,
		searchFor,
		setResults,
		nextPage,
		previousPage,
		totalResults,
		turnOff,
		suggestedTerms,
		isFirstSearch,
		useUrlParams,
	};

	return <Context.Provider value={contextValue}>{children}</Context.Provider>;
};

/**
 * Use the API Search context.
 *
 * @returns {object} API Search Context.
 */
export const useApiSearch = () => {
	return useContext(Context);
};
