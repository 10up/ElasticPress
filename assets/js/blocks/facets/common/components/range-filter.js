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
 * @param {string} props.clearUrl Clear filter URL.
 * @param {number} props.min Minimum value.
 * @param {number} props.max Maximum value.
 * @param {string} props.prefix Value prefix.
 * @param {string} props.suffix Value suffix.
 * @param {number[]} props.value Current value.
 * @returns {WPElement} Component element.
 */
export default ({ clearUrl, min, max, prefix, suffix, value, ...props }) => {
	// Expose the onChange() method so that Cypress can set the app state
	if (window.Cypress) {
		window.app = { sliderChange: props.onChange };
	}

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
				{prefix}
				{value[0]}
				{suffix}
				{' — '}
				{prefix}
				{value[1]}
				{suffix}
			</div>
			<div className="ep-range-facet__action">
				{clearUrl ? <a href={clearUrl}>{__('Clear', 'elasticpress')}</a> : null}{' '}
				<button className="wp-element-button" type="submit">
					{__('Filter', 'elasticpress')}
				</button>
			</div>
		</div>
	);
};
