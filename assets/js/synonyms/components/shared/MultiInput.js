import React, { useState, useRef, useEffect } from 'react';
import CreatableSelect from 'react-select/creatable';

/**
 * Synonyms editor component.
 *
 * @param {object} props Props.
 * @returns {React.FC}
 */
export default function MultiInput(props) {
	const { tokens, setTokens, onClear, initialFocus, clearOnEmpty } = props;
	const [inputValue, setInputValue] = useState('');
	const inputRef = useRef(null);

	/**
	 * Create option.
	 *
	 * @param {string} label Option label.
	 * @returns {object}
	 */
	const createOption = (label) => ({
		label,
		value: label,
	});

	/**
	 * Handle key down.
	 *
	 * @param {React.SyntheticEvent} event Keydown event.
	 */
	const handleKeyDown = (event) => {
		switch (event.key) {
			case ',':
			case 'Enter':
				if (!tokens || !inputValue.length) {
					event.preventDefault();
					break;
				}
				if (tokens.map(({ value }) => value).indexOf(inputValue.trim()) === -1) {
					setTokens([...tokens, createOption(inputValue)]);
				}
				setInputValue('');
				event.preventDefault();
				break;
			case 'Backspace':
				if (!inputValue && !tokens.length && onClear && clearOnEmpty) {
					onClear();
				}
				break;
			default:
		}
	};

	/**
	 * Handle change.
	 *
	 * @param {string} _value The value.
	 * @param {object} data   Data with the action.
	 */
	const handleChange = (_value, data) => {
		switch (data.action) {
			case 'remove-value':
				setTokens([...tokens.filter(({ value }) => value !== data.removedValue.value)]);
				break;
			case 'clear':
				if (onClear) {
					onClear();
					break;
				}
				setTokens([]);
				break;
			default:
				break;
		}
	};

	useEffect(() => {
		if (initialFocus) {
			inputRef.current.focus();
		}
	}, [inputRef, initialFocus]);

	return (
		<CreatableSelect
			{...props}
			isMulti
			components={{ DropdownIndicator: null }}
			inputValue={inputValue}
			isClearable
			menuIsOpen={false}
			onChange={handleChange}
			onInputChange={(val) => setInputValue(val)}
			onKeyDown={handleKeyDown}
			placeholder="Type a synonym and press enter..."
			value={tokens}
			ref={inputRef}
		/>
	);
}
