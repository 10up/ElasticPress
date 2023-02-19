/**
 * WordPress depdendencies.
 */
import { ReactElement, useCallback, useEffect, useRef, useState } from '@wordpress/element';

/**
 * Listbox component.
 *
 * @param {object} props Component props.
 * @param {Array} props.children Component children.
 * @param {string} props.id Element ID.
 * @param {Element} props.input Owning input element.
 * @param {string} props.label Element accessible label.
 * @param {Function} props.onSelect Selection handler.
 * @returns {ReactElement} Rendered component.
 */
export default ({ children, id, input, label, onSelect }) => {
	const [isExpanded, setIsExpanded] = useState(false);
	const [selectedIndex, setSelectedIndex] = useState(false);

	/**
	 * Use refs to keep track of variables we need in our callbacks without
	 * using them as dependencies.
	 *
	 * Using the original variables of dependencies creates a chain of
	 * dependencies that causes the initialization effect to run on every
	 * render, causing events to be repeatedly bound and unbound from the input
	 * element.
	 */
	const childrenRef = useRef(children);
	const selectedIndexRef = useRef(selectedIndex);

	childrenRef.current = children;
	selectedIndexRef.current = selectedIndex;

	/**
	 * Get the index of the next option.
	 *
	 * @param {number} selectedIndex Currently selected index.
	 * @param {Array} children Options.
	 * @returns {null|number} Index of the next option.
	 */
	const getNextIndex = useCallback((selectedIndex, children) => {
		if (selectedIndex === false) {
			return 0;
		}

		const nextIndex = selectedIndex + 1;

		return children?.[nextIndex] ? nextIndex : false;
	}, []);

	/**
	 * Get the index of the previous option.
	 *
	 * @param {number} selectedIndex Currently selected index.
	 * @param {Array} children Options.
	 * @returns {null|number} Index of the previous option.
	 */
	const getPreviousIndex = useCallback((selectedIndex, children) => {
		const lastIndex = children.length - 1;

		if (selectedIndex === false) {
			return lastIndex;
		}

		const previousIndex = selectedIndex - 1;

		return children?.[previousIndex] ? previousIndex : false;
	}, []);

	/**
	 * Callback for input focus event.
	 *
	 * @param {Event} event Focus event.
	 */
	const onFocus = useCallback(() => {
		setIsExpanded(!!childrenRef.current.length);
	}, []);

	/**
	 * Callback for parent focusout event.
	 *
	 * Monitors changes in focus and expands the listbox if focus is within
	 * the parent container and there are results.
	 *
	 * @param {Event} event Focusout event.
	 */
	const onFocusOut = useCallback((event) => {
		setIsExpanded(
			event.currentTarget.contains(event.relatedTarget) && !!childrenRef.current.length,
		);
	}, []);

	/**
	 * Handle key presses.
	 *
	 * @param {Event} event Key down event.
	 */
	const onKeyDown = useCallback(
		(event) => {
			const nextIndex = getNextIndex(selectedIndexRef.current, childrenRef.current);
			const previousIndex = getPreviousIndex(selectedIndexRef.current, childrenRef.current);

			switch (event.key) {
				case 'ArrowDown':
					event.preventDefault();
					setSelectedIndex(nextIndex);
					selectedIndexRef.current = nextIndex;
					break;
				case 'ArrowUp':
					event.preventDefault();
					setSelectedIndex(previousIndex);
					selectedIndexRef.current = previousIndex;
					break;
				case 'Enter':
					if (selectedIndexRef.current === false) {
						return;
					}

					event.preventDefault();

					onSelect(selectedIndexRef.current, event.metaKey);

					break;
				default:
					break;
			}
		},
		[getNextIndex, getPreviousIndex, onSelect],
	);

	/**
	 * Handle new options being available for the list.
	 */
	const handleChildren = () => {
		setSelectedIndex(false);
		setIsExpanded(!!children.length);
	};

	/**
	 * Handle changes to the expanded state.
	 */
	const handleExpanded = () => {
		input.setAttribute('aria-expanded', isExpanded);
	};

	/**
	 * Handle initialization.
	 *
	 * @returns {Function} Cleanup function.
	 */
	const handleInit = () => {
		input.setAttribute('aria-autocomplete', 'list');
		input.setAttribute('aria-haspopup', true);
		input.setAttribute('aria-owns', id);
		input.setAttribute('autocomplete', 'off');
		input.setAttribute('role', 'combobox');
		input.addEventListener('focus', onFocus);
		input.addEventListener('keydown', onKeyDown);
		input.parentElement.addEventListener('focusout', onFocusOut);

		return () => {
			input.removeAttribute('aria-autocomplete');
			input.removeAttribute('aria-haspopup');
			input.removeAttribute('aria-owns');
			input.removeAttribute('autocomplete');
			input.removeAttribute('role');
			input.removeEventListener('focus', onFocus);
			input.removeEventListener('keydown', onKeyDown);
			input.parentElement.removeEventListener('focusout', onFocusOut);
		};
	};

	/**
	 * Effects.
	 */
	useEffect(handleChildren, [children]);
	useEffect(handleExpanded, [input, isExpanded]);
	useEffect(handleInit, [id, input, onFocus, onFocusOut, onKeyDown]);

	return (
		<ul
			aria-hidden={!isExpanded}
			aria-label={label}
			id={id}
			role="listbox"
			className="ep-listbox"
			data-selected-index={selectedIndexRef.current}
		>
			{children.map((child, index) => (
				// eslint-disable-next-line jsx-a11y/click-events-have-key-events
				<li
					aria-selected={selectedIndexRef.current === index}
					className="ep-listbox__option"
					key={child.props.id}
					onClick={(event) => onSelect(index, event.metaKey)}
					onMouseEnter={() => {
						setSelectedIndex(index);
						selectedIndexRef.current = index;
					}}
					role="option"
				>
					{child}
				</li>
			))}
		</ul>
	);
};
