/**
 * WordPress dependencies.
 */
import { useCallback, useEffect, useMemo, useRef, useState, WPElement } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { argsSchema, matchType, paramPrefix } from './config';
import Context from './context';
import { clearFacetsFromArgs, getUrlParamsFromArgs, getUrlWithParams } from './functions';
import { useGetResults } from './hooks';

/**
 * Default search arguments.
 */
const initialArgs = {
	highlight: '',
	offset: 0,
	orderby: 'relevance',
	order: 'desc',
	per_page: 6,
	relation: matchType === 'all' ? 'and' : 'or',
	search: '',
};

/**
 * Instant Results provider component.
 *
 * @param {object} props Component props.
 * @param {WPElement} props.children Component children.
 * @param {object} props.defaultArgs Default args.
 * @returns {WPElement} Component.
 */
export default ({ defaultArgs, children }) => {
	const getResults = useGetResults();

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
	 */
	const clearFacets = useCallback(() => {
		const clearedArgs = clearFacetsFromArgs(argsRef.current);

		setArgs({ ...clearedArgs });
	}, []);

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
	}, []);

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
	}, [getResults, pushState]);

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

	const contextValue = useMemo(
		() => ({
			aggregations,
			args,
			clearFacets,
			isLoading,
			newSearch,
			nextPage,
			previousPage,
			search,
			searchResults,
			searchedTerm,
			totalResults,
		}),
		[
			aggregations,
			args,
			clearFacets,
			isLoading,
			newSearch,
			nextPage,
			previousPage,
			search,
			searchResults,
			searchedTerm,
			totalResults,
		],
	);

	return <Context.Provider value={contextValue}>{children}</Context.Provider>;
};
