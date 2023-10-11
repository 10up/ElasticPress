/**
 * WordPress dependencies.
 */
import { WPElement } from '@wordpress/element';

/**
 * Image component.
 *
 * @param {Option} props Component props.
 *
 * @returns {WPElement} Component element.
 */
export default ({ alt, height, ID, src, width, srcset, ...props }) => {
	return <img alt={alt} src={src} width={width} height={height} srcSet={srcset} {...props} />;
};
