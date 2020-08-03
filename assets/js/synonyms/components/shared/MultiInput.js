import React, { useState } from 'react';
import CreatableSelect from 'react-select/creatable';

/**
 * Synonyms editor component.
 */
export default function MultiInput( props ) {
	const { tokens, setTokens, onClear } = props;
	const [ inputValue, setInputValue] = useState( '' );

	/**
	 * Create option.
	 * @param {String} label
	 * @return {Object}
	 */
	const createOption = label => ( {
		label,
		value: label,
	} );

	/**
	 * Handle key down.
	 * @param {SyntheticEvent} event
	 */
	const handleKeyDown = ( event ) => {
		if ( ! tokens ) return;
		switch ( event.key ) {
				case 'Enter':
				case 'Tab':
					if ( ( -1 === tokens.map( ( {value} ) => value ).indexOf( inputValue.trim() ) ) ) {
						setTokens( [ ...tokens, createOption( inputValue ) ] );
					}
					setInputValue( '' );
					event.preventDefault();
		}
	};

	/**
	 * Handle change.
	 * @param {String} value
	 * @param {Object} data
	 */
	const handleChange = ( value, data ) => {
		switch ( data.action ) {
				case 'remove-value':
					setTokens( [ ...tokens.filter( ( {value} ) => value !== data.removedValue.value ) ] );
					break;
				case 'clear':
					onClear ? onClear() : setTokens( [] );
					break;
				default:
					break;
		}
	};

	return (
		<CreatableSelect
			{ ...props }
			isMulti
			components={ { DropdownIndicator: null } }
			inputValue={inputValue}
			isClearable
			menuIsOpen={false}
			onChange={handleChange}
			onInputChange={ val => setInputValue( val ) }
			onKeyDown={handleKeyDown}
			placeholder="Type a synonym and press enter..."
			value={tokens}
		/>
	);
}
