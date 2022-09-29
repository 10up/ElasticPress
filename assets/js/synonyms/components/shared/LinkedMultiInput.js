/**
 * WordPress dependencies.
 */
import { FormTokenField } from '@wordpress/components';
import { useContext, WPElement } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { Dispatch } from '../../context';

/**
 * Linked MultiInput
 *
 * @param {object}   props              Props.
 * @param {string}   props.id           Set/Alternative id.
 * @param {object[]} props.synonyms     Array of synonyms.
 * @param {string}   props.removeAction Name of action to dispatch on remove.
 * @param {string}   props.updateAction Name of action to dispatch on update.
 * @returns {WPElement} LinkedMultiInput component
 */
const LinkedMultiInput = ({ id, synonyms, removeAction, updateAction }) => {
	const dispatch = useContext(Dispatch);
	const { removeItemText } = window.epSynonyms.i18n;

	/**
	 * Handle change to tokens.
	 *
	 * @param {string[]} value Array of tokens.
	 */
	const handleChange = (value) => {
		const tokens = value.map((v) => {
			const token = {
				label: v,
				value: v,
				primary: false,
			};

			return token;
		});

		dispatch({ type: updateAction, data: { id, tokens } });
	};

	/**
	 * Handle clearing the synonym.
	 */
	const handleClear = () => {
		dispatch({ type: removeAction, data: id });
	};

	return (
		<>
			<FormTokenField
				key={id}
				label={null}
				onChange={handleChange}
				value={synonyms.map((s) => s.value)}
			/>
			<button className="synonym__remove" type="button" onClick={handleClear}>
				<span className="dashicons dashicons-dismiss" />
				<span>{removeItemText}</span>
			</button>
		</>
	);
};

export default LinkedMultiInput;
