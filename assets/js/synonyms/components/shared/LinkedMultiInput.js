import React, { useContext, useState, useEffect } from 'react';
import MultiInput from './MultiInput';
import { Dispatch } from '../../context';

/**
 * Linked MultiInput
 *
 * @param {object} props Props.
 * @returns {React.FC}
 */
export default function LinkedMultiInput({ id, synonyms, removeAction, updateAction }) {
	const dispatch = useContext(Dispatch);
	const [tokens, setTokens] = useState(synonyms || []);
	const { removeItemText } = window.epSynonyms.i18n;

	useEffect(() => {
		if (tokens !== synonyms) {
			dispatch({ type: updateAction, data: { id, tokens } });
		}
	}, [tokens]);

	/**
	 * Handle clearing the synonym.
	 */
	const handleClear = () => {
		dispatch({ type: removeAction, data: id });
	};

	return (
		<>
			<MultiInput
				key={id}
				className="ep-synonyms__linked-multi-input"
				tokens={tokens}
				setTokens={setTokens}
			/>
			<button className="synonym__remove" type="button" onClick={handleClear}>
				<span className="dashicons dashicons-dismiss"></span>
				<span>{removeItemText}</span>
			</button>
		</>
	);
}
