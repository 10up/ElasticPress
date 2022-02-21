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
import { useDebounce, useGetResults } from './hooks';
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
	const openModal = useCallback(() => {
		dispatch({ type: 'OPEN_MODAL' });
	}, []);

	/**
	 * Close the modal.
	 */
	const closeModal = useCallback(() => {
		dispatch({ type: 'CLOSE_MODAL' });
		dispatch({ type: 'CLEAR_FILTERS' });
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
	 * Debounced search function.
	 */
	const doSearchDebounced = useDebounce(doSearch, 250);

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
	 * Handle submitting the search form.
	 *
	 * @param {Event} event Input event.
	 */
	const onSubmit = useCallback(
		(event) => {
			event.preventDefault();

			inputRef.current = event.target.s;

			const searchTerm = inputRef.current.value;
			const postTypes = getPostTypesFromForm(inputRef.current.form);

			dispatch({ type: 'SET_SEARCH_TERM', payload: searchTerm });
			dispatch({ type: 'APPLY_FILTERS', payload: { post_type: postTypes } });

			openModal();
		},
		[openModal],
	);

	/**
	 * Handle changes to search parameters.
	 */
	const handleChanges = () => {
		const {
			args: { search },
			isOpen,
			searchedTerm,
		} = stateRef.current;

		if (!isOpen) {
			return;
		}

		if (search !== searchedTerm) {
			doSearchDebounced();
		} else {
			doSearch();
		}
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

		return () => {
			inputs.forEach((input) => {
				input.form.removeEventListener('submit', onSubmit);
			});

			modal.ownerDocument.body.removeEventListener('keydown', onEscape);
		};
	};

	/**
	 * Effects.
	 */
	useEffect(handleEvents, [onEscape, onSubmit]);
	useEffect(handleChanges, [
		doSearch,
		doSearchDebounced,
		state.args,
		state.args.orderby,
		state.args.order,
		state.args.offset,
		state.args.search,
		state.isOpen,
		state.filters,
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
