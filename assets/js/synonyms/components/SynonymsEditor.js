import React, { useContext, useEffect } from 'react';
import { State, Dispatch } from '../context';
import SetsEditor from './editors/SetsEditor';
import AlterativesEditor from './editors/AlternativesEditor';
import SolrEditor from './editors/SolrEditor';

/**
 * Synonyms editor component.
 *
 * @return {React.FC} Synonyms component
 */
const SynonymsEditor = () => {
	const state = useContext(State);
	const dispatch = useContext(Dispatch);
	const { alternatives, sets, isSolrEditable, isSolrVisible, dirty, submit } = state;
	const {
		pageHeading,
		pageDescription,
		pageToggleAdvanceText,
		pageToggleSimpleText,
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
	 * @param {Object} _state Current state.
	 * @return {boolean} If the form is valid
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
		if (isSolrEditable) {
			dispatch({ type: 'REDUCE_SOLR_TO_STATE' });
		}

		dispatch({ type: 'VALIDATE_ALL' });
		dispatch({ type: 'REDUCE_STATE_TO_SOLR' });
		dispatch({ type: 'SUBMIT' });
	};

	/**
	 * Handle toggling the editor type.
	 */
	const handleToggleAdvance = () => {
		if (isSolrEditable) {
			dispatch({ type: 'REDUCE_SOLR_TO_STATE' });
		} else {
			dispatch({ type: 'REDUCE_STATE_TO_SOLR' });
		}

		dispatch({ type: 'SET_SOLR_EDITABLE', data: !isSolrEditable });
	};

	useEffect(() => {
		if (submit && !dirty && isValid(state)) {
			document.querySelector('.wrap form').submit();
		}
	}, [submit, dirty, state]);

	return (
		<>
			<h1 className="wp-heading-inline">
				{pageHeading}{' '}
				<button onClick={handleToggleAdvance} type="button" className="page-title-action">
					{isSolrEditable ? pageToggleSimpleText : pageToggleAdvanceText}
				</button>
			</h1>
			<p>{pageDescription}</p>

			{!isSolrEditable && (
				<>
					<div className="synonym-editor synonym-editor__sets">
						<h2>{`${setsTitle} (${sets.length})`}</h2>
						<p>{setsDescription}</p>
						<SetsEditor sets={sets} />
					</div>
					<div className="synonym-editor synonym-editor__alteratives">
						<h2>{`${alternativesTitle} (${alternatives.length})`}</h2>
						<p>{alternativesDescription}</p>
						<AlterativesEditor alternatives={alternatives} />
					</div>
				</>
			)}

			<div className="synonym-editor synonym-editor__solr">
				{isSolrVisible && <h2>{solrTitle}</h2>}
				{isSolrVisible && <p>{solrDescription}</p>}
				<SolrEditor />
			</div>

			<input
				type="hidden"
				name="synonyms_editor_mode"
				value={isSolrEditable ? 'advanced' : 'simple'}
			/>

			<div className="synonym-btn-group">
				<button onClick={handleSubmit} type="button" className="button button-primary">
					{submitText}
				</button>
			</div>
		</>
	);
};

export default SynonymsEditor;
