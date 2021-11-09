import React, { useContext, Fragment } from 'react';
import LinkedMultiInput from '../shared/LinkedMultiInput';
import { Dispatch, State } from '../../context';

/**
 * Synonyms editor component.
 *
 * @param {Object} props Props
 * @param {Object[]} props.sets Defined sets (equivalent synonyms).
 * @return {React.FC} SetsEditor component
 */
const SetsEditor = ({ sets }) => {
	const dispatch = useContext(Dispatch);
	const state = useContext(State);
	const { setsInputHeading, setsAddButtonText, setsErrorMessage } = window.epSynonyms.i18n;

	/**
	 * Handle click.
	 *
	 * @param {React.SyntheticEvent} e Event
	 */
	const handleClick = (e) => {
		const [lastSet] = state.sets.slice(-1);
		if (!sets.length || lastSet.synonyms.length) {
			dispatch({ type: 'ADD_SET' });
		}
		e.preventDefault();
	};

	return (
		<div className="synonym-sets-editor metabox-holder">
			<div className="postbox">
				<h2 className="hndle">
					<span>{setsInputHeading}</span>
				</h2>
				<div className="inside">
					{sets.map((props) => (
						<Fragment key={props.id}>
							<div className="synonym-set-editor">
								<LinkedMultiInput
									{...props}
									updateAction="UPDATE_SET"
									removeAction="REMOVE_SET"
								/>
							</div>
							{!props.valid && (
								<p className="synonym__validation">{setsErrorMessage}</p>
							)}
						</Fragment>
					))}
					<button type="button" className="button button-secondary" onClick={handleClick}>
						{setsAddButtonText}
					</button>
				</div>
			</div>
		</div>
	);
};

export default SetsEditor;
