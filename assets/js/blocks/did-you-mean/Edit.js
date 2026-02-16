/**
 * WordPress dependencies.
 */
import { useBlockProps } from '@wordpress/block-editor';
import { createInterpolateElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Edit component.
 *
 * @returns {Function} Component.
 */
const Edit = () => {
	const blockProps = useBlockProps({
		className: 'wp-block-elasticpress-did-you-mean',
	});

	return (
		<div {...blockProps}>
			{createInterpolateElement(__('Did you mean <a>Hello</a>?', 'elasticpress'), {
				a: <a href="#" />, // eslint-disable-line jsx-a11y/anchor-has-content, jsx-a11y/control-has-associated-label, jsx-a11y/anchor-is-valid
			})}
		</div>
	);
};

export default Edit;
