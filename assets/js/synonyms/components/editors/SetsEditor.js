import React, { useContext } from 'react';
import LinkedMultiInput from '../shared/LinkedMultiInput';
import { Dispatch, State } from '../../context';

/**
 * Synonyms editor component.
 *
 * @param {object} props Props
 * @returns {React.FC}
 */
export default function SetsEditor({ sets }) {
	const dispatch = useContext(Dispatch);
	const state = useContext(State);
	const { setsInputHeading, setsAddButtonText } = window.epSynonyms.i18n;

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
					{sets.map(({ synonyms, id }) => (
						<div className="synonym-set-editor" key={id}>
							<LinkedMultiInput
								updateAction="UPDATE_SET"
								removeAction="REMOVE_SET"
								synonyms={synonyms}
								id={id}
							/>
						</div>
					))}
					<button className="button button-secondary" onClick={handleClick}>
						{setsAddButtonText}
					</button>
				</div>
			</div>
		</div>
	);
}
