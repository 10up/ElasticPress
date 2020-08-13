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
		<div className="synonym-alternative-editor">
			<input
				type="text"
				className="ep-synonyms__input"
				onChange={(e) => setPrimaryTerm(e.target.value)}
				value={primaryTerm}
				ref={primaryRef}
			/>
			<LinkedMultiInput
				{...props}
				synonyms={props.synonyms.filter((item) => !item.primary)}
			/>
		</div>
	);
}
