/**
 * WordPress dependencies.
 */
import { SlotFillProvider } from '@wordpress/components';
import { render, useCallback, useEffect, useReducer, useRef, WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import Context from './context';
import { getPostTypesFromForm, getURLParamsFromState } from './functions';
import { useGetResults } from './hooks';
import { reducer, initialArg } from './reducer';
import Layout from './components/layout';
import Modal from './components/common/modal';

/**
 * component.
 *
 * @return {WPElement} Element.
 */
const App = () => {
	const getResults = useGetResults();
	const [state, dispatch] = useReducer(reducer, initialArg);
	const inputRef = useRef();
	const modalRef = useRef();
	const stateRef = useRef(state);

	stateRef.current = state;

	/**
	 * Close the modal.
	 */
	const closeModal = useCallback(() => {
		dispatch({ type: 'CLOSE_MODAL' });
		inputRef.current.focus();
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
	 * @param {Object} response Search results.
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
		const urlParams = getURLParamsFromState(stateRef.current);

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
			history.pushState(state, document.title);
		} else {
			history.replaceState(state, document.title);
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
	 * @param {Event} event Input event.
	 */
	const onSubmit = useCallback((event) => {
		event.preventDefault();

		inputRef.current = event.target.s;

		const search = inputRef.current.value;
		const post_type = getPostTypesFromForm(inputRef.current.form);

		dispatch({ type: 'APPLY_ARGS', payload: { search, post_type } });
	}, []);

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
	 * @return {Function} A cleanup function that unbinds the events.
	 */
	const handleEvents = () => {
		const inputs = document.querySelectorAll('form input[type="search"');
		const modal = modalRef.current;

		inputs.forEach((input) => {
			input.form.addEventListener('submit', onSubmit);
		});

		modal.ownerDocument.body.addEventListener('keydown', onEscape);
		modal.ownerDocument.defaultView.addEventListener('popstate', onPopState);

		return () => {
			inputs.forEach((input) => {
				input.form.removeEventListener('submit', onSubmit);
			});

			modal.ownerDocument.body.removeEventListener('keydown', onEscape);
			modal.ownerDocument.defaultView.removeEventListener('popstate', onPopState);
		};
	};

	/**
	 * Effects.
	 */
	useEffect(handleEvents, [onEscape, onPopState, onSubmit]);
	useEffect(handleChanges, [
		doSearch,
		pushState,
		state.args,
		state.args.orderby,
		state.args.order,
		state.args.offset,
		state.args.search,
		state.isOpen,
	]);

	return (
		<Context.Provider value={{ state, dispatch }}>
			<SlotFillProvider>
				<Modal
					aria-label={__('Search results', 'elasticpress')}
					isOpen={state.isOpen}
					onClose={closeModal}
					ref={modalRef}
				>
					<Layout />
				</Modal>
			</SlotFillProvider>
		</Context.Provider>
	);
};

render(<App />, document.getElementById('ep-instant-results'));
