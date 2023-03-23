/**
 * WordPress dependencies.
 */
import { Warning } from '@wordpress/block-editor';
import { WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Empty preview placeholder.
 *
 * @param {object} props Component props.
 * @param {object} props.attributes Block attributes.
 * @returns {WPElement}
 */
export default ({ attributes }) => {
	const { type } = attributes;

	return (
		<Warning>
			{type === 'taxonomy'
				? __(
						'Preview unavailable. The selected taxonomy has no terms assigned to searchable content.',
						'elasticpress',
				  )
				: __(
						'Preview unavailable. There is no searchable content available with the selected metadata.',
						'elasticpress',
				  )}
		</Warning>
	);
};
