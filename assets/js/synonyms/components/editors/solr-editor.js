/**
 * WordPress dependencies.
 */
import { TextareaControl } from '@wordpress/components';
import { WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { useSynonymsSettings } from '../../provider';

/**
 * Solr editor component.
 *
 * @returns {WPElement}
 */
const SolrEditor = () => {
	const { isBusy, solr, updateSolr } = useSynonymsSettings();

	/**
	 * Handle changes to the Solr synonyms value.
	 *
	 * @param {Event} value Textarea control value.
	 */
	const onChange = (value) => {
		updateSolr(value);
	};

	return (
		<TextareaControl
			className="ep-synonyms-solr-editor"
			disabled={isBusy}
			label={__('Solr synonyms', 'elasticpress')}
			rows="20"
			value={solr}
			onChange={onChange}
		/>
	);
};

export default SolrEditor;
