/**
 * WordPress dependencies.
 */
import { useCallback, useEffect, useRef, useState, WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { facets, paramPrefix } from '../config';
import { useInstantResults } from '../hooks';
import { getUrlWithParams } from '../lib';
import { getPostTypesFromForm } from '../utilities';
import Modal from './common/modal';
import SearchTermFacet from './facets/search-term-facet';
import PostTypeFacet from './facets/post-type-facet';
import PriceRangeFacet from './facets/price-range-facet';
import TaxonomyTermsFacet from './facets/taxonomy-terms-facet';
import Results from './modal/results';
import Sidebar from './modal/sidebar';
import SidebarToggle from './modal/sidebar-toggle';
import Toolbar from './modal/toolbar';
import ActiveConstraints from './tools/active-constraints';
import ClearConstraints from './tools/clear-constraints';
import Sort from './tools/sort';
/**
 * Instant Results Modal component.
 *
 * @param {object} props Component props.
 * @param {boolean} props.defaultIsOpen Is the model open by default?
 * @returns {WPElement} Element.
 */
export default ({ defaultIsOpen }) => {
	const { args, isLoading, search } = useInstantResults();

	/**
	 * State.
	 */
	const [isOpen, setIsOpen] = useState(defaultIsOpen);

	/**
	 * Refs.
	 */
	const inputRef = useRef();
	const isInitialRenderRef = useRef(true);

	/**
	 * Push state to history.
	 */
	const pushState = useCallback(() => {
		const url = getUrlWithParams(paramPrefix);

		window.history.pushState({}, document.title, url);
	}, []);

	/**
	 * Handle closing the modal.
	 */
	const closeModal = useCallback(() => {
		pushState();
		setIsOpen(false);

		if (inputRef.current) {
			inputRef.current.focus();
		}
	}, [pushState]);

	/**
	 * Handle closing the modal.
	 *
	 * @returns {void}
	 */
	const onClose = useCallback(() => {
		closeModal();
	}, [closeModal]);

	/**
	 * Handle popping state.
	 *
	 * If we are popping to a state with no data, close the modal.
	 *
	 * @returns {void}
	 */
	const onPopState = useCallback((event) => {
		const hasState = event.state && Object.keys(event.state).length > 0;

		if (!hasState) {
			setIsOpen(false);
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

			const { value } = inputRef.current;
			const post_type = getPostTypesFromForm(inputRef.current.form);

			search({ post_type, search: value });
		},
		[inputRef, search],
	);

	/**
	 * Open the modal if the args change, unless this is the initial render.
	 *
	 * @returns {void}
	 */
	const handleArgs = () => {
		if (isInitialRenderRef.current) {
			isInitialRenderRef.current = false;
			return;
		}

		setIsOpen(true);
	};

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

		window.addEventListener('popstate', onPopState);

		return () => {
			inputs.forEach((input) => {
				input.form.removeEventListener('submit', onSubmit);
			});

			window.removeEventListener('popstate', onPopState);
		};
	};

	/**
	 * Effects.
	 */
	useEffect(handleArgs, [args]);
	useEffect(handleEvents, [onPopState, onSubmit]);

	return (
		<Modal aria-label={__('Search results', 'elasticpress')} isOpen={isOpen} onClose={onClose}>
			<div className={`ep-search-page ${isLoading ? 'is-loading' : ''}`}>
				<div className="ep-search-page__header">
					<SearchTermFacet />

					<Toolbar>
						<ActiveConstraints />
						<ClearConstraints />
						<SidebarToggle />
					</Toolbar>
				</div>

				<div className="ep-search-page__body">
					<Sidebar>
						<Sort />
						{facets.map(({ label, name, postTypes, type }, index) => {
							const defaultIsOpen = index < 2;

							switch (type) {
								case 'post_type':
									return (
										<PostTypeFacet
											defaultIsOpen={defaultIsOpen}
											key={name}
											label={label}
										/>
									);
								case 'price_range':
									return (
										<PriceRangeFacet
											defaultIsOpen={defaultIsOpen}
											key={name}
											label={label}
										/>
									);
								case 'taxonomy':
									return (
										<TaxonomyTermsFacet
											defaultIsOpen={defaultIsOpen}
											key={name}
											label={label}
											name={name}
											postTypes={postTypes}
										/>
									);
								default:
									return null;
							}
						})}
					</Sidebar>

					<Results />
				</div>
			</div>
		</Modal>
	);
};
