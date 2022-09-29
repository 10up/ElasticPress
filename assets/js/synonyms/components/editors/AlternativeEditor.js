/**
 * WordPress dependencies.
 */
import { useContext, useEffect, useMemo, useRef, useState, WPElement } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { Dispatch } from '../../context';
import LinkedMultiInput from '../shared/LinkedMultiInput';

/**
 * Alternative Editor
 *
 * @param {object} props Props.
 * @returns {WPElement} AlternativeEditor component
 */
const AlternativeEditor = (props) => {
	const { id, synonyms, removeAction, updateAction } = props;
	const primary = synonyms.find((item) => item.primary);
	const [primaryTerm, setPrimaryTerm] = useState(primary ? primary.value : '');
	const dispatch = useContext(Dispatch);
	const primaryRef = useRef(null);

	/**
	 * Create primary token
	 *
	 * @param {string} label Label.
	 * @returns {object} Primary token
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
	 * @param {Event} event Keydown event.
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
			data: { id, token: createPrimaryToken(primaryTerm) },
		});
	}, [primaryTerm, id, dispatch]);

	useEffect(() => {
		primaryRef.current.focus();
	}, [primaryRef]);

	const memoizedSynonyms = useMemo(() => {
		return synonyms.filter((item) => !item.primary);
	}, [synonyms]);

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
				id={id}
				updateAction={updateAction}
				removeAction={removeAction}
				synonyms={memoizedSynonyms}
			/>
		</>
	);
};

export default AlternativeEditor;
