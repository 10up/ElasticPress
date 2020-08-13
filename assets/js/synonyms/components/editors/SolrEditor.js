import React, { useContext, useEffect, useState } from 'react';
import { State, Dispatch } from '../../context';
import { reduceStateToSolr } from '../../utils';

/**
 * Synonym Inspector
 *
 * @returns {React.FC}
 */
export default function SolrEditor() {
	const state = useContext(State);
	const dispatch = useContext(Dispatch);
	const reducedState = reduceStateToSolr(state);
	const { isSolrEditable, isSolrVisible } = state;
	const [solr, setSolr] = useState(reducedState);
	const [editing, setEditing] = useState(isSolrEditable);
	const {
		synonymsTextareaInputName,
		solrInputHeading,
		solrEditButtonText,
		solrApplyButtonText,
	} = window.epSynonyms.i18n;

	useEffect(() => {
		setSolr(reducedState);
	}, [reducedState]);

	useEffect(() => {
		if (!editing && solr !== reducedState) {
			dispatch({ type: 'REDUCE_STATE_FROM_SOLR', data: solr });
		}
		dispatch({ type: 'SET_SOLR_EDITABLE', data: editing });
	}, [editing]);

	/**
	 * Toggle Solr Editable
	 *
	 * @param {React.SytheticEvent} e Event
	 */
	const toggleSolrEditable = (e) => {
		setEditing(!editing);
		e.preventDefault();
	};

	return (
		<div className={`synonym-solr-editor metabox-holder ${!isSolrVisible ? 'hidden' : ''}`}>
			<div className="postbox">
				<h2 className="hndle">
					<span>{solrInputHeading}</span>
				</h2>
				<div className="inside">
					<textarea
						className="large-text"
						id="ep-synonym-input"
						name={synonymsTextareaInputName}
						rows="20"
						value={solr}
						readOnly={!editing}
						onChange={(e) => setSolr(e.target.value)}
					/>
					<button className="button button-secondary" onClick={toggleSolrEditable}>
						{editing ? solrApplyButtonText : solrEditButtonText}
					</button>
				</div>
			</div>
		</div>
	);
}
