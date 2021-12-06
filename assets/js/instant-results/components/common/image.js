/**
 * WordPress dependencies.
 */
import { WPElement } from '@wordpress/element';

/**
 * Image component.
 *
 * @param {Option} props Component props.
 *
 * @return {WPElement} Component element.
 */
export default ({ alt, height, ID, src, width, ...props }) => {
	return <img alt={alt} src={src} width={width} height={height} {...props} />;
};
