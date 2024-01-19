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
 * Replacements group component.
 *
 * @returns {WPElement}
 */
export default () => {
	const { replacements } = useSynonymsSettings();

	return (
		<>
			<RawHTML>
				{safeHTML(
					__(
						'<p><strong>Replacements</strong> are terms that replace other incorrect or obsolete terms.</p>',
						'elasticpress',
					),
				)}
				{safeHTML(
					__(
						'<p>Use replacements when you want search queries for certain terms to return results that are only relevant to another term, or set of terms. This can be useful for supporting specific typos or incorrect phrasing. For example, when a search for the phrase "intensive purposes" should only return results including the phrase "intents and purposes".</p>',
						'elasticpress',
					),
				)}
			</RawHTML>
			<VisualEditor
				labels={{
					add: __('Add replacements', 'elasticpress'),
					edit: __('Edit Replacements', 'elasticpress'),
					new: __('Add Replacements', 'elasticpress'),
					primary: __('Terms', 'replacements'),
					synonyms: __('Replacements', 'elasticpress'),
				}}
				messages={{
					added: __('Added replacements.', 'elasticpress'),
					deleted: __('Deleted replacements.', 'elasticpress'),
					invalid: __(
						'Replacement sets require at least one term and one replacement.',
						'elasticpress',
					),
					updated: __('Updated replacements.', 'elasticpress'),
				}}
				mode="replacements"
				rules={replacements}
			/>
		</>
	);
};
