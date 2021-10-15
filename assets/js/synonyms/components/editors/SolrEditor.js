import React, { useContext } from 'react';
import { State, Dispatch } from '../../context';

/**
 * Synonym Inspector
 *
 * @return {React.FC} SolrEditor Component
 */
const SolrEditor = () => {
	const state = useContext(State);
	const dispatch = useContext(Dispatch);
	const { isSolrEditable, isSolrVisible, solr } = state;
	const { synonymsTextareaInputName, solrInputHeading } = window.epSynonyms.i18n;

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
						onChange={(event) =>
							dispatch({ type: 'UPDATE_SOLR', data: event.target.value })
						}
					/>
				</div>
			</div>
		</div>
	);
};

export default SolrEditor;
