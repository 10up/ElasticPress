/**
 * WordPress dependencies.
 */
import { safeHTML } from '@wordpress/dom';
import { RawHTML, WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { useSynonymsSettings } from '../../provider';
import VisualEditor from '../editors/visual-editor';

/**
 * Hyponyms group component.
 *
 * @returns {WPElement}
 */
export default () => {
	const { hyponyms } = useSynonymsSettings();

	return (
		<>
			<RawHTML>
				{safeHTML(
					__(
						'<p><strong>Hyponyms</strong> are terms with a more specific meaning than another more generic terms, called a <strong>hypernym</strong>. For example, <em>aqua</em>, <em>azure</em>, and <em>cerulean</em> are all hyponyms of <em>blue</em>, their hypernym.</p>',
						'elasticpress',
					),
				)}
				{safeHTML(
					__(
						'<p>Use hyponyms when you want search queries for a parent term to return results relevant to itself or any of its child terms, but search queries for a child term to only return results that are relevant to that term. For example, when a search for "blue" should return anything blue, whether it be aqua, azure, or cerulean, but a search for "cerulean" should return only items that are specifically cerulean blue.</p>',
						'elasticpress',
					),
				)}
			</RawHTML>
			<VisualEditor
				labels={{
					add: __('Add hyponyms', 'elasticpress'),
					edit: __('Edit Hyponyms', 'elasticpress'),
					new: __('Add Hyponyms', 'elasticpress'),
					primary: __('Hypernym', 'elasticpress'),
					synonyms: __('Hyponyms', 'elasticpress'),
				}}
				messages={{
					added: __('Added hyponyms.', 'elasticpress'),
					deleted: __('Deleted hyponyms.', 'elasticpress'),
					invalid: __(
						'Hyponym sets require a hypernym and at least one hyponym that is not the hypernym.',
						'elasticpress',
					),
					updated: __('Updated hyponyms.', 'elasticpress'),
				}}
				mode="hyponyms"
				rules={hyponyms}
			/>
		</>
	);
};
