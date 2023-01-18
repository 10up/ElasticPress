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
 * @param {WPElement} props.children Component children.
 * @param {string} props.paramPrefix Prefix used to set and parse URL parameters.
 * @returns {WPElement} Component.
 */
export const ApiSearchProvider = ({ apiEndpoint, apiHost, argsSchema, children, paramPrefix }) => {
	/**
	 * Get default args from the schema.
	 */
	const defaultArgsFromSchema = useMemo(() => {
		return getDefaultArgsFromSchema(argsSchema);
	}, [argsSchema]);

	/**
	 * Get any default args from the UR:.
	 */
	const defaultArgsFromUrl = useMemo(() => {
		if (!paramPrefix) {
			return {};
		}

		return getArgsFromUrlParams(argsSchema, paramPrefix);
	}, [argsSchema, paramPrefix]);

	/**
	 * Set up the reducer.
	 */
	const [state, dispatch] = useReducer(reducer, {
		aggregations: {},
		args: {
			...defaultArgsFromSchema,
			...defaultArgsFromUrl,
		},
		isLoading: false,
		isOn: Object.keys(defaultArgsFromUrl).length > 0,
		isPoppingState: false,
		searchResults: [],
		searchedTerm: '',
		totalResults: 0,
	});

	/**
	 * Set up fetch method.
	 */
	const fetchResults = useFetchResults(apiHost, apiEndpoint);

	/**
	 * Create state ref.
	 */
	const stateRef = useRef(state);

	stateRef.current = state;

	/**
	 * Clear facet contraints.
	 *
	 * @returns {void}
	 */
	const clearConstraints = useCallback(() => {
		dispatch({
			type: 'CLEAR_CONSTRAINTS',
			argsSchema,
		});
	}, [argsSchema]);

	/**
	 * Set search args.
	 *
	 * @param {object} args Search args.
	 * @returns {void}
	 */
	const search = useCallback((args) => {
		dispatch({
			type: 'SEARCH',
			args,
		});
	}, []);

	/**
	 * Reset search args with new search term.
	 *
	 * @param {string} searchTerm Search term.
	 * @returns {void}
	 */
	const searchFor = (searchTerm) => {
		dispatch({
			type: 'SEARCH_FOR',
			argsSchema,
			searchTerm,
		});
	};

	/**
	 * Set loading state.
	 *
	 * @param {boolean} isLoading Is loading?
	 * @returns {void}
	 */
	const setIsLoading = (isLoading) => {
		dispatch({
			type: 'SET_IS_LOADING',
			isLoading,
		});
	};

	/**
	 * Set search results based on an Elasticsearch response.
	 *
	 * @param {object} response Elasticsearch response.
	 * @returns {void}
	 */
	const setResults = (response) => {
		dispatch({
			type: 'SET_RESULTS',
			response,
		});
	};

	/**
	 * Load the next page of search results.
	 *
	 * @returns {void}
	 */
	const nextPage = () => {
		dispatch({
			type: 'NEXT_PAGE',
		});
	};

	/**
	 * Load the previous page of search results.
	 *
	 * @returns {void}
	 */
	const previousPage = () => {
		dispatch({
			type: 'PREVIOUS_PAGE',
		});
	};

	/**
	 * Set search args from popped history state.
	 *
	 * @param {object} args Search args.
	 */
	const popState = (args) => {
		dispatch({
			type: 'POP_STATE',
			args,
		});
	};

	/**
	 * Push args state to history.
	 *
	 * @returns {void}
	 */
	const pushState = useCallback(() => {
		if (typeof paramPrefix === 'undefined') {
			return;
		}

		const { args, isOn } = stateRef.current;
		const state = { ...args, isOn };

		if (window.history.state) {
			const params = isOn ? getUrlParamsFromArgs(args, argsSchema, paramPrefix) : null;
			const url = getUrlWithParams(paramPrefix, params);

			window.history.pushState(state, document.title, url);
		} else {
			window.history.replaceState(state, document.title, window.location.href);
		}
	}, [argsSchema, paramPrefix]);

	/**
	 * Close search.
	 *
	 * @returns {void}
	 */
	const turnOff = () => {
		dispatch({
			type: 'TURN_OFF',
		});
	};

	/**
	 * Handle popstate event.
	 *
	 * @param {Event} event popstate event.
	 */
	const onPopState = useCallback(
		(event) => {
			if (typeof paramPrefix === 'undefined') {
				return;
			}

			const hasState = event.state && Object.keys(event.state).length > 0;

			if (hasState) {
				popState(event.state);
			}
		},
		[paramPrefix],
	);

	/**
	 * Handle search.
	 *
	 * @returns {void}
	 */
	const onSearch = useCallback(async () => {
		const { args, isOn, isPoppingState } = stateRef.current;

		if (!isPoppingState) {
			pushState();
		}

		if (!isOn) {
			return;
		}

		const urlParams = getUrlParamsFromArgs(args, argsSchema);

		setIsLoading(true);

		const response = await fetchResults(urlParams);

		setResults(response);
		setIsLoading(false);
	}, [argsSchema, fetchResults, pushState]);

	/**
	 * Bind events to outside elements.
	 *
	 * @returns {Function} A cleanup function that unbinds the events.
	 */
	const handleInit = () => {
		window.addEventListener('popstate', onPopState);

		return () => {
			window.removeEventListener('popstate', onPopState);
		};
	};

	/**
	 * Perform a search.
	 *
	 * @returns {void}
	 */
	const handleSearch = () => {
		onSearch();
	};

	/**
	 * Effects.
	 */
	useEffect(handleInit, [argsSchema, onPopState, paramPrefix, search]);
	useEffect(handleSearch, [
		onSearch,
		state.args,
		state.args.orderby,
		state.args.order,
		state.args.offset,
		state.args.search,
	]);

	/**
	 * Provide state to context.
	 */
	const { aggregations, args, isLoading, isOn, searchResults, searchTerm, totalResults } =
		stateRef.current;

	// eslint-disable-next-line react/jsx-no-constructed-context-values
	const contextValue = {
		aggregations,
		args,
		clearConstraints,
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
