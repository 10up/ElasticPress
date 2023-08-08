/**
 * WordPress dependencies.
 */
import { ToggleControl } from '@wordpress/components';
import { WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Facet display count control component.
 *
 * @param {object} props Component props.
 * @param {string} props.checked Is checked?
 * @param {Function} props.onChange Change handler.
 * @returns {WPElement}
 */
export default ({ onChange, checked }) => {
	return (
		<ToggleControl
			checked={checked}
			label={__('Display count', 'elasticpress')}
			onChange={onChange}
		/>
	);
};
