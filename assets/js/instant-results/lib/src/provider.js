/**
 * WordPress dependencies.
 */
import { useCallback, useEffect, useReducer, useRef, WPElement } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { getUrlParamsFromArgs, getUrlWithParams } from './utilities';
import Context from './context';
import { useFetchResults } from './hooks';
import reducer from './reducer';

/**
 * Instant Results provider component.
 *
 * @param {object} props Component props.
 * @param {string} props.apiEndpoint API endpoint.
 * @param {string} props.apiHost API Host.
 * @param {object} props.argsSchema Schema describing supported args.
 * @param {WPElement} props.children Component children.
 * @param {string} props.currencyCode Currency code.
 * @param {object} props.defaultArgs Default search args.
 * @param {string} props.paramPrefix Prefix used to set and parse URL parameters.
 * @returns {WPElement} Component.
 */
export default ({
	apiEndpoint,
	apiHost,
	argsSchema,
	children,
	currencyCode,
	defaultArgs,
	paramPrefix,
}) => {
	/**
	 * Default args as defined by the schema.
	 */
	const schemaArgs = Object.entries(argsSchema).reduce((args, [arg, schema]) => {
		const hasDefault = Object.hasOwnProperty.call(schema, 'default');

		if (hasDefault) {
			args[arg] = schema.default;
		}

		return args;
	}, {});

	/**
	 * The initial state, including any default args passed to the component.
	 */
	const initialState = {
		aggregations: {},
		args: { ...schemaArgs, ...defaultArgs },
		isLoading: false,
		isPoppingState: false,
		searchResults: [],
		searchedTerm: '',
		totalResults: 0,
	};

	/**
	 * Set up the reducer.
	 */
	const [state, dispatch] = useReducer(reducer, initialState);

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
	const clearConstraints = () => {
		dispatch({
			type: 'CLEAR_CONSTRAINTS',
			argsSchema,
		});
	};

	/**
	 * Set search args.
	 *
	 * @param {object} args Search args.
	 * @returns {void}
	 */
	const search = (args) => {
		dispatch({
			type: 'SEARCH',
			args,
			argsSchema,
		});
	};

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
		const { args } = stateRef.current;

		if (window.history.state) {
			const params = getUrlParamsFromArgs(args, argsSchema, paramPrefix);
			const url = getUrlWithParams(paramPrefix, params);

			window.history.pushState(args, document.title, url);
		} else {
			window.history.replaceState(args, document.title, window.location.href);
		}
	}, [argsSchema, paramPrefix]);

	/**
	 * Handle popstate event.
	 *
	 * @param {Event} event popstate event.
	 */
	const onPopState = useCallback((event) => {
		const hasState = event.state && Object.keys(event.state).length > 0;

		if (hasState) {
			popState(event.state);
		}
	}, []);

	/**
	 * Handle search.
	 *
	 * @returns {void}
	 */
	const onSearch = useCallback(async () => {
		const { args, isPoppingState } = stateRef.current;

		if (!isPoppingState) {
			pushState();
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
	useEffect(handleInit, [onPopState]);
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
	const { aggregations, args, isLoading, searchResults, searchTerm, totalResults } =
		stateRef.current;

	// eslint-disable-next-line react/jsx-no-constructed-context-values
	const contextValue = {
		aggregations,
		clearConstraints,
		currencyCode,
		args,
		isLoading,
		searchResults,
		searchTerm,
		search,
		searchFor,
		setIsLoading,
		setResults,
		nextPage,
		previousPage,
		totalResults,
	};

	return <Context.Provider value={contextValue}>{children}</Context.Provider>;
};
