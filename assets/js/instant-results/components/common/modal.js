/**
 * External dependencies.
 */
import FocusTrap from 'focus-trap-react';

/**
 * WordPress dependencies.
 */
import { useCallback, useEffect, useRef, WPElement } from '@wordpress/element';
import { closeSmall, Icon } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';

/**
 * Modal components.
 *
 * @param {object} props Component props.
 * @param {WPElement} props.children Component children.
 * @param {boolean} props.isOpen Whether the modal is open.
 * @param {Function} props.onClose Callback to run when modal is closed.
 * @returns {WPElement} React element.
 */
export default ({ children, isOpen, onClose, ...props }) => {
	/**
	 * Reference to close button element.
	 */
	const closeRef = useRef(null);

	/**
	 * Handle key down.
	 *
	 * @param {Event} event Keydown event.
	 */
	const onKeyDown = useCallback(
		(event) => {
			if (event.key === 'Escape' || event.key === 'Esc') {
				onClose();
			}
		},
		[onClose],
	);

	/**
	 * Handle binding events to outside DOM elements.
	 *
	 * @returns {Function} Clean up function that removes events.
	 */
	const handleEvents = () => {
		document.body.addEventListener('keydown', onKeyDown);

		return () => {
			document.body.removeEventListener('keydown', onKeyDown);
		};
	};

	/**
	 * Handle the model being opened or closed.
	 *
	 * Adds a class to the body element to allow controlling scrolling.
	 */
	const handleOpen = () => {
		if (isOpen) {
			document.body.classList.add('has-ep-search-modal');
			closeRef.current.focus();
		} else {
			document.body.classList.remove('has-ep-search-modal');
		}
	};

	useEffect(handleEvents, [onKeyDown]);
	useEffect(handleOpen, [isOpen]);

	return (
		<div
			aria-hidden={!isOpen}
			aria-modal="true"
			className="ep-search-modal"
			role="dialog"
			{...props}
		>
			{isOpen && (
				<FocusTrap focusTrapOptions={{ allowOutsideClick: true }}>
					<div className="ep-search-modal__content">
						<button
							className="ep-search-modal__close ep-search-reset-button ep-search-icon-button"
							type="button"
							onClick={onClose}
							ref={closeRef}
						>
							<Icon icon={closeSmall} />
							{__('Close', 'elasticpress')}
						</button>
						{children}
					</div>
				</FocusTrap>
			)}
		</div>
	);
};
