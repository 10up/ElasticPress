/**
 * WordPress dependencies.
 */
import { TextControl } from '@wordpress/components';
import { WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Facet sorting control component.
 *
 * @param {object} props Component props.
 * @param {Function} props.onChange Change handler.
 * @param {string} props.value Current value.
 * @returns {WPElement}
 */
export default ({ onChange, value }) => {
	return (
		<TextControl
			label={__('Search field placeholder', 'elasticpress')}
			value={value}
			onChange={onChange}
		/>
	);
};
