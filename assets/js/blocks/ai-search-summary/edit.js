/**
 * WordPress dependencies.
 */
import { RichText, useBlockProps } from '@wordpress/block-editor';
import { Placeholder } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Edit component.
 *
 * @param {object} props Component props.
 * @param {object} props.attributes Block attributes.
 * @param {Function} props.setAttributes Block attribute setter.
 * @returns {Function} Component.
 */
export default ({ attributes, setAttributes }) => {
	const { note, title } = attributes;

	const blockProps = useBlockProps({
		className: 'ep-ai-search-summary',
	});

	return (
		<div {...blockProps}>
			<RichText
				aria-label={__('Title text', 'elasticpress')}
				placeholder={__('Add title', 'elasticpress')}
				withoutInteractiveFormatting
				value={title}
				onChange={(html) => setAttributes({ title: html })}
			/>
			<Placeholder label={__('AI Response', 'elasticpress')} />
			<RichText
				aria-label={__('Note', 'elasticpress')}
				placeholder={__('Add a note', 'elasticpress')}
				withoutInteractiveFormatting
				value={note}
				onChange={(html) => setAttributes({ note: html })}
			/>
		</div>
	);
};
