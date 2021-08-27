import React, { useState } from 'react';
import CreatableSelect from 'react-select/creatable';

/**
 * Synonyms editor component.
 *
 * @param {Object} props Props.
 * @return {React.FC} MultiInput component
 */
const MultiInput = (props) => {
	const { tokens, setTokens } = props;
	const [inputValue, setInputValue] = useState('');

	/**
	 * Create option.
	 *
	 * @param {string} label Option label.
	 * @return {Object} Option object
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
			default:
		}
	};

	/**
	 * Handle change.
	 *
	 * @param {string} _value The value.
	 * @param {Object} data   Data with the action.
	 */
	const handleChange = (_value, data) => {
		switch (data.action) {
			case 'remove-value':
				setTokens([...tokens.filter(({ value }) => value !== data.removedValue.value)]);
				break;
			default:
				break;
		}
	};

	const customStyles = {
		input: (styles) => ({
			...styles,
			cursor: 'text',
			fontSize: '14px',
			padding: 0,
		}),
		control: (styles) => ({
			...styles,
			cursor: 'text',
		}),
		multiValue: (styles) => ({
			...styles,
			padding: '4px',
		}),
		multiValueLabel: (styles) => ({
			...styles,
			fontSize: '14px',
		}),
		multiValueRemove: (styles) => ({
			...styles,
			cursor: 'pointer',
			paddingTop: '2px',
		}),
	};

	return (
		<CreatableSelect
			{...props}
			isMulti
			components={{ DropdownIndicator: null }}
			inputValue={inputValue}
			isClearable={false}
			menuIsOpen={false}
			autoFocus
			onChange={handleChange}
			onInputChange={(val) => setInputValue(val)}
			onKeyDown={handleKeyDown}
			placeholder="Type a synonym and press enter..."
			value={tokens}
			styles={customStyles}
		/>
	);
};

export default MultiInput;
