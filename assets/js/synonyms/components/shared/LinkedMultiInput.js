import React, { useContext, useState, useEffect } from 'react';
import MultiInput from './MultiInput';
import { Dispatch } from '../../context';

/**
 * Linked MultiInput
 * @param {String} synonymType
 * @param {Array} synonyms
 * @param {Number} index
 */
export default function LinkedMultiInput( { id, synonyms, removeAction, updateAction } ) {
	const dispatch = useContext( Dispatch );
	const [ tokens, setTokens ] = useState( synonyms || [] );

	useEffect( () => {
		if ( tokens != synonyms ) {
			dispatch( { type: updateAction, data: { id, tokens } } );
		}
	}, [ tokens ] );

	/**
	 * Handle clearing the synonym.
	 */
	const handleClear = () => {
		dispatch( { type: removeAction, data: id } );
	};

	return (
		<MultiInput
			key={id}
			className="ep-synonyms__linked-multi-input"
			tokens={tokens}
			setTokens={setTokens}
			onClear={handleClear}
		/>
	);
}
