/**
 * WordPress dependencies.
 */
import { useLayoutEffect, useState, WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import RangeSlider from './components/range-slider';

/**
 * App component.
 *
 * Accepts input elements as properties. These elements are used to derive the
 * current, minimum, and maximum values, and are updated with new values as the
 * range slider is used.
 *
 * @param {object} props Components props.
 * @param {Element} props.max Maximum value input element.
 * @param {Element} props.min Minimum value input element.
 * @returns {WPElement}
 */
export default ({ max, min }) => {
	/**
	 * Minimum and maximum possible values.
	 */
	const maxAgg = Math.ceil(max.max);
	const minAgg = Math.floor(min.min);

	/**
	 * Selected minimum and maximum values.
	 */
	const defaultTo = max.value ? parseInt(max.value, 10) : maxAgg;
	const defaultFrom = min.value ? parseInt(min.value, 10) : minAgg;

	/**
	 * Current minimum and maximum values.
	 */
	const [to, setTo] = useState(defaultTo);
	const [from, setFrom] = useState(defaultFrom);

	/**
	 * Handle change.
	 *
	 * @param {Array} value Value range.
	 * @param {number} value.0 Minimum value.
	 * @param {number} value.1 Mximum value.
	 * @returns {void}
	 */
	const onChange = ([from, to]) => {
		setTo(to);
		setFrom(from);
	};

	/**
	 * Effects.
	 */
	useLayoutEffect(() => {
		max.value = Math.min(maxAgg, to);
		min.value = Math.max(minAgg, from);
	}, [from, to, min, max, minAgg, maxAgg]);

	/**
	 * Render.
	 */
	return (
		<div className="ep-range-facet">
			<div className="ep-range-facet__slider">
				<RangeSlider max={maxAgg} min={minAgg} onChange={onChange} value={[from, to]} />
			</div>
			<div className="ep-range-facet__values">
				{from} — {to}
			</div>
			<div className="ep-range-facet__action">
				<button type="submit">{__('Filter', 'elasticpress')}</button>
			</div>
		</div>
	);
};
