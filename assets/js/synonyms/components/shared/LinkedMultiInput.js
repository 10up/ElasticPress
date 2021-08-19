import React, { useContext } from 'react';
import MultiInput from './MultiInput';
import { Dispatch } from '../../context';

/**
 * Linked MultiInput
 *
 * @param {Object} props Props.
 * @param {string} props.id Set/Alternative id.
 * @param {Object[]} props.synonyms Array of synonyms.
 * @param {string} props.removeAction Name of action to dispatch on remove.
 * @param {string} props.updateAction Name of action to dispatch on update.
 * @return {React.FC} LinkedMultiInput component
 */
const LinkedMultiInput = ({ id, synonyms, removeAction, updateAction }) => {
	const dispatch = useContext(Dispatch);
	const { removeItemText } = window.epSynonyms.i18n;

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
				tokens={synonyms}
				setTokens={(tokens) => dispatch({ type: updateAction, data: { id, tokens } })}
			/>
			<button className="synonym__remove" type="button" onClick={handleClear}>
				<span className="dashicons dashicons-dismiss" />
				<span>{removeItemText}</span>
			</button>
		</>
	);
};

export default LinkedMultiInput;
