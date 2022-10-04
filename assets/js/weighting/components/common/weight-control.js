/**
 * WordPress dependencies.
 */
import { RangeControl } from '@wordpress/components';
import { WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Weight control component.
 *
 * @param {object} props Component props.
 * @returns {WPElement} Component element.
 */
export default (props) => {
	return <RangeControl label={__('Weight', 'elasticpress')} min={1} max={100} {...props} />;
};
