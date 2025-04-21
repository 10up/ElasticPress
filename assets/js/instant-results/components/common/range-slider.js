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
 */
export default ({ ...props }) => {
	return (
		<ReactSlider
			className="ep-search-range-slider"
			minDistance={1}
			thumbActiveClassName="ep-search-range-slider__thumb--active"
			thumbClassName="ep-search-range-slider__thumb"
			trackClassName="ep-search-range-slider__track"
			{...props}
		/>
	);
};
