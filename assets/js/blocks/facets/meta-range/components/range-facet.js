/**
 * External dependencies.
 */
import ReactSlider from 'react-slider';

/**
 * WordPress dependencies.
 */
import { WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Range facet component.
 *
 * @param {object} props Props.
 * @param {number} props.min Minimum value.
 * @param {number} props.max Maximum value.
 * @param {number[]} props.value Currnet value.
 * @returns {WPElement} Component element.
 */
export default ({ min, max, value, ...props }) => {
	return (
		<div className="ep-range-facet">
			<div className="ep-range-facet__slider">
				<ReactSlider
					className="ep-range-slider"
					minDistance={1}
					thumbActiveClassName="ep-range-slider__thumb--active"
					thumbClassName="ep-range-slider__thumb"
					trackClassName="ep-range-slider__track"
					min={min}
					max={max}
					value={value}
					{...props}
				/>
			</div>
			<div className="ep-range-facet__values">
				{value[0]} — {value[1]}
			</div>
			<div className="ep-range-facet__action">
				<button type="submit">{__('Filter', 'elasticpress')}</button>
			</div>
		</div>
	);
};
