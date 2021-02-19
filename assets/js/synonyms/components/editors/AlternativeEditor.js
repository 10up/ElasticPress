import React, { useState, useEffect, useContext, useRef } from 'react';
import LinkedMultiInput from '../shared/LinkedMultiInput';
import { Dispatch } from '../../context';

/**
 * Alternative Editor
 *
 * @param {object} props Props.
 * @returns {React.FC}
 */
export default function AlternativeEditor(props) {
	const primary = props.synonyms.find((item) => item.primary);
	const [primaryTerm, setPrimaryTerm] = useState(primary ? primary.value : '');
	const dispatch = useContext(Dispatch);
	const primaryRef = useRef(null);

	/**
	 * Create primary token
	 *
	 * @param {string} label Label.
	 * @returns {object}
	 */
	const createPrimaryToken = (label) => {
		return {
			label,
			value: label,
			primary: true,
		};
	};

	/**
	 * Handle key down.
	 *
	 * @param {React.SyntheticEvent} event Keydown event.
	 */
	const handleKeyDown = (event) => {
		switch (event.key) {
			case 'Enter':
				event.preventDefault();
				break;
			default:
		}
	};

	useEffect(() => {
		dispatch({
			type: 'UPDATE_ALTERNATIVE_PRIMARY',
			data: { id: props.id, token: createPrimaryToken(primaryTerm) },
		});
	}, [primaryTerm]);

	useEffect(() => {
		primaryRef.current.focus();
	}, [primaryRef]);

	return (
		<>
			<input
				type="text"
				className="ep-synonyms__input"
				onChange={(e) => setPrimaryTerm(e.target.value)}
				value={primaryTerm}
				onKeyDown={handleKeyDown}
				ref={primaryRef}
			/>
			<LinkedMultiInput
				{...props}
				synonyms={props.synonyms.filter((item) => !item.primary)}
			/>
		</>
	);
}
