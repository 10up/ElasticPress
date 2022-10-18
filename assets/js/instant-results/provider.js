/**
 * WordPress dependencies.
 */
import { useCallback, useEffect, useMemo, useRef, useState, WPElement } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import Context from './context';
import { getUrlParamsFromArgs, getUrlWithParams } from './functions';
import { useGetResults } from './hooks';

/**
 * Instant Results provider component.
 *
 * @param {object} props Component props.
 * @param {object} props.argsSchema Schema describing supported args.
 * @param {WPElement} props.children Component children.
 * @param {string} props.currencyCode Currency code.
 * @param {object} props.defaultArgs Default args.
 * @param {string} props.locale BCP 47 language tag.
 * @param {'all'|'any'} props.matchType How to match filters.
 * @param {string} props.paramPrefix Prefix used to set and parse URL parameters.
 * @param {object} props.postTypeLabels Post type labels indexed by post type slug.
 * @returns {WPElement} Component.
 */
export default ({
	argsSchema,
	defaultArgs,
	children,
	currencyCode,
	locale,
	matchType,
	paramPrefix,
	postTypeLabels,
}) => {
	const getResults = useGetResults();

	/**
	 * Default search arguments, derived from schema.
	 */
	const initialArgs = useMemo(
		() =>
			Object.entries(argsSchema).reduce((initialArgs, [arg, schema]) => {
				if (schema.default) {
					initialArgs[arg] = schema.default;
				}

				return initialArgs;
			}, {}),
		[argsSchema],
	);

	/**
	 * State.
	 */
	const [aggregations, setAggregations] = useState({});
	const [args, setArgs] = useState({ ...initialArgs, ...defaultArgs });
	const [isLoading, setIsLoading] = useState(false);
	const [isPoppingState, setIsPoppingState] = useState(Object.keys(defaultArgs).length === 0);
	const [searchResults, setSearchResults] = useState([]);
	const [searchedTerm, setSearchedTerm] = useState('');
	const [totalResults, setTotalResults] = useState(0);

	/**
	 * Refs.
	 */
	const argsRef = useRef(args);
	const isPoppingStateRef = useRef(isPoppingState);

	argsRef.current = args;
	isPoppingStateRef.current = isPoppingState;

	/**
	 * Update search args.
	 *
	 * @param {object} payload Search parameters.
	 * @returns {void}
	 */
	const search = useCallback((payload) => {
		setArgs({ ...argsRef.current, ...payload });
	}, []);

	/**
	 * Reset facets and pagination and search for a new search term.
	 *
	 * @returns {void}
	 */
	const newSearch = useCallback((payload) => {
		const clearedArgs = clearFacetsFromArgs(argsRef.current);

		setArgs({ ...clearedArgs, search: payload, offset: 0 });
	}, []);

	/**
	 * Clear applied facet constraints.
	 *
	 * Args without default values can be cleared.
	 *
	 * @returns {void}
	 */
	const clearConstraints = useCallback(() => {
		const clearedArgs = { ...argsRef.current };

		Object.entries(argsSchema).forEach(([arg, schema]) => {
			/**
			 *
			 */
			const hasDefault = Object.hasOwnProperty.call(schema, 'default');

			if (!hasDefault) {
				delete clearedArgs[arg];
			}
		});

		setArgs({ ...clearedArgs });
	}, [argsSchema]);

	/**
	 * Set args to get the next page.
	 *
	 * @returns {void}
	 */
	const nextPage = useCallback(() => {
		const { offset, per_page } = argsRef.current;

		setArgs({ offset: offset + per_page });
	}, []);

	/**
	 * Set args to get the previous page.
	 *
	 * @returns {void}
	 */
	const previousPage = useCallback(() => {
		const { offset, per_page } = argsRef.current;

		setArgs({ offset: Math.max(offset - per_page, 0) });
	}, []);

	/**
	 * Push args state to history.
	 *
	 * @returns {void}
	 */
	const pushState = useCallback(() => {
		const state = { ...argsRef.current };

		if (window.history.state) {
			const params = getUrlParamsFromArgs(argsRef.current, argsSchema, paramPrefix);
			const url = getUrlWithParams(paramPrefix, params);

			window.history.pushState(state, document.title, url);
		} else {
			window.history.replaceState(state, document.title, window.location.href);
		}
	}, [argsSchema, paramPrefix]);

	/**
	 * Perform a search.
	 *
	 * @returns {void}
	 */
	const doSearch = useCallback(async () => {
		if (!isPoppingStateRef.current) {
			pushState();
		}

		const urlParams = getUrlParamsFromArgs(argsRef.current, argsSchema);

		setIsLoading(true);
		setIsPoppingState(false);

		const { hits, aggregations } = await getResults(urlParams);

		const results = hits.hits;
		const searchedTerm = argsRef.current.search;
		const totalResults = typeof hits.total === 'number' ? hits.total : hits.total.value;

		setAggregations(aggregations);
		setSearchResults(results);
		setSearchedTerm(searchedTerm);
		setTotalResults(totalResults);

		setIsLoading(false);
	}, [argsSchema, getResults, pushState]);

	/**
	 * Handle popstate event.
	 *
	 * @param {Event} event popstate event.
	 */
	const onPopState = useCallback(
		(event) => {
			const hasState = event.state && Object.keys(event.state).length > 0;

			if (hasState) {
				setIsPoppingState(true);
				search(event.state);
			}
		},
		[search],
	);

	/**
	 * Handle changes to search args.
	 *
	 * @returns {void}
	 */
	const handleArgs = () => {
		doSearch();
	};

	/**
	 * Bind events to outside elements.
	 *
	 * @returns {Function} A cleanup function that unbinds the events.
	 */
	const handleEvents = () => {
		window.addEventListener('popstate', onPopState);

		return () => {
			window.removeEventListener('popstate', onPopState);
		};
	};

	/**
	 * Effects.
	 */
	useEffect(handleArgs, [args, doSearch, pushState]);
	useEffect(handleEvents, [onPopState]);

	// eslint-disable-next-line react/jsx-no-constructed-context-values
	const contextValue = {
		aggregations,
		args,
		clearConstraints,
		currencyCode,
		isLoading,
		locale,
		matchType,
		newSearch,
		nextPage,
		paramPrefix,
		postTypeLabels,
		previousPage,
		search,
		searchResults,
		searchedTerm,
		totalResults,
	};

	return <Context.Provider value={contextValue}>{children}</Context.Provider>;
};
