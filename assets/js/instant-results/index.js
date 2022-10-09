/**
 * WordPress dependencies.
 */
import {
	render,
	useCallback,
	useEffect,
	useMemo,
	useReducer,
	useRef,
	WPElement,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { argsSchema, paramPrefix } from './config';
import Context from './context';
import { getArgsFromUrlParams, getUrlParamsFromArgs, getPostTypesFromForm } from './functions';
import { useGetResults } from './hooks';
import { reducer, initialState } from './reducer';
import Layout from './components/layout';
import Modal from './components/common/modal';

/**
 * component.
 *
 * @returns {WPElement} Element.
 */
const App = () => {
	const getResults = useGetResults();
	const [state, dispatch] = useReducer(reducer, initialState);
	const inputRef = useRef();
	const modalRef = useRef();
	const stateRef = useRef(state);

	stateRef.current = state;

	/**
	 * Handle open action.
	 *
	 * @param {Event} event Input event.
	 */
	const openModal = useCallback((args) => {
		dispatch({ type: 'APPLY_ARGS', payload: args });
	}, []);

	/**
	 * Close the modal.
	 */
	const closeModal = useCallback(() => {
		dispatch({ type: 'CLOSE_MODAL' });

		if (inputRef.current) {
			inputRef.current.focus();
		}
	}, []);

	/**
	 * Start loading.
	 */
	const startLoading = useCallback(() => {
		dispatch({ type: 'START_LOADING' });
	}, []);

	/**
	 * Finish loading.
	 */
	const finishLoading = useCallback(() => {
		dispatch({ type: 'FINISH_LOADING' });
	}, []);

	/**
	 * Update search results.
	 *
	 * @param {object} response Search results.
	 */
	const updateResults = useCallback((response) => {
		if (response) {
			dispatch({ type: 'NEW_SEARCH_RESULTS', payload: response });
		}
	}, []);

	/**
	 * Perform a search.
	 */
	const doSearch = useCallback(async () => {
		const urlParams = getUrlParamsFromArgs(stateRef.current.args, argsSchema);

		startLoading();

		const response = await getResults(urlParams);

		updateResults(response);

		finishLoading();
	}, [finishLoading, getResults, startLoading, updateResults]);

	/**
	 * Push state to history.
	 */
	const pushState = useCallback(() => {
		const { history } = modalRef.current.ownerDocument.defaultView;
		const { args, isOpen, isPoppingState } = stateRef.current;

		if (isPoppingState) {
			return;
		}

		const state = JSON.stringify({ ...args, isOpen });

		if (history.state) {
			const params = getUrlParamsFromArgs(args, argsSchema, paramPrefix);
			const url = new URL(window.location.href);
			const keys = Array.from(url.searchParams.keys());

			for (const key of keys) {
				if (key.startsWith(paramPrefix)) {
					url.searchParams.delete(key);
				}
			}

			if (isOpen) {
				params.forEach((value, key) => {
					url.searchParams.set(key, value);
				});
			}

			history.pushState(state, document.title, url.toString());
		} else {
			history.replaceState(state, document.title, window.location.href);
		}
	}, []);

	/**
	 * Handle escape key press.
	 *
	 * @param {Event} event Key down event.
	 */
	const onEscape = useCallback(
		(event) => {
			if (event.key === 'Escape') {
				closeModal();
			}
		},
		[closeModal],
	);

	/**
	 * Handle popstate event.
	 *
	 * @param {Event} event popstate event.
	 */
	const onPopState = useCallback((event) => {
		if (event.state) {
			dispatch({ type: 'POP_STATE', payload: JSON.parse(event.state) });
		}
	}, []);

	/**
	 * Handle submitting the search form.
	 *
	 * @param {Event} event Submit event.
	 */
	const onSubmit = useCallback(
		(event) => {
			event.preventDefault();

			inputRef.current = event.target.s;

			const search = inputRef.current.value;
			const post_type = getPostTypesFromForm(inputRef.current.form);

			openModal({ post_type, search });
		},
		[openModal],
	);

	/**
	 * Handle changes to search parameters.
	 */
	const handleChanges = () => {
		const { isOpen } = stateRef.current;

		pushState();

		if (!isOpen) {
			return;
		}

		doSearch();
	};

	/**
	 * Bind events to outside elements.
	 *
	 * @returns {Function} A cleanup function that unbinds the events.
	 */
	const handleEvents = () => {
		const inputs = document.querySelectorAll('form input[name="s"]');
		const modal = modalRef.current;

		inputs.forEach((input) => {
			input.form.addEventListener('submit', onSubmit);
		});

		modal.ownerDocument.defaultView.addEventListener('popstate', onPopState);

		return () => {
			inputs.forEach((input) => {
				input.form.removeEventListener('submit', onSubmit);
			});

			modal.ownerDocument.defaultView.removeEventListener('popstate', onPopState);
		};
	};

	/**
	 * Initialize the Instant Results modal.
	 *
	 * @returns {void}
	 */
	const handleInit = () => {
		/**
		 * Automatically open the modal with pre-defined args if they are found
		 * in the URL.
		 */
		const urlParams = new URLSearchParams(window.location.search);
		const args = getArgsFromUrlParams(urlParams, argsSchema, paramPrefix, false);

		if (Object.keys(args).length > 0) {
			openModal(args);
		}

		/**
		 * Publicly expose certain methods for interacting with the modal.
		 */
		window.epInstantResults.openModal = openModal;
	};

	/**
	 * Effects.
	 */
	useEffect(handleInit, [openModal]);
	useEffect(handleEvents, [onEscape, onPopState, onSubmit]);
	useEffect(handleChanges, [
		doSearch,
		pushState,
		state.args,
		state.args.orderby,
		state.args.order,
		state.args.offset,
		state.args.search,
	]);

	/**
	 * Create context.
	 */
	const context = useMemo(() => ({ state, dispatch }), [state, dispatch]);

	return (
		<Context.Provider value={context}>
			<Modal
				aria-label={__('Search results', 'elasticpress')}
				isOpen={state.isOpen}
				onClose={closeModal}
				ref={modalRef}
			>
				<Layout />
			</Modal>
		</Context.Provider>
	);
};

render(<App />, document.getElementById('ep-instant-results'));
