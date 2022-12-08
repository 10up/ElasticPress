/**
 * WordPress dependencies.
 */
import { useCallback, useEffect, useReducer, useRef, WPElement } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { getArgsFromUrlParams, getUrlParamsFromArgs, getUrlWithParams } from './utilities';
import Context from './src/context';
import { useFetchResults } from './src/hooks';
import reducer from './src/reducer';

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
		args: { ...schemaArgs },
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

		/**
		 * If a parameter prefix is defined perform an initial search with the
		 * URL paramters.
		 */
		if (typeof paramPrefix !== 'undefined') {
			const urlParams = new URLSearchParams(window.location.search);
			const urlArgs = getArgsFromUrlParams(urlParams, argsSchema, paramPrefix, false);

			if (Object.keys(urlArgs).length > 0) {
				search(urlArgs);
			}
		}

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
	const { aggregations, args, isLoading, searchResults, searchTerm, totalResults } =
		stateRef.current;

	// eslint-disable-next-line react/jsx-no-constructed-context-values
	const contextValue = {
		aggregations,
		args,
		clearConstraints,
		getUrlParamsFromArgs,
		getUrlWithParams,
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
