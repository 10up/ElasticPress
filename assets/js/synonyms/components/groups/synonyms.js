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
 * Synonyms group component.
 *
 * @returns {WPElement}
 */
export default () => {
	const { synonyms } = useSynonymsSettings();

	return (
		<>
			<RawHTML>
				{safeHTML(
					__(
						'<p><strong>Synonyms</strong> are terms with similar meanings. For example, <em>sneaker</em>, <em>tennis shoe</em>, <em>trainer</em>, and <em>running shoe</em> could all refer to a particular type of shoe.</p>',
						'elasticpress',
					),
				)}
				{safeHTML(
					__(
						'<p>Use synonyms when you want queries for a specific term to also return results relevant to any of its synonyms. This can be useful for supporting products and services whose names have changed over time or regional variations in terminology. For example, when a search for "sneaker" should return sneakers, tennis shoes, trainers and running shoes.</p>',
						'elasticpress',
					),
				)}
			</RawHTML>
			<VisualEditor
				labels={{
					add: __('Add synonyms', 'elasticpress'),
					edit: __('Edit Synonyms', 'elasticpress'),
					new: __('Add Synonyms', 'elasticpress'),
					synonyms: __('Synonyms', 'elasticpress'),
				}}
				messages={{
					added: __('Added synonyms.', 'elasticpress'),
					deleted: __('Deleted synonyms.', 'elasticpress'),
					invalid: __('Synonym sets require at least two synonyms.', 'elasticpress'),
					updated: __('Updated synonyms.', 'elasticpress'),
				}}
				mode="synonyms"
				rules={synonyms}
			/>
		</>
	);
};
