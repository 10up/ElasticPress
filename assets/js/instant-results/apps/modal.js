/**
 * WordPress dependencies.
 */
import { useCallback, useEffect, useRef, WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { useApiSearch } from '../../api-search';
import { getPostTypesFromForm } from '../utilities';
import Modal from '../components/common/modal';
import Layout from '../components/layout';

/**
 * Instant Results Modal component.
 *
 * @returns {WPElement} Element.
 */
export default () => {
	const { turnOff, isOn, search } = useApiSearch();

	/**
	 * Refs.
	 */
	const inputRef = useRef();

	/**
	 * Handle closing the modal.
	 *
	 * @returns {void}
	 */
	const onClose = useCallback(() => {
		turnOff();

		if (inputRef.current) {
			inputRef.current.focus();
		}
	}, [turnOff]);

	/**
	 * Handle submitting the search form.
	 *
	 * @param {Event} event Submit event.
	 */
	const onSubmit = useCallback(
		(event) => {
			event.preventDefault();

			inputRef.current = event.target.s;

			/**
			 * Don't open the modal if an autosuggest suggestion is selected.
			 */
			const activeDescendant = inputRef.current.getAttribute('aria-activedescendant');

			if (activeDescendant) {
				return;
			}

			const { value } = inputRef.current;
			const post_type = getPostTypesFromForm(inputRef.current.form);

			search({ post_type, search: value });
		},
		[inputRef, search],
	);

	/**
	 * Bind events.
	 *
	 * @returns {Function} A cleanup function that unbinds the events.
	 */
	const handleEvents = () => {
		const inputs = document.querySelectorAll('form input[name="s"]');

		inputs.forEach((input) => {
			input.form.addEventListener('submit', onSubmit);
		});

		window.epInstantResults.openModal = search;

		return () => {
			inputs.forEach((input) => {
				input.form.removeEventListener('submit', onSubmit);
			});

			delete window.epInstantResults.openModal;
		};
	};

	/**
	 * Effects.
	 */
	useEffect(handleEvents, [onSubmit, search]);

	return (
		<Modal aria-label={__('Search results', 'elasticpress')} isOpen={isOn} onClose={onClose}>
			<Layout />
		</Modal>
	);
};
