/**
 * WordPress dependencies.
 */
import { ReactElement, useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { _n, sprintf } from '@wordpress/i18n';

/**
 * Listbox component.
 *
 * @param {object} props Component props.
 * @param {Array} props.children Component children.
 * @param {string} props.id Element ID.
 * @param {boolean} props.isBusy Is the combobox busy?
 * @param {Function} props.onSelect Selection handler.
 * @returns {ReactElement} Rendered component.
 */
export default ({ children, id, isBusy, onSelect, ...props }) => {
	/**
	 * State.
	 */
	const [isExpanded, setIsExpanded] = useState(false);
	const [selected, setSelected] = useState(false);

	/**
	 * Active descendant ID.
	 *
	 * @returns {string|null} Active descendant id.
	 */
	const activeDescendant = useMemo(() => {
		return children[selected] ? children[selected].props.id : null;
	}, [children, selected]);

	/**
	 * Input description.
	 *
	 * @returns {string} Description.
	 */
	const description = useMemo(() => {
		return children.length && !isBusy
			? sprintf(
					// translators: %d: Number of options in the list.
					_n(
						'%d suggestion available, use the up and down keys to browse and the enter key to open',
						'%d suggestions available, use the up and down keys to browse and the enter key to open',
						children.length,
						'elasticpress',
					),
					children.length,
			  )
			: '';
	}, [children, isBusy]);

	/**
	 * Current value.
	 *
	 * @returns {*} Current value.
	 */
	const value = useMemo(() => {
		return selected !== false && children[selected] ? children[selected].props.value : false;
	}, [children, selected]);

	/**
	 * Get the index of the first option.
	 *
	 * @returns {false|number} Index of the first option.
	 */
	const getFirstIndex = useCallback(() => {
		return children.length ? 0 : false;
	}, [children]);

	/**
	 * Get the index of the last option.
	 *
	 * @returns {false|number} Index of the last option.
	 */
	const getLastIndex = useCallback(() => {
		return children.length ? children.length - 1 : false;
	}, [children]);

	/**
	 * Get the index of the next option.
	 *
	 * @returns {false|number} Index of the next option.
	 */
	const getNextIndex = useCallback(() => {
		const firstIndex = getFirstIndex(children);

		if (selected === false) {
			return firstIndex;
		}

		const nextIndex = selected + 1;

		return children?.[nextIndex] ? nextIndex : firstIndex;
	}, [children, getFirstIndex, selected]);

	/**
	 * Get the index of the previous option.
	 *
	 * @returns {false|number} Index of the previous option.
	 */
	const getPreviousIndex = useCallback(() => {
		const lastIndex = getLastIndex(children);

		if (selected === false) {
			return lastIndex;
		}

		const previousIndex = selected - 1;

		return children?.[previousIndex] ? previousIndex : lastIndex;
	}, [children, getLastIndex, selected]);

	/**
	 * Callback for input focus event.
	 *
	 * @param {Event} event Focus event.
	 * @returns {void}
	 */
	const onFocus = useCallback(() => {
		setIsExpanded(!!children.length);
	}, [children]);

	/**
	 * Callback for parent focusout event.
	 *
	 * Monitors changes in focus and expands the listbox if focus is within
	 * the parent container and there are results.
	 *
	 * @param {Event} event Focusout event.
	 * @returns {void}
	 */
	const onBlur = useCallback((event) => {
		if (event.currentTarget.contains(event.relatedTarget)) {
			return;
		}

		setSelected(false);
		setIsExpanded(false);
	}, []);

	/**
	 * Handle key presses.
	 *
	 * @param {Event} event Key down event.
	 * @returns {void}
	 */
	const onKeyDown = useCallback(
		(event) => {
			const nextIndex = getNextIndex(selected, children);
			const previousIndex = getPreviousIndex(selected, children);

			switch (event.key) {
				case 'ArrowDown':
					event.preventDefault();
					setSelected(nextIndex);
					break;
				case 'ArrowUp':
					event.preventDefault();
					setSelected(previousIndex);
					break;
				case 'Enter':
					if (value !== false) {
						event.preventDefault();
						onSelect(value, event);
					}
					break;
				case 'Escape':
					if (isExpanded) {
						event.preventDefault();
						setSelected(false);
						setIsExpanded(false);
					}
					break;
				default:
					break;
			}
		},
		[children, getNextIndex, getPreviousIndex, isExpanded, onSelect, selected, value],
	);

	/**
	 * Handle new options being available for the list.
	 *
	 * @returns {void}
	 */
	const handleChildren = () => {
		setSelected(false);
		setIsExpanded(!!children.length);
	};

	/**
	 * Handle changes to selected option.
	 *
	 * @returns {void}
	 */
	const handleSelected = () => {
		if (selected !== false) {
			setIsExpanded(true);
		}
	};

	/**
	 * Effects.
	 */
	useEffect(handleChildren, [children]);
	useEffect(handleSelected, [selected]);

	/**
	 * Render.
	 */
	return (
		<div className="ep-combobox" onBlur={onBlur}>
			<input
				aria-activedescendant={activeDescendant}
				aria-autocomplete="list"
				aria-controls={id}
				aria-describedby={`${id}-description`}
				aria-expanded={!isBusy && isExpanded}
				autoComplete="off"
				className="ep-combobox__input"
				onFocus={onFocus}
				onKeyDown={onKeyDown}
				role="combobox"
				{...props}
			/>
			<div id={`${id}-description`} className="screen-reader-text">
				{description}
			</div>
			<ul id={id} role="listbox" className="ep-combobox__list">
				{children.map((child, index) => {
					const { id, value } = child.props;

					return (
						// eslint-disable-next-line jsx-a11y/click-events-have-key-events
						<li
							aria-selected={selected === index}
							className="ep-combobox__option"
							key={id}
							onClick={(event) => {
								onSelect(value, event);
							}}
							role="option"
							tabIndex="-1"
						>
							{child}
						</li>
					);
				})}
			</ul>
		</div>
	);
};
