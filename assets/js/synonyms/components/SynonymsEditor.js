import React, { useContext, useEffect } from 'react';
import { State, Dispatch } from '../context';
import SetsEditor from './editors/SetsEditor';
import AlterativesEditor from './editors/AlternativesEditor';
import SolrEditor from './editors/SolrEditor';

/**
 * Synonyms editor component.
 *
 * @returns {React.FC}
 */
export default function SynonymsEditor() {
	const state = useContext(State);
	const dispatch = useContext(Dispatch);
	const { alternatives, sets, isSolrEditable, isSolrVisible, dirty, submit } = state;
	const {
		alternativesTitle,
		alternativesDescription,
		setsTitle,
		setsDescription,
		solrTitle,
		solrDescription,
		submitText,
	} = window.epSynonyms.i18n;

	/**
	 * Checks if the form is valid.
	 *
	 * @param {object} _state Current state.
	 * @returns {boolean}
	 */
	const isValid = (_state) => {
		return [..._state.sets, ..._state.alternatives].reduce((valid, item) => {
			return !valid ? valid : item.valid;
		}, true);
	};

	/**
	 * Handles submitting the form.
	 */
	const handleSubmit = () => {
		dispatch({ type: 'VALIDATE_ALL' });
		dispatch({ type: 'SUBMIT' });
	};

	useEffect(() => {
		if (submit && !dirty && isValid(state)) {
			document.querySelector('.wrap form').submit();
		}
	}, [submit, dirty]);

	return (
		<>
			<div className="synonym-editor synonym-editor__sets">
				<h2>{`${setsTitle} (${sets.length})`}</h2>
				<p>{setsDescription}</p>
				{!isSolrEditable && <SetsEditor sets={sets} />}
			</div>
			<div className="synonym-editor synonym-editor__alteratives">
				<h2>{`${alternativesTitle} (${alternatives.length})`}</h2>
				<p>{alternativesDescription}</p>
				{!isSolrEditable && <AlterativesEditor alternatives={alternatives} />}
			</div>
			<div className="synonym-editor synonym-editor__solr">
				{isSolrVisible && <h2>{solrTitle}</h2>}
				{isSolrVisible && <p>{solrDescription}</p>}
				<SolrEditor />
			</div>
			<button onClick={handleSubmit} type="button" className="button button-primary">
				{submitText}
			</button>
		</>
	);
}
