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
						'<p><strong>Replacements</strong> are terms that replace other incorrect or obsolete terms in search queries.</p>',
						'elasticpress',
					),
				)}
				{safeHTML(
					__(
						'<p>Use replacements when you want queries for certain terms to return results that are only relevant to another term, or set of terms. This can be useful for supporting incorrect phrasing or specific typos. For example, when a search for "i phone" should return results for "iPhone", but not other possible matches for "i phone".</p>',
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
