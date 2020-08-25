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
	const { synonymsTextareaInputName, solrInputHeading } = window.epSynonyms.i18n;

	useEffect(() => {
		setSolr(reducedState);
	}, [reducedState]);

	useEffect(() => {
		if (!isSolrEditable && solr !== reducedState) {
			dispatch({ type: 'REDUCE_STATE_FROM_SOLR', data: solr });
		}
		dispatch({ type: 'SET_SOLR_EDITABLE', data: isSolrEditable });
	}, [isSolrEditable]);

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
						readOnly={!isSolrEditable}
						onChange={(e) => setSolr(e.target.value)}
					/>
				</div>
			</div>
		</div>
	);
}
