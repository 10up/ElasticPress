import React, { useContext } from 'react';
import { State } from '../context';
import SetsEditor from './editors/SetsEditor';
import AlterativesEditor from './editors/AlternativesEditor';
import SolrEditor from './editors/SolrEditor';

/**
 * Synonyms editor component.
 */
export default function SynonymsEditor() {
	const state = useContext( State );
	const { alternatives, sets, isSolrEditable } = state;
	const {
		alternativesTitle,
		alternativesDescription,
		setsTitle,
		setsDescription,
		solrTitle,
		solrDescription,
	} = window.epSynonyms.i18n;

	return (
		<>
			<div className="synonym-editor synonym-editor__sets">
				<h2>{ `${setsTitle} (${sets.length})` }</h2>
				<p>{ setsDescription }</p>
				{ ! isSolrEditable && <SetsEditor sets={sets}/> }
			</div>

			<div className="synonym-editor synonym-editor__alteratives">
				<h2>{ `${alternativesTitle} (${alternatives.length})` }</h2>
				<p>{ alternativesDescription }</p>
				{ ! isSolrEditable && <AlterativesEditor alternatives={alternatives}/> }
			</div>

			<div className="synonym-editor synonym-editor__solr">
				<h2>{ solrTitle }</h2>
				<p>{ solrDescription }</p>
				<SolrEditor />
			</div>
		</>
	);
}
