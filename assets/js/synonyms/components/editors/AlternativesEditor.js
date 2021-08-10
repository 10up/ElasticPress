import React, { Fragment, useContext } from 'react';
import AlternativeEditor from './AlternativeEditor';
import { Dispatch, State } from '../../context';

/**
 * Synonyms editor component.
 *
 * @param {Object} props Props.
 * @param {Object[]} props.alternatives Defined alternatives (explicit mappings).
 * @return {React.FC} AlternativesEditor component
 */
const AlternativesEditor = ({ alternatives }) => {
	const dispatch = useContext(Dispatch);
	const state = useContext(State);
	const {
		alternativesInputHeading,
		alternativesPrimaryHeading,
		alternativesAddButtonText,
		alternativesErrorMessage,
	} = window.epSynonyms.i18n;

	/**
	 * Handle click.
	 *
	 * @param {React.SyntheticEvent} e Event.
	 */
	const handleClick = (e) => {
		const [lastItem] = state.alternatives.slice(-1);
		if (!alternatives.length || lastItem.synonyms.filter(({ value }) => value.length).length) {
			dispatch({ type: 'ADD_ALTERNATIVE' });
		}
		e.preventDefault();
	};

	return (
		<div className="synonym-alternatives-editor metabox-holder">
			<div className="postbox">
				<h2 className="hndle">
					<span className="synonym-alternatives__primary-heading">
						{alternativesPrimaryHeading}
					</span>
					<span className="synonym-alternatives__input-heading">
						{alternativesInputHeading}
					</span>
				</h2>
				<div className="inside">
					{alternatives.map((props) => (
						<Fragment key={props.id}>
							<div className="synonym-alternative-editor">
								<AlternativeEditor
									{...props}
									updateAction="UPDATE_ALTERNATIVE"
									removeAction="REMOVE_ALTERNATIVE"
								/>
							</div>
							{!props.valid && (
								<p className="synonym__validation">{alternativesErrorMessage}</p>
							)}
						</Fragment>
					))}
					<button type="button" className="button button-secondary" onClick={handleClick}>
						{alternativesAddButtonText}
					</button>
				</div>
			</div>
		</div>
	);
};

export default AlternativesEditor;
