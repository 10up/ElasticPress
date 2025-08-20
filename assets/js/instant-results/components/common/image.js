/**
 * WordPress dependencies.
 */
import { WPElement } from '@wordpress/element';

/**
 * Calculate the sizes attribute.
 *
 * @param {number} width Image width
 * @param {number} height Image height
 * @param {number} containerWidth Container width
 * @returns {string} Sizes attribute value
 */
const calculateSizes = (width, height, containerWidth) => {
	if (!width || !height || width <= 0 || height <= 0) {
		return `${containerWidth}px`;
	}

	const effectiveWidth = Math.max((width / height) * containerWidth, containerWidth);

	return `${Math.round(effectiveWidth)}px`;
};

/**
 * Image component.
 *
 * @param {Option} props Component props.
 *
 * @returns {WPElement} Component element.
 */
export default ({ alt, height, ID, src, width, srcset, containerWidth = 300, ...props }) => {
	const sizes = calculateSizes(width, height, containerWidth);

	return (
		<img
			alt={alt}
			src={src}
			width={width}
			height={height}
			srcSet={srcset}
			sizes={sizes}
			{...props}
		/>
	);
};
