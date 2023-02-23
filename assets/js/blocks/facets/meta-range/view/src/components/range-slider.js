/**
 * External dependencies.
 */
import ReactSlider from 'react-slider';

/**
 * WordPress dependencies.
 */
import { WPElement } from '@wordpress/element';

/**
 * Range slider component.
 *
 * @param {object} props Props.
 * @returns {WPElement} Element.
 * @todo Consolidate with the range slider used by Instant Results.
 */
export default ({ ...props }) => {
	return (
		<ReactSlider
			className="ep-range-slider"
			minDistance={1}
			thumbActiveClassName="ep-range-slider__thumb--active"
			thumbClassName="ep-range-slider__thumb"
			trackClassName="ep-range-slider__track"
			{...props}
		/>
	);
};
